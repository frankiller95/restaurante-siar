# Despliegue de SIAR en Hostinger (Business Web Hosting)

La aplicación queda publicada en **https://siar.frajarele.dev**:

| URL | Qué es |
|---|---|
| `https://siar.frajarele.dev/` | Frontend (Quasar compilado a archivos estáticos) |
| `https://siar.frajarele.dev/backend/api/...` | API de Laravel |

Estructura en el servidor:

```
~/domains/siar.frajarele.dev/
├── siar-app/                ← repositorio clonado de GitHub (NO es público)
│   ├── backend/             ← Laravel (código, .env, vendor)
│   └── frontend/
└── public_html/             ← lo único visible desde internet
    ├── index.html, assets/, .htaccess   ← frontend compilado
    └── backend  →  ../siar-app/backend/public   (enlace simbólico)
```

Solo la carpeta `public` de Laravel queda expuesta. El `.env`, el código y `vendor` quedan fuera de `public_html`.

> En los comandos, cambia `u123456789` por tu usuario de Hostinger y `IP_DEL_SERVIDOR` por la IP que aparece en hPanel.

---

## 1. Crear el sitio del subdominio

1. En hPanel: **Sitios web → Crear sitio web → Sitio web PHP/HTML**.
2. Como dominio escribe `siar.frajarele.dev` y termina el asistente. Si te ofrece instalar algo, elige el sitio vacío.
3. **DNS:** si `frajarele.dev` usa los nameservers de Hostinger, el subdominio se configura solo. Si el dominio está en otro proveedor, crea allá un registro `A` con nombre `siar` y la IP del hosting que muestra hPanel.
4. **SSL:** en el panel del sitio, abre **Seguridad → SSL** y verifica que el certificado esté activo. Los dominios `.dev` **solo abren con HTTPS**, así que hasta que el SSL esté activo el navegador no muestra nada. Puede tardar unos minutos.

## 2. Versión de PHP

En el panel del sitio: **Avanzado → Configuración de PHP** → elige **PHP 8.3** (mínimo 8.2) y guarda.

## 3. Crear la base de datos

En el panel del sitio: **Bases de datos → Administración de bases de datos MySQL**.

- Nombre: `siar` (queda como `u123456789_siar`)
- Usuario: `siar` (queda como `u123456789_siar`)
- Contraseña: una segura. Guárdala para el paso 6.

## 4. Activar SSH y conectarte

1. En el panel del sitio: **Avanzado → Acceso SSH → Habilitar**. Anota la IP, el puerto (`65002`) y el usuario. Si no tienes contraseña de SSH, créala ahí mismo.
2. Desde PowerShell en tu PC:

```bash
ssh -p 65002 u123456789@IP_DEL_SERVIDOR
```

3. Ya en el servidor, verifica las versiones:

```bash
php -v
```

```bash
composer --version
```

Debe salir PHP 8.2 o superior y Composer 2. Si `php -v` muestra otra versión, revisa el paso 2 y vuelve a conectarte.

## 5. Clonar el proyecto

```bash
cd ~/domains/siar.frajarele.dev
```

```bash
git clone https://github.com/frankiller95/restaurante-siar.git siar-app
```

## 6. Configurar Laravel

```bash
cd ~/domains/siar.frajarele.dev/siar-app/backend
```

```bash
composer install --no-dev --optimize-autoloader --no-interaction
```

```bash
cp .env.example .env
```

```bash
nano .env
```

Cambia estas líneas. Las demás déjalas como están.

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://siar.frajarele.dev/backend
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=u123456789_siar
DB_USERNAME=u123456789_siar
DB_PASSWORD=la-contraseña-del-paso-3

FRONTEND_URL=https://siar.frajarele.dev
CORS_ALLOWED_ORIGINS=https://siar.frajarele.dev

SIAR_ADMIN_NOMBRES="Tu nombre"
SIAR_ADMIN_DOCUMENTO=tu-documento
SIAR_ADMIN_CORREO=tu-correo@frajarele.dev
SIAR_ADMIN_CONTRASENA=una-contraseña-segura
```

Guarda con `Ctrl+O` y `Enter`, y sal con `Ctrl+X`.

> **Importante:** `APP_DEBUG=false` en producción. Con `true`, un error muestra datos internos del servidor.

Luego genera la llave de la app, crea las tablas con los datos iniciales y optimiza:

```bash
php artisan key:generate --force
```

```bash
php artisan migrate --seed --force
```

```bash
php artisan optimize
```

## 7. Publicar la API

```bash
cd ~/domains/siar.frajarele.dev/public_html
```

Borra el archivo de bienvenida que crea Hostinger, si existe:

```bash
rm -f default.php index.php
```

Crea el enlace simbólico hacia la carpeta `public` de Laravel:

```bash
ln -s ../siar-app/backend/public backend
```

Prueba en el navegador: `https://siar.frajarele.dev/backend/api/auth/perfil` debe responder `{"message":"Unauthenticated."}`. Eso significa que la API funciona; solo falta iniciar sesión.

## 8. Subir el frontend

En **tu PC** (PowerShell), compila y empaqueta:

```bash
cd C:\xampp\htdocs\restaurante-siar\frontend
```

```bash
npm run build
```

```bash
tar -a -c -f siar-frontend.zip -C dist\spa .
```

Esto crea `frontend\siar-frontend.zip`, con el `.htaccess` incluido.

Súbelo al servidor:

1. hPanel → **Archivos → Administrador de archivos** → entra a `domains/siar.frajarele.dev/public_html`.
2. Sube `siar-frontend.zip`, clic derecho → **Extraer** en esa misma carpeta, y luego borra el `.zip`.
3. **No borres** el enlace `backend`.

## 9. Probar

Abre **https://siar.frajarele.dev** e inicia sesión con el `SIAR_ADMIN_CORREO` y la `SIAR_ADMIN_CONTRASENA` del paso 6. Después cambia la contraseña desde **Mi perfil**.

---

## Opcional: llevar los datos de tu base local

Por defecto, el paso 6 crea la base vacía, con roles, permisos y el administrador. Si en cambio quieres subir los datos de tu MariaDB local:

1. En HeidiSQL: clic derecho en `siar_db` → **Exportar base de datos como SQL**. Marca *Crear* en tablas y *Insertar* en datos, y genera un archivo `.sql`.
2. En hPanel: **Bases de datos → phpMyAdmin** → entra a `u123456789_siar` → **Importar** → sube el `.sql`.
3. En el paso 6 **no ejecutes** `php artisan migrate --seed --force`. La tabla `migrations` viene en el archivo, así que Laravel ya sabe qué tablas existen.

## Opcional: enviar correos de verdad

Con `MAIL_MAILER=log`, los correos (credenciales y recuperación de contraseña) no se envían: quedan en `backend/storage/logs/laravel.log`. Para enviarlos:

1. En hPanel → **Correos**, crea una cuenta, por ejemplo `no-responder@frajarele.dev`.
2. En el `.env` del servidor:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=no-responder@frajarele.dev
MAIL_PASSWORD=contraseña-de-esa-cuenta
MAIL_FROM_ADDRESS="no-responder@frajarele.dev"
MAIL_FROM_NAME="SIAR"
```

3. Ejecuta `php artisan optimize` para que Laravel lea el `.env` nuevo.

> Cada vez que cambies el `.env` en el servidor, ejecuta `php artisan optimize`. La configuración queda guardada en caché.

---

## Actualizar después de nuevos cambios

**Backend:** después de subir cambios a GitHub, entra por SSH y ejecuta:

```bash
cd ~/domains/siar.frajarele.dev/siar-app
```

```bash
bash scripts/desplegar.sh
```

El script descarga los cambios, instala dependencias, aplica migraciones nuevas y optimiza.

**Frontend:** repite el paso 8 (compilar, empaquetar, subir y extraer, reemplazando los archivos).

---

## Problemas comunes

| Síntoma | Causa probable | Solución |
|---|---|---|
| El navegador dice que el sitio no es seguro o no carga | El SSL aún no está activo (`.dev` exige HTTPS) | Espera o actívalo en hPanel → Seguridad → SSL |
| Error 500 en `/backend/...` | Error de Laravel | Revisa `siar-app/backend/storage/logs/laravel.log` |
| "No application encryption key" | Falta la llave | `php artisan key:generate --force` y `php artisan optimize` |
| "Access denied for user" | Datos de la base mal en `.env` | Revisa `DB_*`; el nombre lleva el prefijo `u123456789_` |
| 404 en `/backend/api/...` | Falta el enlace simbólico | Repite el paso 7 |
| Al recargar `/usuarios` sale 404 | No se subió el `.htaccess` del frontend | Repite el paso 8 y verifica que `.htaccess` esté en `public_html` |
| Cambié el `.env` y no pasa nada | Configuración en caché | `php artisan optimize` |
