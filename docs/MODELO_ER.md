# Sistema Integral de Administración para Restaurantes (SIAR)

## Modelo Entidad-Relación (DER) - Módulos 1, 2 y 3

```mermaid
erDiagram
    %% --- MÓDULO 1: USUARIOS Y PERMISOS ---
    ROLES ||--o{ USUARIOS : asigna
    ROLES ||--o{ ROL_PERMISOS : contiene
    PERMISOS ||--o{ ROL_PERMISOS : pertenece
    USUARIOS ||--o{ SESIONES : inicia
    USUARIOS ||--o{ BITACORA_AUDITORIA : genera
    USUARIOS ||--o{ SOLICITUDES_RECUPERACION : solicita

    %% --- MÓDULO 2: MENÚ ---
    CATEGORIAS ||--o{ PRODUCTOS : clasifica

    %% --- MÓDULO 3: MESAS Y RESERVAS ---
    USUARIOS ||--o{ RESERVAS : realiza
    MESAS ||--o{ RESERVAS : asigna

    %% --- TABLAS MÓDULO 1 ---
    USUARIOS {
        int id_usuario PK
        string nombres
        string documento
        string correo
        string contrasena_cifrada
        enum estado
        datetime fecha_creacion
        datetime fecha_actualizacion
        int id_rol FK
    }

    ROLES {
        int id_rol PK
        string nombre_rol
        string descripcion
        boolean es_predefinido
    }

    PERMISOS {
        int id_permiso PK
        string codigo_permiso
        string nombre
        string descripcion
    }

    ROL_PERMISOS {
        int id_rol PK, FK
        int id_permiso PK, FK
    }

    SESIONES {
        int id PK
        string tokenable_type
        int tokenable_id FK "id_usuario"
        string name
        string token "hash SHA-256"
        datetime last_used_at
        datetime expires_at "fecha de expiración"
        datetime created_at "fecha de inicio"
    }

    SOLICITUDES_RECUPERACION {
        string email PK "correo del usuario"
        string token "hash bcrypt"
        datetime created_at "vence a los 60 min"
    }

    BITACORA_AUDITORIA {
        int id_bitacora PK
        string tipo_evento
        string descripcion
        datetime fecha_hora
        string ip_origen
        int id_usuario FK
    }

    %% --- TABLAS MÓDULO 2 ---
    CATEGORIAS {
        int id_categoria PK
        string nombre
        string descripcion
        boolean activa
    }

    PRODUCTOS {
        int id_producto PK
        string nombre
        string descripcion
        decimal precio
        string imagen_url
        boolean disponible
        boolean activo
        datetime fecha_creacion
        datetime fecha_modificacion
        datetime fecha_baja
        int id_categoria FK
    }

    %% --- TABLAS MÓDULO 3 ---
    MESAS {
        int id_mesa PK
        int numero
        int capacidad
        string estado
    }

    RESERVAS {
        int id_reserva PK
        date fecha
        string hora
        int cantidad_personas
        string estado
        int id_usuario FK
        int id_mesa FK
    }
```

## Notas de implementación (Módulo 1)

- Las migraciones están en `backend/database/migrations` y usan los mismos nombres de tablas y columnas de este modelo.
- **SESIONES**: cada fila es un token de Laravel Sanctum (modelo `App\Models\Sesion`). Una sesión está activa mientras su fila exista y `expires_at` no haya pasado (8 horas por defecto). Revocar una sesión es borrar la fila, por eso no se necesita la columna `activa`.
- **SOLICITUDES_RECUPERACION**: la usa el sistema de recuperación de contraseñas de Laravel. El token se guarda cifrado, vence a los 60 minutos y se borra al usarse, así que es de un solo uso (reemplaza a la columna `usado`).
- **BITACORA_AUDITORIA**: si un usuario se borra físicamente, sus registros se conservan con `id_usuario` en `NULL`.
- **USUARIOS.estado**: `activo` o `inactivo`. La eliminación lógica (CU-03) pone el estado en `inactivo`.
