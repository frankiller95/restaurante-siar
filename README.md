# SIAR — Sistema Integral de Administración para Restaurantes

¡Bienvenido al repositorio del proyecto **SIAR**! 🍽️

SIAR es una aplicación web para administrar la operación de un restaurante: usuarios y roles, menú, mesas, pedidos, inventario, facturación, reportes y reservas.

Este proyecto se desarrolla en el marco de la asignatura **Ingeniería de Software II** (UniAJC, 2026).

## 🧱 Stack

| Capa | Tecnología |
|---|---|
| Backend (API REST) | Laravel 12 (PHP 8.2+), Laravel Sanctum |
| Frontend | Vue 3 + Quasar Framework 2 (Quasar CLI con Vite) + Pinia |
| Base de datos | MySQL / MariaDB |
| Pruebas | PHPUnit (SQLite en memoria) |

## 📁 Estructura

```
restaurante-siar/
├── backend/    API REST en Laravel
├── frontend/   Aplicación web en Quasar
└── docs/       Documentación del proyecto
```

## 🚦 Estado de los módulos

| Módulo | Estado |
|---|---|
| 1. Gestión de usuarios (CU-01 a CU-06) | ✅ Implementado y probado |
| 2. Gestión de menú | ⏳ Pendiente |
| 3. Gestión de mesas | ⏳ Pendiente |

## ⚙️ Instalación local

Requisitos: PHP 8.2+, Composer 2, Node.js 22.12+ o 24+, y MySQL o MariaDB.

### 1. Base de datos

Crea la base de datos (desde la consola de MySQL/MariaDB o HeidiSQL):

```sql
CREATE DATABASE siar_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

En `backend/.env` ajusta:

- `DB_USERNAME` y `DB_PASSWORD`: tu usuario de MySQL/MariaDB.
- `SIAR_ADMIN_CORREO` y `SIAR_ADMIN_CONTRASENA`: el administrador inicial.

Luego crea las tablas y los datos iniciales (permisos, los 4 roles del sistema y el administrador):

```bash
php artisan migrate --seed
php artisan serve
```

La API queda en `http://localhost:8000/api`.

> Los correos (credenciales, recuperación de contraseña) se escriben en `backend/storage/logs/laravel.log` mientras `MAIL_MAILER=log`. Para enviarlos de verdad, configura SMTP en el `.env`.

### 3. Frontend

```bash
cd frontend
npm install
cp .env.example .env
npm run dev
```

La aplicación abre en `http://localhost:9000`. Ingresa con el administrador del `.env` del backend.

### 4. Pruebas

```bash
cd backend
php artisan test
```

## 🔌 API del Módulo 1

| Método | Ruta | Caso de uso |
|---|---|---|
| POST | `/api/auth/login` | CU-05 Iniciar sesión |
| POST | `/api/auth/logout` | Cerrar sesión |
| GET | `/api/auth/perfil` | Usuario actual y sus permisos |
| PUT | `/api/auth/contrasena` | Cambiar la contraseña propia |
| POST | `/api/auth/recuperar-contrasena` | CU-06 Solicitar enlace |
| POST | `/api/auth/restablecer-contrasena` | CU-06 Nueva contraseña |
| GET, POST | `/api/usuarios` | Listar y buscar / CU-01 Registrar |
| GET, PUT, DELETE | `/api/usuarios/{id}` | Ver / CU-02 Editar / CU-03 Eliminar |
| POST | `/api/usuarios/{id}/reenviar-credenciales` | CU-01 flujo 7a |
| GET, POST | `/api/roles` | CU-04 Listar / crear rol |
| PUT, DELETE | `/api/roles/{id}` | CU-04 Editar / eliminar rol personalizado |
| PUT | `/api/roles/{id}/permisos` | CU-04 Asignar permisos |
| GET | `/api/permisos` | Catálogo de permisos |
| GET | `/api/bitacora` | Bitácora de auditoría (RNF06) |

Todas las rutas, salvo login y recuperación, requieren el encabezado `Authorization: Bearer <token>`. Las de usuarios, roles y bitácora requieren además el permiso `gestionar_usuarios`.

## 📂 Documentación

- **Documento de Inicio**: [`Documento de Inicio — SIAR.pdf`](docs/Documento%20de%20Inicio%20%E2%80%94%20SIAR.pdf)
- **Arquitectura y patrones**: [`ARQUITECTURA.md`](docs/ARQUITECTURA.md)
- **Modelo entidad-relación**: [`MODELO_ER.md`](docs/MODELO_ER.md)
- **Diagrama de clases (Módulo 1)**: [`Diagrama_de_clases.md`](docs/Diagrama_de_clases.md)
- **Casos de uso**: [`casos de uso/`](docs/casos%20de%20uso/)
