#!/usr/bin/env bash
# Actualiza el backend de SIAR en el servidor con lo último de GitHub.
# Uso, desde la carpeta del proyecto en el servidor:
#   bash scripts/desplegar.sh
#
# SEGURIDAD DE LA BASE DE DATOS
# Este script solo APLICA migraciones pendientes ("migrate"): crea tablas o
# columnas nuevas y nunca borra datos. No usa migrate:fresh, migrate:refresh,
# migrate:reset, migrate:rollback ni db:wipe. Antes de migrar, además:
#   1. Se niega a correr si el entorno no es "production" (evita usarlo por
#      error en un equipo local).
#   2. Muestra las migraciones pendientes y su SQL (--pretend, sin ejecutarlo);
#      si aparece DROP, TRUNCATE o DELETE FROM, se detiene.
#   3. Hace un respaldo (mysqldump) en backend/storage/app/backups; si no se
#      puede, se detiene.
#   4. Después de migrar compara las filas de cada tabla: ninguna puede haber
#      disminuido.
#
# Variables opcionales (solo para casos puntuales):
#   SIAR_OMITIR_RESPALDO=1        migra aunque no exista mysqldump (no recomendado)
#   SIAR_PERMITIR_DESTRUCTIVO=1   migra aunque el SQL incluya DROP/TRUNCATE/DELETE (revisado a mano)
#   SIAR_PERMITIR_NO_PRODUCCION=1 corre aunque APP_ENV no sea production
#
# Todo va dentro de funciones y "main" termina con exit: así Bash lee el archivo
# completo antes de ejecutar y no se desordena si "git pull" lo actualiza.
set -euo pipefail

error() { echo "ERROR: $*" >&2; exit 1; }

# Ejecuta el ayudante de PHP (solo lectura) y devuelve lo que imprime.
estado_bd() { php "$RAIZ/scripts/estado_bd.php" "$1" | tr -d '\r'; }

verificar_entorno() {
  local entorno
  entorno="$(estado_bd entorno)" || error "No se pudo leer el entorno de la aplicación (¿falta backend/vendor o backend/.env?)."

  if [[ "$entorno" != "production" && "${SIAR_PERMITIR_NO_PRODUCCION:-0}" != "1" ]]; then
    error "APP_ENV es \"$entorno\", no \"production\". Este script es para el servidor. Si de verdad quieres correrlo aquí: SIAR_PERMITIR_NO_PRODUCCION=1 bash scripts/desplegar.sh"
  fi
}

# Respaldo completo de la base con mysqldump (la contraseña va por variable de
# entorno, no en la línea de comandos). Deja la ruta en $ARCHIVO_RESPALDO.
respaldar_base() {
  # Sin "mapfile < <(...)": esa sintaxis usa /dev/fd, que Hostinger no permite.
  local conexion driver="" host="" puerto="" base="" usuario="" clave=""
  conexion="$(estado_bd conexion)" || error "No se pudieron leer los datos de conexión de la base; no se migra."
  { IFS= read -r driver; IFS= read -r host; IFS= read -r puerto; IFS= read -r base; IFS= read -r usuario; IFS= read -r clave || true; } <<<"$conexion"

  if [[ "$driver" != "mysql" && "$driver" != "mariadb" ]]; then
    error "El respaldo automático solo soporta MySQL/MariaDB (la conexión es \"$driver\")."
  fi

  if ! command -v mysqldump >/dev/null 2>&1; then
    if [[ "${SIAR_OMITIR_RESPALDO:-0}" == "1" ]]; then
      echo "   AVISO: mysqldump no está disponible; se omite el respaldo por SIAR_OMITIR_RESPALDO=1."
      ARCHIVO_RESPALDO=""
      return 0
    fi
    error "No existe mysqldump, así que no se puede respaldar la base. Haz un respaldo desde el panel del hosting y vuelve a correr con SIAR_OMITIR_RESPALDO=1."
  fi

  local carpeta="$RAIZ/backend/storage/app/backups"
  mkdir -p "$carpeta"
  chmod 700 "$carpeta"
  ARCHIVO_RESPALDO="$carpeta/${base}_$(date +%Y%m%d_%H%M%S).sql"

  # --no-tablespaces evita un error de permisos en MySQL 8; MariaDB antiguo no
  # conoce esa opción, por eso hay un segundo intento sin ella.
  local -a comun=(-h "$host" -P "${puerto:-3306}" -u "$usuario" --single-transaction --quick --routines --triggers)
  # El archivo se crea primero con permisos 600 (solo el dueño); sin tocar el
  # umask del resto del script, para no afectar los archivos de caché de Laravel.
  : >"$ARCHIVO_RESPALDO"
  chmod 600 "$ARCHIVO_RESPALDO"
  if ! MYSQL_PWD="$clave" mysqldump "${comun[@]}" --no-tablespaces "$base" >"$ARCHIVO_RESPALDO" 2>/dev/null; then
    MYSQL_PWD="$clave" mysqldump "${comun[@]}" "$base" >"$ARCHIVO_RESPALDO" \
      || { rm -f "$ARCHIVO_RESPALDO"; error "mysqldump falló: no se migra sin respaldo."; }
  fi

  # Un volcado completo termina con "Dump completed".
  if [[ ! -s "$ARCHIVO_RESPALDO" ]] || ! tail -n 5 "$ARCHIVO_RESPALDO" | grep -q "Dump completed"; then
    error "El respaldo quedó incompleto ($ARCHIVO_RESPALDO): no se migra."
  fi

  echo "   Respaldo guardado en: $ARCHIVO_RESPALDO ($(du -h "$ARCHIVO_RESPALDO" | cut -f1))"

  # Se conservan los 10 respaldos más recientes.
  { ls -1t "$carpeta"/*.sql 2>/dev/null || true; } | tail -n +11 | while read -r viejo; do rm -f "$viejo"; done
}

# Compara las filas de antes y después: ninguna tabla puede haber perdido filas.
comparar_conteos() {
  local antes="$1" despues="$2" perdidas
  perdidas="$(awk -F'\t' 'NR==FNR { despues[$1]=$2; next } { if (!($1 in despues)) print $1 " (la tabla desapareció)"; else if (despues[$1]+0 < $2+0) print $1 " (" $2 " → " despues[$1] " filas)" }' "$despues" "$antes")"

  if [[ -n "$perdidas" ]]; then
    echo "ERROR: ¡después de migrar hay tablas con menos filas!" >&2
    echo "$perdidas" >&2
    [[ -n "${ARCHIVO_RESPALDO:-}" ]] && echo "Respaldo para restaurar: $ARCHIVO_RESPALDO" >&2
    return 1
  fi
}

migrar_con_proteccion() {
  echo "-> Revisando migraciones pendientes..."
  php artisan migrate:status --pending --no-interaction || true

  # --pretend muestra el SQL sin ejecutarlo.
  local simulacion
  simulacion="$(php artisan migrate --pretend --force --no-interaction)" || error "No se pudo simular la migración; no se toca la base."

  if grep -qi "nothing to migrate" <<<"$simulacion"; then
    echo "   No hay migraciones pendientes."
    return 0
  fi

  echo "-> SQL que se ejecutará (simulación):"
  echo "$simulacion"

  # En MySQL "dropColumn" se ve como "alter table x drop `columna`" (sin la
  # palabra "column"), por eso se detecta cualquier DROP, además de TRUNCATE y DELETE FROM.
  if grep -Eqi '\b(drop|truncate)\b|\bdelete[[:space:]]+from\b' <<<"$simulacion"; then
    if [[ "${SIAR_PERMITIR_DESTRUCTIVO:-0}" != "1" ]]; then
      error "Hay sentencias que BORRAN datos o estructura (DROP, TRUNCATE o DELETE FROM). Se detiene sin migrar. Si ya las revisaste y son correctas: SIAR_PERMITIR_DESTRUCTIVO=1"
    fi
    echo "   AVISO: sentencias destructivas permitidas por SIAR_PERMITIR_DESTRUCTIVO=1."
  fi

  echo "-> Respaldando la base antes de migrar..."
  ARCHIVO_RESPALDO=""
  respaldar_base

  local antes despues
  antes="$(mktemp)"; despues="$(mktemp)"
  estado_bd conteos >"$antes" || { rm -f "$antes" "$despues"; error "No se pudieron contar las filas antes de migrar; no se migra."; }

  echo "-> Aplicando migraciones pendientes (solo crea o agrega, no borra)..."
  php artisan migrate --force --no-interaction

  echo "-> Verificando que no se perdieron datos..."
  estado_bd conteos >"$despues" || { rm -f "$antes" "$despues"; error "No se pudieron contar las filas después de migrar. Revisa la base a mano."; }
  comparar_conteos "$antes" "$despues" || { rm -f "$antes" "$despues"; exit 1; }
  rm -f "$antes" "$despues"
  echo "   OK: ninguna tabla perdió filas."
}

main() {
  cd "$(dirname "${BASH_SOURCE[0]}")/.."
  RAIZ="$(pwd)"
  ARCHIVO_RESPALDO=""

  echo "-> Verificando el entorno..."
  verificar_entorno

  echo "-> Descargando cambios de GitHub..."
  git pull --ff-only

  cd backend

  # En Hostinger PHP tiene desactivada la función proc_open, y Composer la usa
  # para correr los scripts de Laravel. Por eso se instala con --no-scripts y
  # esos pasos se ejecutan después con artisan.
  echo "-> Instalando dependencias de PHP (sin las de desarrollo)..."
  composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

  echo "-> Limpiando cachés y registrando paquetes de Laravel..."
  php artisan optimize:clear
  php artisan package:discover --ansi

  migrar_con_proteccion

  echo "-> Guardando en caché la configuración, rutas y vistas..."
  php artisan optimize

  echo "Listo: backend actualizado."
}

main "$@"
exit 0
