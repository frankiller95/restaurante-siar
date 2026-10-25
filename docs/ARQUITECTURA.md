# Diseño del Sistema SIAR (Sistema Integral de Administración para Restaurantes)

---

## 1. Arquitectura del Sistema

El sistema **SIAR** utiliza una **Arquitectura en Capas (Layered Architecture / N-Tier Architecture)** basada en el estándar **MVC (Modelo-Vista-Controlador)** desacoplado, combinada con un esquema cliente-servidor API REST.

```mermaid
graph TD
    subgraph Capa1 [Capa 1: Presentación / Cliente]
        UI[Cliente Web - Vue 3 + Quasar]
    end

    subgraph Capa2 [Capa 2: Controladores / API REST]
        M1_Ctrl[UserController / AuthController]
        M2_Ctrl[MenuController]
        M3_Ctrl[TableController / ReservationController]
    end

    subgraph Capa3 [Capa 3: Lógica de Negocio y Servicios]
        M1_Serv[UserService / AuthService]
        M2_Serv[MenuService]
        M3_Serv[TableService / ReservationService]
    end

    subgraph Capa4 [Capa 4: Acceso a Datos y Persistencia]
        Repo[Repositories / ORM]
        BD[(Base de Datos Relacional)]
    end

    UI -->|Peticiones HTTP / JSON| M1_Ctrl
    UI -->|Peticiones HTTP / JSON| M2_Ctrl
    UI -->|Peticiones HTTP / JSON| M3_Ctrl

    M1_Ctrl --> M1_Serv
    M2_Ctrl --> M2_Serv
    M3_Ctrl --> M3_Serv

    M1_Serv --> Repo
    M2_Serv --> Repo
    M3_Serv --> Repo

    Repo --> BD
```

### Descripción de Capas:
1. **Capa de Presentación (Frontend):** Desarrollada con Vue 3 y Quasar Framework (SPA responsiva), encargada de la interfaz gráfica e interacción con el usuario (administradores, cajeros, clientes y meseros).
2. **Capa de Controladores / Endpoints (API REST):** Recibe las solicitudes HTTP/HTTPS desde el cliente, valida los DTOs (Data Transfer Objects) de entrada y expone respuestas estructuradas en JSON.
3. **Capa de Negocio / Servicios (Backend Services):** Contiene la lógica del negocio, validaciones de reglas del restaurante (por ejemplo, disponibilidad de mesas, control de roles, cambio de precios) y coordinación de operaciones.
4. **Capa de Persistencia / Acceso a Datos:** Gestiona la comunicación con la base de datos relacional mediante un ORM/Mapeador, garantizando la integridad de datos y las transacciones.

---

## 2. Patrones de Diseño Seleccionados y Justificación

Para garantizar un código mantenible, escalable y con bajo acoplamiento, se seleccionaron los siguientes patrones de diseño creacionales, estructurales y comportamentales:

---

### A. Patrón Observer (Observador) — *Comportamental*
- **Uso en el sistema:** Módulo de Auditoría y Eventos del Menú/Mesas.
- **Descripción:** Permite que un objeto (Sujeto) notifique automáticamente a otros objetos (Observadores) cuando ocurre un cambio de estado sin acoplar sus clases.
- **Justificación:** Cuando se realiza una modificación sensible en el menú o un usuario cambia de estado, la clase `MenuService` publica el evento `EventoMenu`. Clases como `AuditoriaObserver` escuchan automáticamente y registran el evento en la bitácora sin ensuciar la lógica principal del menú.

```mermaid
graph LR
    Sujeto[MenuService / Sujeto] -->|Publica EventoMenu| Evento[EventoMenu]
    Evento -->|Notifica| Obs1[AuditoriaObserver]
    Evento -->|Notifica| Obs2[NotificacionService]
```

---

### B. Patrón Repository (Repositorio) — *Estructural*
- **Uso en el sistema:** Acceso a datos en todos los módulos (`IProductoRepository`, `IUsuarioRepository`).
- **Descripción:** Media entre la capa de dominio y la capa de mapeo de datos utilizando una interfaz similar a una colección para acceder a los objetos de dominio.
- **Justificación:** Aislar completamente la lógica de negocio de la tecnología de base de datos SQL utilizada. Si en el futuro se cambia el motor de base de datos o el ORM, la capa de servicios (`MenuService`, `UserService`) no sufrirá ningún cambio.

```mermaid
graph TD
    Servicio[MenuService] -->|Depende de Interfaz| Interfaz[IProductoRepository]
    Impl[ProductoRepositoryImpl] -.->|Implementa| Interfaz
    Impl -->|Consultas SQL| BD[(Base de Datos)]
```

---

### C. Patrón DTO (Data Transfer Object) — *Estructural*
- **Uso en el sistema:** Comunicación entre el Frontend y los Controladores REST.
- **Descripción:** Objeto que transporta datos entre procesos para reducir el número de llamadas a métodos o peticiones de red.
- **Justificación:** Evita exponer las entidades completas de la base de datos a la interfaz pública (por ejemplo, evita enviar campos sensibles como contraseñas cifradas o tokens internos al cliente en peticiones GET).

```mermaid
graph LR
    Client[Cliente / Frontend] -->|Envía ProductoDTO| Controller[MenuController]
    Controller -->|Mapea DTO a Entidad| Entity[Entidad Producto]
    Entity -->|Persiste| BD[(Base de Datos)]
```

---

### D. Patrón Singleton — *Creacional*
- **Uso en el sistema:** Conexión a Base de Datos y Configuración Global.
- **Descripción:** Garantiza que una clase tenga una única instancia en toda la aplicación y proporciona un punto de acceso global a ella.
- **Justificación:** Evita crear múltiples instancias innecesarias del pool de conexiones a la base de datos, optimizando el consumo de memoria y recursos en el servidor.

```mermaid
graph TD
    Cliente1[Servicio Usuarios] -->|Solicita Conexión| Singleton{DatabaseConnection}
    Cliente2[Servicio Menú] -->|Solicita Conexión| Singleton
    Cliente3[Servicio Mesas] -->|Solicita Conexión| Singleton
    Singleton -->|Instancia Única| BD[(Base de Datos)]
```

---

### E. Patrón State (Estado) — *Comportamental*
- **Uso en el sistema:** Módulo de Mesas y Reservas.
- **Descripción:** Permite a un objeto alterar su comportamiento cuando su estado interno cambia.
- **Justificación:** Las mesas del restaurante transicionan entre estados (*LIBRE*, *OCUPADA*, *RESERVADA*, *MANTENIMIENTO*). El patrón evita el uso excesivo de estructuras condicionales `if-else` o `switch` complejas en el código, encapsulando las reglas de cada estado en clases independientes.

```mermaid
stateDiagram-v2
    [*] --> Libre
    Libre --> Reservada : Cliente reserva
    Libre --> Ocupada : Cliente ocupa mesa
    Reservada --> Ocupada : Cliente llega
    Ocupada --> Libre : Liberar / Pagar
    Libre --> Mantenimiento : Inhabilitar
    Mantenimiento --> Libre : Habilitar
```

---

## 3. Tecnologías

| Capa | Tecnología |
|---|---|
| Presentación | Vue 3 + Quasar Framework 2 (Quasar CLI con Vite), Pinia, Axios |
| Controladores / API REST | Laravel 12, autenticación por tokens con Laravel Sanctum |
| Lógica de negocio | Servicios de Laravel (`app/Services`) |
| Persistencia | Repositorios sobre Eloquent ORM, MySQL / MariaDB |

---

## 4. Implementación de los patrones (Módulo 1)

| Patrón | Dónde está en el código | Qué hace |
|---|---|---|
| **Observer** | `app/Events/EventoAuditable.php` (evento) y `app/Listeners/AuditoriaObserver.php` (observador) | Los servicios publican `EventoAuditable` (inicio de sesión, registro, edición o eliminación de usuarios, cambios de roles). `AuditoriaObserver` lo escucha y lo guarda en `bitacora_auditoria` sin que los servicios sepan de la bitácora. |
| **Repository** | Interfaces en `app/Contracts/Repositories`, implementaciones en `app/Repositories/Eloquent` | `UsuarioService`, `RolService` y `AuthService` dependen de interfaces como `UsuarioRepositoryInterface`. Las pruebas unitarias (`tests/Unit/UsuarioServiceTest.php`) reemplazan el repositorio por un doble de prueba sin tocar la base de datos. |
| **DTO** | Entrada: `app/DTOs/UsuarioDTO.php`, `app/DTOs/RolDTO.php`. Salida: `app/Http/Resources/*Resource.php` | La entrada validada se convierte en un DTO que normaliza los datos. La salida usa API Resources, que nunca exponen `contrasena_cifrada` ni los tokens. |
| **Singleton** | `app/Providers/RepositoryServiceProvider.php` | Los repositorios y el `ServicioCorreo` se registran con `$singletons` en el contenedor de Laravel: una sola instancia compartida por todos los servicios. La conexión a la base de datos también es un singleton del framework (`db`). |
| **State** | Módulo 3 (Mesas) | Pendiente: se aplicará a los estados de las mesas. |

### Flujo de una petición (ejemplo: CU-01 Registrar usuario)

```mermaid
sequenceDiagram
    participant UI as Quasar (UsuarioFormDialog)
    participant C as UsuarioController
    participant R as GuardarUsuarioRequest
    participant S as UsuarioService
    participant Repo as UsuarioRepository
    participant Mail as ServicioCorreo
    participant Obs as AuditoriaObserver

    UI->>C: POST /api/usuarios (JSON)
    C->>R: valida formato y campos obligatorios
    C->>S: registrar(UsuarioDTO)
    S->>Repo: existeCorreo / existeDocumento
    S->>Repo: crear(datos + contraseña cifrada)
    S-->>Obs: EventoAuditable(usuario_registrado)
    S->>Mail: enviarCredenciales()
    S-->>C: usuario + correo_enviado
    C-->>UI: 201 UsuarioResource
```

### Control de acceso (RNF05)

- Cada rol tiene permisos (`rol_permisos`). El middleware `permiso:<codigo>` (`app/Http/Middleware/VerificarPermiso.php`) protege las rutas.
- El frontend arma el menú con los permisos que devuelve `GET /api/auth/perfil` y el router bloquea las rutas sin permiso.
- Al cambiar el rol o el estado de un usuario, o los permisos de un rol, se revocan las sesiones afectadas para que vuelvan a ingresar con sus permisos vigentes.
