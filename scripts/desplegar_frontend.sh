#!/usr/bin/env bash
# Publica el frontend de SIAR en el servidor a partir de siar-frontend.zip.
# El zip no está en git (frontend/.gitignore): se sube a mano al servidor, por
# ejemplo a siar-app/frontend/. Uso, desde la carpeta del proyecto en el servidor:
#   bash scripts/desplegar_frontend.sh                 # destino por defecto: ../public_html
#   bash scripts/desplegar_frontend.sh /ruta/destino   # otra carpeta pública
#   bash scripts/desplegar_frontend.sh --zip=/ruta/siar-frontend.zip
#   bash scripts/desplegar_frontend.sh -y              # sin pedir confirmación
# Sin --zip, busca el zip en: <proyecto>/frontend, <proyecto>, <destino> y ~.
#
# Qué hace, en este orden (si algo falla, se detiene ANTES de borrar nada):
#   1. Descomprime el zip en una carpeta temporal y comprueba que trae index.html.
#   2. Respalda en un .tar.gz (junto a la carpeta pública, fuera de la web) los
#      archivos y carpetas actuales que el zip va a reemplazar.
#   3. Borra SOLO esas entradas del destino (las que trae el zip: index.html,
#      assets/, icons/, favicon.ico, .htaccess) y mueve las nuevas.
# Todo lo demás del destino (por ejemplo "backend") no se toca; y si el zip trajera
# una entrada protegida (backend, storage, .well-known...), se detiene.
#
# Sin "<(...)" ni /dev/fd (Hostinger no los permite); todo dentro de main().
set -euo pipefail

error() { echo "ERROR: $*" >&2; exit 1; }

# Nombres que el zip nunca debe reemplazar.
PROTEGIDOS=(backend storage .well-known cgi-bin .git .env .htpasswd)

esta_protegido() {
  local nombre="$1" p
  for p in "${PROTEGIDOS[@]}"; do
    [[ "$nombre" == "$p" ]] && return 0
  done
  return 1
}

# Descomprime $1 (zip) en $2 (carpeta) con la herramienta que haya disponible.
descomprimir() {
  local zip="$1" destino="$2"
  if command -v unzip >/dev/null 2>&1; then
    unzip -q -o "$zip" -d "$destino"
  elif command -v python3 >/dev/null 2>&1; then
    python3 -m zipfile -e "$zip" "$destino"
  elif command -v php >/dev/null 2>&1; then
    php -r '$z = new ZipArchive; if ($z->open($argv[1]) !== true || !$z->extractTo($argv[2])) { fwrite(STDERR, "No se pudo extraer\n"); exit(1); }' "$zip" "$destino"
  else
    error "No hay unzip, python3 ni PHP con ZipArchive para descomprimir."
  fi
}

main() {
  local confirmar=1 destino_arg="" zip_arg=""
  local arg
  for arg in "$@"; do
    case "$arg" in
      -y|--si) confirmar=0 ;;
      --zip=*) zip_arg="${arg#--zip=}" ;;
      -*) error "Opción desconocida: $arg" ;;
      *) destino_arg="$arg" ;;
    esac
  done

  cd "$(dirname "${BASH_SOURCE[0]}")/.."
  local raiz zip destino padre
  raiz="$(pwd)"
  destino="${destino_arg:-$raiz/../public_html}"

  [[ -d "$destino" ]] || error "La carpeta de destino no existe: $destino. Pásala como argumento: bash scripts/desplegar_frontend.sh /ruta/public_html"

  destino="$(cd "$destino" && pwd)"
  padre="$(dirname "$destino")"

  # El zip NO viaja por git (está en frontend/.gitignore): se sube a mano. Si no se
  # indica con --zip=, se busca en estos lugares, en este orden.
  zip=""
  if [[ -n "$zip_arg" ]]; then
    zip="$zip_arg"
  else
    local candidato
    for candidato in "$raiz/frontend/siar-frontend.zip" "$raiz/siar-frontend.zip" "$destino/siar-frontend.zip" "$HOME/siar-frontend.zip"; do
      if [[ -f "$candidato" ]]; then zip="$candidato"; break; fi
    done
  fi
  [[ -n "$zip" && -f "$zip" ]] || error "No encontré siar-frontend.zip. Súbelo y vuelve a correr, o indícalo: bash scripts/desplegar_frontend.sh --zip=/ruta/siar-frontend.zip (busqué en: $raiz/frontend, $raiz, $destino y $HOME)."
  echo "   Zip: $zip"

  # Protecciones: nunca el repositorio, la carpeta personal ni la raíz.
  if [[ "$destino" == "/" || "$destino" == "$HOME" || "$destino" == "$raiz" || "$destino" == "$raiz/backend" || "$destino" == "$raiz/frontend" ]]; then
    error "Destino no permitido: $destino"
  fi

  # 1) Descomprimir en una carpeta temporal junto al destino (mismo disco: mover es instantáneo).
  local tmp
  tmp="$(mktemp -d "$padre/.frontend_nuevo.XXXXXX")" || error "No se pudo crear una carpeta temporal en $padre."
  echo "-> Descomprimiendo el zip en una carpeta temporal..."
  descomprimir "$zip" "$tmp" || { rm -rf "$tmp"; error "No se pudo descomprimir el zip; no se tocó el destino."; }

  [[ -f "$tmp/index.html" ]] || { rm -rf "$tmp"; error "El zip no trae index.html en su raíz; no se tocó el destino."; }

  # Entradas de primer nivel que trae el zip (incluye archivos ocultos como .htaccess).
  local -a entradas=()
  local ruta nombre
  for ruta in "$tmp"/* "$tmp"/.[!.]*; do
    [[ -e "$ruta" ]] || continue
    nombre="$(basename "$ruta")"
    if esta_protegido "$nombre"; then
      rm -rf "$tmp"
      error "El zip trae \"$nombre\", que está protegido y no se reemplaza. No se tocó el destino."
    fi
    entradas+=("$nombre")
  done
  [[ ${#entradas[@]} -gt 0 ]] || { rm -rf "$tmp"; error "El zip está vacío."; }

  echo "   Destino: $destino"
  echo "   Se reemplazarán: ${entradas[*]}"
  echo "   Se conserva todo lo demás del destino (por ejemplo: backend)."

  if [[ "$confirmar" == "1" ]]; then
    if [[ ! -t 0 ]]; then
      rm -rf "$tmp"
      error "No hay terminal para confirmar. Vuelve a correr con -y si estás seguro."
    fi
    local respuesta=""
    read -r -p "¿Continuar? [s/N] " respuesta
    if [[ "$respuesta" != "s" && "$respuesta" != "S" ]]; then
      rm -rf "$tmp"
      echo "Cancelado. No se cambió nada."
      return 0
    fi
  fi

  # 2) Respaldo de lo que existe y se va a reemplazar (fuera de la carpeta pública).
  local -a existentes=()
  for nombre in "${entradas[@]}"; do
    [[ -e "$destino/$nombre" ]] && existentes+=("$nombre")
  done

  local respaldo=""
  if [[ ${#existentes[@]} -gt 0 ]]; then
    respaldo="$padre/respaldo_frontend_$(date +%Y%m%d_%H%M%S).tar.gz"
    echo "-> Respaldando lo actual en $respaldo ..."
    tar -czf "$respaldo" -C "$destino" "${existentes[@]}" || { rm -rf "$tmp" "$respaldo"; error "No se pudo hacer el respaldo; no se tocó el destino."; }
    chmod 600 "$respaldo"
    # Se conservan los 5 respaldos más recientes.
    { ls -1t "$padre"/respaldo_frontend_*.tar.gz 2>/dev/null || true; } | tail -n +6 | while read -r viejo; do rm -f "$viejo"; done
  fi

  # 3) Borrar solo lo que trae el zip y poner lo nuevo.
  echo "-> Reemplazando archivos..."
  for nombre in "${entradas[@]}"; do
    rm -rf -- "${destino:?}/$nombre"
    mv -- "$tmp/$nombre" "$destino/$nombre"
  done
  rmdir "$tmp" 2>/dev/null || rm -rf "$tmp"

  # Permisos normales para un sitio web: carpetas 755, archivos 644.
  for nombre in "${entradas[@]}"; do
    if [[ -d "$destino/$nombre" ]]; then
      find "$destino/$nombre" -type d -exec chmod 755 {} +
      find "$destino/$nombre" -type f -exec chmod 644 {} +
    else
      chmod 644 "$destino/$nombre"
    fi
  done

  echo "Listo: frontend publicado en $destino"
  [[ -n "$respaldo" ]] && echo "Respaldo de lo anterior: $respaldo"
  return 0
}

main "$@"
exit 0
