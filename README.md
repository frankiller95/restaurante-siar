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
| 2. Gestión de menú (CU01 a CU05) | ✅ Implementado y probado |
| 3. Gestión de mesas y reservas (RF12 a RF16) | ✅ Implementado y probado |
| 4. Pedidos (RF17 a RF22) | ✅ Implementado y probado |
| 5. Inventario (RF23 a RF27, RF38 a RF40) | ✅ Implementado y probado |

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

## 🔌 API del Módulo 2

| Método | Ruta | Caso de uso | Permiso |
|---|---|---|---|
| GET | `/api/menu` | Carta: categorías activas con sus productos | `consultar_menu`, `gestionar_menu` o `gestionar_disponibilidad` |
| GET | `/api/categorias` | Listar categorías (`?solo_activas=1`) | igual que la carta |
| POST | `/api/categorias` | CU04 Crear categoría | `gestionar_menu` |
| PUT, DELETE | `/api/categorias/{id}` | CU04 Editar o desactivar / eliminar categoría sin productos | `gestionar_menu` |
| GET | `/api/productos` | Listar (`buscar`, `id_categoria`, `disponible`, `estado=activos\|baja\|todos`) | igual que la carta |
| POST | `/api/productos` | CU01 Crear producto (multipart, imagen opcional) | `gestionar_menu` |
| PUT | `/api/productos/{id}` | CU02 Modificar producto (con imagen: `POST` + `_method=PUT`) | `gestionar_menu` |
| DELETE | `/api/productos/{id}` | CU03 Dar de baja (baja lógica) | `gestionar_menu` |
| PATCH | `/api/productos/{id}/categoria` | CU04 Cambiar la categoría | `gestionar_menu` |
| PATCH | `/api/productos/{id}/disponibilidad` | CU05 Disponible / agotado | `gestionar_disponibilidad` (Cocinero y Administrador) |

Si el nombre ya existe en la categoría, `POST`/`PUT` de productos responde `409` con `"codigo": "nombre_duplicado"`. Para guardarlo igual se reenvía con `confirmar_duplicado=1` (CU01 flujo 3a).

## 🔌 API del Módulo 3

| Método | Ruta | Requerimiento | Permiso |
|---|---|---|---|
| GET | `/api/mesas` | RF14 Estado de las mesas + `resumen` por estado (`?estado=`, `?ubicacion=`) | `consultar_mesas`, `gestionar_mesas` o `gestionar_reservas` |
| GET | `/api/mesas/{id}` | RF14 Ver una mesa | igual que la anterior |
| POST | `/api/mesas` | RF12 Crear mesa | `gestionar_mesas` |
| PUT, DELETE | `/api/mesas/{id}` | RF13 Modificar / dar de baja (baja lógica) | `gestionar_mesas` |
| PATCH | `/api/mesas/{id}/mantenimiento`, `/habilitar` | Enviar a mantenimiento / habilitar | `gestionar_mesas` |
| PATCH | `/api/mesas/{id}/ocupar` | Ocupar (cliente sin reserva) | `gestionar_reservas` o `gestionar_mesas` |
| PATCH | `/api/mesas/{id}/liberar` | RF16 Liberar mesa | `gestionar_reservas` o `gestionar_mesas` |
| GET | `/api/mesas/disponibles?fecha=&hora=&personas=` | Mesas sin cruce para una reserva | `gestionar_reservas` o `gestionar_mesas` |
| GET, POST | `/api/reservas` | Listar (`fecha`, `estado`, `buscar`) / RF15 Reservar | `gestionar_reservas` o `gestionar_mesas` |
| PATCH | `/api/reservas/{id}/llegada`, `/cancelar` | Registrar llegada / cancelar | `gestionar_reservas` o `gestionar_mesas` |

Las transiciones de estado inválidas (por ejemplo, liberar una mesa libre) responden `409` con `"codigo": "transicion_invalida"`. Cada mesa trae en `acciones` lo que su estado permite.

## 🔌 API del Módulo 4

| Método | Ruta | Requerimiento | Permiso |
|---|---|---|---|
| GET | `/api/pedidos` | RF22 Consultar (`estado` = `abiertos` o un estado, `id_mesa`, `fecha`, `mios=1`) | `tomar_pedidos`, `preparar_pedidos` o `facturar` |
| GET | `/api/pedidos/{id}` | RF22 Ver un pedido con sus productos y total | igual que la anterior |
| GET | `/api/pedidos/cocina` | RF22 Cola de cocina | `preparar_pedidos` |
| POST | `/api/pedidos` | RF17 Crear pedido (`id_mesa`, `productos` opcional) | `tomar_pedidos` |
| PUT | `/api/pedidos/{id}` | RF19 Observaciones del pedido | `tomar_pedidos` |
| POST | `/api/pedidos/{id}/productos` | RF18 Agregar producto | `tomar_pedidos` |
| PUT, DELETE | `/api/pedidos/{id}/productos/{detalle}` | RF19 Cambiar cantidad o nota / quitar | `tomar_pedidos` |
| PATCH | `/api/pedidos/{id}/enviar-cocina` | RF21 Enviar a cocina | `tomar_pedidos` |
| PATCH | `/api/pedidos/{id}/preparar`, `/listo` | Cocina: iniciar preparación / marcar listo | `preparar_pedidos` |
| PATCH | `/api/pedidos/{id}/entregar` | Entregar en la mesa | `tomar_pedidos` |
| PATCH | `/api/pedidos/{id}/cancelar` | RF20 Cancelar (`motivo`, obligatorio en preparación) | `tomar_pedidos` |

Un pedido solo se modifica mientras está pendiente y no se ha enviado a cocina; si no, responde `409` con `"codigo": "pedido_no_editable"`.

## 🔌 API del Módulo 5

Todas requieren el permiso `gestionar_inventario` (la receta también acepta `gestionar_menu`).

| Método | Ruta | Requerimiento |
|---|---|---|
| GET | `/api/insumos` | RF27 Existencias (`buscar`, `estado=activos\|baja\|todos`, `bajo_minimo=1`) |
| GET | `/api/insumos/alertas` | RF40 Insumos en su stock mínimo o por debajo |
| POST | `/api/insumos` | RF23 Registrar insumo (`stock_inicial` opcional, queda en el kárdex) |
| GET, PUT, DELETE | `/api/insumos/{id}` | Ver / RF24 Modificar / RF24 Desactivar (baja lógica si tiene historial) |
| POST | `/api/insumos/{id}/entradas` | RF25 Entrada (`cantidad`, `costo_unitario`, `proveedor`, `fecha`) |
| POST | `/api/insumos/{id}/mermas` | RF26 Merma (`cantidad`, `motivo`) |
| POST | `/api/insumos/{id}/ajustes` | RF26 Ajuste por conteo (`stock_contado`, `motivo`) |
| GET | `/api/insumos/{id}/movimientos` | RF27 Kárdex (`tipo`, `desde`, `hasta`) |
| GET, PUT | `/api/productos/{id}/receta` | RF38 Receta del producto (`insumos: [{id_insumo, cantidad_por_porcion}]`) |

RF39 y RF40 no tienen rutas propias: el inventario escucha los eventos del Módulo 4 (`PedidoEnviadoACocina`, `PedidoCancelado`) y sus propios eventos (`StockActualizado`, `StockBajo`). Se pueden ver con `php artisan event:list`.

## 📂 Documentación

- **Documento de Inicio**: [`Documento de Inicio — SIAR.pdf`](docs/Documento%20de%20Inicio%20%E2%80%94%20SIAR.pdf)
- **Despliegue en Hostinger** (https://siar.frajarele.dev): [`DESPLIEGUE.md`](docs/DESPLIEGUE.md)
- **Arquitectura y patrones**: [`ARQUITECTURA.md`](docs/ARQUITECTURA.md)
- **Modelo entidad-relación**: [`MODELO_ER.md`](docs/MODELO_ER.md)
- **Diagrama de clases (Módulo 1)**: [`Diagrama_de_clases.md`](docs/Diagrama_de_clases.md)
- **Casos de uso**: [`casos de uso/`](docs/casos%20de%20uso/)
