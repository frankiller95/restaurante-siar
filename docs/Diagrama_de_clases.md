# Diagrama de Clases UML - SIAR

## Módulo 1: Gestión de Usuarios

```mermaid
classDiagram
    class Usuario {
        - int idUsuario
        - String nombres
        - String documento
        - String correo
        - String contrasenaCifrada
        - EstadoUsuario estado
        - DateTime fechaCreacion
        + autenticar(String contrasena) bool
        + actualizarDatos(String nombres, String correo) void
        + cambiarEstado(EstadoUsuario nuevoEstado) void
        + cambiarContrasena(String nuevaContrasena) void
    }

    class Rol {
        - int idRol
        - String nombreRol
        - String descripcion
        - bool esPredefinido
        + asignarPermiso(Permiso permiso) void
        + removerPermiso(Permiso permiso) void
        + esEliminable() bool
    }

    class Permiso {
        - int idPermiso
        - String codigoPermiso
        - String nombre
        - String descripcion
    }

    class EstadoUsuario {
        <<enumeration>>
        ACTIVO
        INACTIVO
    }

    class Sesion {
        - String tokenSesion
        - DateTime fechaInicio
        - DateTime fechaExpiracion
        - bool activa
        + revocar() void
        + esValida() bool
    }

    class SolicitudRecuperacion {
        - String tokenRecuperacion
        - DateTime fechaCreacion
        - DateTime fechaExpiracion
        - bool usado
        + esValido() bool
        + marcarComoUsado() void
    }

    class BitacoraAuditoria {
        - int idBitacora
        - String tipoEvento
        - String descripcion
        - DateTime fechaHora
        - String ipOrigen
        + registrarEvento(Usuario usuario, String accion, String detalle) void
    }

    class ServicioCorreo {
        <<interface>>
        + enviarCredenciales(String correo, String usuario, String contrasena) bool
        + enviarNotificacionCambio(String correo, String mensaje) bool
        + enviarEnlaceRecuperacion(String correo, String enlace) bool
    }

    %% Relaciones
    Usuario "1" --> "1" EstadoUsuario : tiene
    Usuario "*" --> "1" Rol : pertenece a
    Rol "*" o-- "*" Permiso : contiene
    Usuario "1" --> "*" Sesion : posee
    Usuario "1" --> "*" SolicitudRecuperacion : solicita
    Usuario "1" --> "*" BitacoraAuditoria : genera
    Usuario ..> ServicioCorreo : utiliza
```

---

## Módulo 2: Gestión de Menú

Requerimientos: RF07 a RF11 (casos de uso CU01 a CU05 en `docs/casos de uso/CasoUso_Modulo2_GestionMenu.pdf`).

```mermaid
classDiagram
    class Categoria {
        - int idCategoria
        - String nombre
        - String descripcion
        - bool activa
        + activar() void
        + desactivar() void
        + tieneProductos() bool
    }

    class Producto {
        - int idProducto
        - String nombre
        - String descripcion
        - decimal precio
        - String imagenUrl
        - bool disponible
        - bool activo
        - DateTime fechaCreacion
        - DateTime fechaModificacion
        - DateTime fechaBaja
        + actualizarDatos(ProductoDTO datos) void
        + cambiarCategoria(Categoria categoria) void
        + marcarDisponible(bool disponible) void
        + darDeBaja() void
        + estaEnMenu() bool
    }

    class TieneBajaLogica {
        <<trait>>
        + darDeBaja() void
        + estaActivo() bool
        + scopeActivos(Query consulta) Query
    }

    class GestionaImagenes {
        <<trait>>
        + guardarImagen(File archivo, String carpeta) String
        + eliminarImagen(String ruta) void
    }

    class ProductoDTO {
        <<DTO>>
        + String nombre
        + String descripcion
        + decimal precio
        + int idCategoria
        + bool confirmarDuplicado
    }

    class ProductoService {
        + listar(Map filtros) List~Producto~
        + crear(ProductoDTO datos, File imagen) Producto
        + actualizar(Producto producto, ProductoDTO datos, File imagen) Producto
        + eliminar(Producto producto) void
        + cambiarCategoria(Producto producto, Categoria categoria) Producto
        + cambiarDisponibilidad(Producto producto, bool disponible) Producto
        + menuDisponible() List~Categoria~
    }

    class CategoriaService {
        + listar(bool soloActivas) List~Categoria~
        + crear(String nombre, String descripcion) Categoria
        + actualizar(Categoria categoria, String nombre, String descripcion, bool activa) Categoria
        + eliminar(Categoria categoria) void
    }

    class ProductoRepositoryInterface {
        <<interface>>
        + buscar(Map filtros) List~Producto~
        + existeNombreEnCategoria(String nombre, int idCategoria, int exceptoId) bool
        + tienePedidosPendientes(Producto producto) bool
        + crear(Map datos) Producto
        + actualizar(Producto producto, Map datos) Producto
    }

    class CategoriaRepositoryInterface {
        <<interface>>
        + todas(bool soloActivas) List~Categoria~
        + existeNombre(String nombre, int exceptoId) bool
        + crear(Map datos) Categoria
        + actualizar(Categoria categoria, Map datos) Categoria
        + eliminar(Categoria categoria) void
    }

    class EventoAuditable {
        <<event>>
        + TipoEvento tipo
        + String descripcion
    }

    class AuditoriaObserver {
        <<observer>>
        + handle(EventoAuditable evento) void
    }

    class DetallePedido {
        <<Módulo 4>>
        - int cantidad
        - decimal precioUnitario
    }

    class DetalleReceta {
        <<Módulo 5>>
        - decimal cantidadPorPorcion
    }

    %% Relaciones del menú
    Categoria "1" o-- "*" Producto : agrupa
    Producto ..|> TieneBajaLogica : usa
    ProductoService ..|> GestionaImagenes : usa
    ProductoService ..> ProductoDTO : recibe
    ProductoService ..> ProductoRepositoryInterface : usa (Repository)
    ProductoService ..> CategoriaRepositoryInterface : usa (Repository)
    CategoriaService ..> CategoriaRepositoryInterface : usa (Repository)
    ProductoService ..> EventoAuditable : publica
    CategoriaService ..> EventoAuditable : publica
    AuditoriaObserver ..> EventoAuditable : escucha

    %% Conexión con otros módulos
    DetallePedido "*" --> "1" Producto : pide
    Producto "1" *-- "*" DetalleReceta : receta
```

### Reglas de negocio del Módulo 2

| Caso de uso | Regla | Dónde se aplica |
|---|---|---|
| CU01 Crear producto | El precio es obligatorio y no puede ser negativo. El producto queda **disponible** por defecto. | `GuardarProductoRequest`, `ProductoService.crear()` |
| CU01 flujo 3a | Si ya existe un producto activo con el mismo nombre en la categoría, el sistema advierte y solo lo guarda si se confirma (`confirmarDuplicado`). | `ProductoService` + `ProductoRepository.existeNombreEnCategoria()` |
| CU02 Modificar | Se actualiza `fechaModificacion`. Cambiar el precio no afecta pedidos existentes: cada `DetallePedido` guarda su propio `precioUnitario` (Módulo 4). | `ProductoService.actualizar()` |
| CU03 Eliminar | Baja lógica: `activo = false` y `fechaBaja`. Se conserva para el historial y RF34. Si tiene pedidos pendientes o en preparación, se impide y se sugiere marcarlo como no disponible. | Trait `TieneBajaLogica`, `ProductoRepository.tienePedidosPendientes()` |
| CU04 Categorizar | El producto se asigna a una categoría existente, que se puede crear desde el mismo formulario. Una categoría con productos no se elimina, solo se desactiva. | `CategoriaService`, `ProductoService.cambiarCategoria()` |
| CU05 Disponibilidad | El Cocinero (o el Administrador) marca el producto como disponible o agotado. No cancela pedidos en preparación; solo impide pedirlo de nuevo. | `ProductoService.cambiarDisponibilidad()`, permiso `gestionar_disponibilidad` |

---

## Módulo 3: Gestión de Mesas

Requerimientos: RF12 a RF16 (casos de uso en `docs/casos de uso/Casos_de_uso_SIAR_Modulo_3_GestionMesas.pdf`). El estado de la mesa usa el **patrón State** descrito en `docs/ARQUITECTURA.md`.

```mermaid
classDiagram
    class Mesa {
        - int idMesa
        - int numero
        - int capacidad
        - String ubicacion
        - EstadoMesa estado
        - bool activo
        - DateTime fechaBaja
        + estadoActual() ComportamientoMesa
        + ocupar() void
        + reservar() void
        + liberar() void
        + enviarAMantenimiento() void
        + habilitar() void
        + admitePersonas(int cantidad) bool
    }

    class EstadoMesa {
        <<enumeration>>
        LIBRE
        OCUPADA
        RESERVADA
        MANTENIMIENTO
        + comportamiento() ComportamientoMesa
    }

    class ComportamientoMesa {
        <<interface>>
        + ocupar(Mesa mesa) EstadoMesa
        + reservar(Mesa mesa) EstadoMesa
        + liberar(Mesa mesa) EstadoMesa
        + enviarAMantenimiento(Mesa mesa) EstadoMesa
        + habilitar(Mesa mesa) EstadoMesa
    }

    class EstadoBase {
        <<abstract>>
        + ocupar(Mesa mesa) EstadoMesa
        + reservar(Mesa mesa) EstadoMesa
        + liberar(Mesa mesa) EstadoMesa
        + enviarAMantenimiento(Mesa mesa) EstadoMesa
        + habilitar(Mesa mesa) EstadoMesa
        # transicionInvalida(String accion) TransicionMesaException
    }

    class MesaLibre {
        + ocupar(Mesa mesa) EstadoMesa
        + reservar(Mesa mesa) EstadoMesa
        + enviarAMantenimiento(Mesa mesa) EstadoMesa
    }

    class MesaReservada {
        + ocupar(Mesa mesa) EstadoMesa
        + liberar(Mesa mesa) EstadoMesa
    }

    class MesaOcupada {
        + liberar(Mesa mesa) EstadoMesa
    }

    class MesaEnMantenimiento {
        + habilitar(Mesa mesa) EstadoMesa
    }

    class Reserva {
        - int idReserva
        - Date fecha
        - Time hora
        - int cantidadPersonas
        - EstadoReserva estado
        - String clienteNombre
        - String clienteTelefono
        - String observaciones
        + inicio() DateTime
        + fin() DateTime
        + estaPendiente() bool
        + cancelar() void
        + marcarCumplida() void
        + marcarNoAsistio() void
    }

    class EstadoReserva {
        <<enumeration>>
        PENDIENTE
        CUMPLIDA
        CANCELADA
        NO_ASISTIO
    }

    class Usuario {
        <<Módulo 1>>
        - int idUsuario
        - String nombres
    }

    class TieneBajaLogica {
        <<trait>>
        + darDeBaja() void
        + scopeActivos(Query consulta) Query
    }

    class MesaService {
        + listar(Map filtros) List~Mesa~
        + crear(MesaDTO datos) Mesa
        + actualizar(Mesa mesa, MesaDTO datos) Mesa
        + eliminar(Mesa mesa) void
        + ocupar(Mesa mesa) Mesa
        + liberar(Mesa mesa) Mesa
        + enviarAMantenimiento(Mesa mesa) Mesa
        + habilitar(Mesa mesa) Mesa
        + disponibles(DateTime inicio, int personas) List~Mesa~
    }

    class ReservaService {
        + listar(Map filtros) List~Reserva~
        + reservar(ReservaDTO datos, Usuario registradaPor) Reserva
        + cancelar(Reserva reserva) Reserva
        + registrarLlegada(Reserva reserva) Reserva
        + sincronizar() void
    }

    class MesaRepositoryInterface {
        <<interface>>
        + buscar(Map filtros) List~Mesa~
        + existeNumero(int numero, int exceptoId) bool
        + tienePedidosAbiertos(Mesa mesa) bool
    }

    class ReservaRepositoryInterface {
        <<interface>>
        + hayCruce(int idMesa, DateTime inicio, int exceptoId) bool
        + pendientesQueEmpiezanAntesDe(DateTime limite) List~Reserva~
        + vencidas(DateTime limite) List~Reserva~
    }

    class TransicionMesaException {
        <<exception>>
    }

    %% Patrón State
    Mesa "*" --> "1" EstadoMesa : estado
    EstadoMesa ..> ComportamientoMesa : crea
    ComportamientoMesa <|.. EstadoBase
    EstadoBase <|-- MesaLibre
    EstadoBase <|-- MesaReservada
    EstadoBase <|-- MesaOcupada
    EstadoBase <|-- MesaEnMantenimiento
    Mesa ..> ComportamientoMesa : delega la transición
    EstadoBase ..> TransicionMesaException : lanza
    Mesa ..|> TieneBajaLogica : usa

    %% Reservas
    Mesa "1" --> "*" Reserva : recibe
    Reserva "*" --> "1" EstadoReserva : estado
    Reserva "*" --> "1" Usuario : registrada por

    %% Capas
    MesaService ..> MesaRepositoryInterface : usa (Repository)
    ReservaService ..> ReservaRepositoryInterface : usa (Repository)
    ReservaService ..> MesaService : reserva / libera la mesa
```

### Estados de la mesa (patrón State)

```mermaid
stateDiagram-v2
    [*] --> Libre
    Libre --> Reservada : falta 1 h para una reserva
    Libre --> Ocupada : ocupar (llega un cliente)
    Reservada --> Ocupada : registrar llegada
    Reservada --> Libre : reserva cancelada o no asistió
    Ocupada --> Libre : liberar (RF16)
    Libre --> Mantenimiento : enviar a mantenimiento
    Mantenimiento --> Libre : habilitar
```

Cada estado es una clase (`MesaLibre`, `MesaOcupada`, `MesaReservada`, `MesaEnMantenimiento`) que solo implementa las transiciones que le corresponden. Las demás heredan de `EstadoBase`, que lanza `TransicionMesaException`. Así no hay cadenas de `if/switch` sobre el estado: por ejemplo, liberar una mesa que ya está libre falla en la clase `MesaLibre`.

### Reglas de negocio del Módulo 3

| RF | Regla | Dónde se aplica |
|---|---|---|
| RF12 Crear mesas | El número de mesa es único y la capacidad va de 1 a 30 personas. La mesa nace **Libre**. | `GuardarMesaRequest`, `MesaService.crear()` |
| RF13 Modificar mesas | Se pueden cambiar el número, la capacidad y la ubicación en cualquier estado. La capacidad no puede quedar por debajo de una reserva pendiente. Dar de baja una mesa es una baja lógica, y solo si está libre o en mantenimiento y no tiene reservas pendientes. | `MesaService.actualizar()`, `MesaService.eliminar()` |
| RF14 Consultar estado | Lista de mesas con su estado, la próxima reserva y un resumen por estado. Antes de consultar se sincronizan las reservas. | `MesaService.listar()`, `ReservaService.sincronizar()` |
| RF15 Reservar mesas | La reserva la registra el personal a nombre del cliente. La fecha y hora deben ser futuras, la mesa debe tener capacidad suficiente y no estar en mantenimiento, y no puede cruzarse con otra reserva de la misma mesa (duración de 2 h). Cuando falta 1 h, la mesa pasa a **Reservada**; si el cliente no llega 20 min después de la hora, la reserva queda como **no asistió** y la mesa se libera. | `ReservaService`, `ReservaRepository.hayCruce()` |
| RF16 Liberar mesas | Una mesa ocupada vuelve a **Libre**. No se libera si tiene pedidos abiertos (Módulo 4). | Estado `MesaOcupada`, `MesaRepository.tienePedidosAbiertos()` |

> Las horas de las reservas se interpretan en la zona horaria del restaurante (`America/Bogota`, configurable en `config/siar.php`).

---

## Módulo 4: Pedidos

Requerimientos: RF17 a RF22 (ver [Proyecto_de_semestre.md](Proyecto_de_semestre.md)).

```mermaid
classDiagram
    class Pedido {
        - int idPedido
        - EstadoPedido estado
        - String observaciones
        - String motivoCancelacion
        - DateTime fechaEnvioCocina
        - DateTime fechaInicioPreparacion
        - DateTime fechaListo
        - DateTime fechaEntrega
        - DateTime fechaCancelacion
        + total() decimal
        + esEditable() bool
        + fueEnviadoACocina() bool
        + enviarACocina() void
        + iniciarPreparacion() void
        + marcarListo() void
        + entregar() void
        + marcarFacturado() void
        + cancelar(String motivo) void
    }

    class EstadoPedido {
        <<enumeration>>
        PENDIENTE
        EN_PREPARACION
        LISTO
        ENTREGADO
        FACTURADO
        CANCELADO
        + transicionesPermitidas() List~EstadoPedido~
        + puedePasarA(EstadoPedido destino) bool
        + esAbierto() bool
    }

    class DetallePedido {
        - int idDetalle
        - int cantidad
        - decimal precioUnitario
        - String observacion
        + subtotal() decimal
    }

    class Producto {
        <<Módulo 2>>
        - int idProducto
        - String nombre
        - decimal precio
        - bool disponible
        + estaEnMenu() bool
    }

    class Mesa {
        <<Módulo 3>>
        - int idMesa
        - int numero
        - EstadoMesa estado
        + ocupar() void
    }

    class Usuario {
        <<Módulo 1>>
        - int idUsuario
        - String nombres
    }

    class PedidoDTO {
        <<DTO>>
        + int idMesa
        + String observaciones
        + List~LineaPedidoDTO~ productos
    }

    class LineaPedidoDTO {
        <<DTO>>
        + int idProducto
        + int cantidad
        + String observacion
    }

    class PedidoService {
        + listar(Map filtros) List~Pedido~
        + colaDeCocina() List~Pedido~
        + crear(PedidoDTO datos, Usuario mesero) Pedido
        + agregarProducto(Pedido pedido, LineaPedidoDTO linea) Pedido
        + modificarProducto(DetallePedido detalle, int cantidad, String observacion) Pedido
        + quitarProducto(DetallePedido detalle) Pedido
        + actualizar(Pedido pedido, String observaciones) Pedido
        + enviarACocina(Pedido pedido) Pedido
        + iniciarPreparacion(Pedido pedido) Pedido
        + marcarListo(Pedido pedido) Pedido
        + entregar(Pedido pedido) Pedido
        + cancelar(Pedido pedido, String motivo) Pedido
    }

    class PedidoRepositoryInterface {
        <<interface>>
        + buscar(Map filtros) List~Pedido~
        + colaDeCocina() List~Pedido~
        + crear(Map datos) Pedido
        + agregarLinea(Pedido pedido, Map datos) DetallePedido
    }

    class TransicionPedidoException {
        <<exception>>
    }

    class PedidoEnviadoACocina {
        <<event>>
        + Pedido pedido
    }

    class PedidoCancelado {
        <<event>>
        + Pedido pedido
        + bool estabaEnPreparacion
    }

    class EventoAuditable {
        <<event>>
    }

    %% Relaciones
    Pedido "1" *-- "1..*" DetallePedido : contiene
    DetallePedido "*" --> "1" Producto : pide
    Pedido "*" --> "1" Mesa : se sirve en
    Pedido "*" --> "1" Usuario : tomado por
    Pedido "*" --> "1" EstadoPedido : estado
    EstadoPedido ..> TransicionPedidoException : valida transiciones

    %% Capas y patrones
    PedidoService ..> PedidoDTO : recibe
    PedidoDTO "1" *-- "*" LineaPedidoDTO
    PedidoService ..> PedidoRepositoryInterface : usa (Repository)
    PedidoService ..> Mesa : ocupa si está libre (State)
    PedidoService ..> PedidoEnviadoACocina : publica (Observer)
    PedidoService ..> PedidoCancelado : publica (Observer)
    PedidoService ..> EventoAuditable : publica
```

### Estados del pedido

```mermaid
stateDiagram-v2
    [*] --> Pendiente : crear pedido (RF17)
    Pendiente --> Pendiente : agregar o modificar productos (RF18, RF19)
    Pendiente --> Pendiente : enviar a cocina (RF21)
    Pendiente --> EnPreparacion : cocinero inicia preparación
    EnPreparacion --> Listo : cocinero marca listo
    Listo --> Entregado : mesero entrega
    Entregado --> Facturado : factura (Módulo 6)
    Pendiente --> Cancelado : cancelar (RF20)
    EnPreparacion --> Cancelado : cancelar con motivo (RF20)
```

"Pendiente" tiene dos momentos: mientras el mesero arma el pedido (`fechaEnvioCocina` vacía) y cuando ya está en la cola de cocina esperando al cocinero. Así el inventario (RF39) descuenta insumos al **enviar a cocina** y los reintegra si se cancela antes de "En preparación".

### Reglas de negocio del Módulo 4

| RF | Regla | Dónde se aplica |
|---|---|---|
| RF17 Crear pedido | El pedido se toma para una mesa activa. Si está **libre**, se ocupa sola (patrón State); si está reservada o en mantenimiento, no se permite. Una mesa puede tener varios pedidos (rondas). | `PedidoService.crear()` |
| RF18 Agregar productos | Solo productos activos, **disponibles** y de categorías activas. Cada línea guarda el **precio unitario** del momento (un cambio de precio no es retroactivo, CU02). El mismo producto con la misma observación suma cantidad. | `PedidoService.agregarProducto()` |
| RF19 Modificar pedido | Solo mientras está **Pendiente y no se ha enviado a cocina**: cambiar cantidades u observaciones y quitar productos. Una vez enviado (el inventario ya descontó insumos), se cancela o se toma un pedido nuevo para la misma mesa. | `Pedido.esEditable()` |
| RF20 Cancelar pedido | Se puede cancelar en **Pendiente** o **En preparación**. En preparación el motivo es obligatorio. Publica `PedidoCancelado` para que el inventario reintegre o registre merma (RF39). | `PedidoService.cancelar()` |
| RF21 Enviar a cocina | El pedido debe tener al menos un producto y todos deben seguir disponibles. Publica `PedidoEnviadoACocina` (el inventario descontará insumos, RF39). | `PedidoService.enviarACocina()` |
| RF22 Consultar estado | Lista con filtros por estado, mesa y fecha, y una **cola de cocina** con los pedidos enviados, en preparación y listos. | `PedidoService.listar()`, `colaDeCocina()` |

Permisos: `tomar_pedidos` (Mesero) toma, modifica, envía, cancela y entrega; `preparar_pedidos` (Cocinero) inicia la preparación y marca listo; `facturar` (Cajero) consulta. El Administrador tiene todos.

---

## Módulo 5: Inventario

Requerimientos: RF23 a RF27 y RF38 a RF40 (ver [Proyecto_de_semestre.md](Proyecto_de_semestre.md)).

Las clases `Producto`, `Pedido`, `DetallePedido` y `Usuario` pertenecen a los módulos 2, 4 y 1. Aquí solo aparecen los atributos y métodos que usa el inventario.

```mermaid
classDiagram
    class Insumo {
        - int idInsumo
        - String nombre
        - UnidadMedida unidadMedida
        - decimal stockActual
        - decimal stockMinimo
        - decimal costoUnitario
        - bool activo
        + aumentarStock(decimal cantidad) void
        + disminuirStock(decimal cantidad) void
        + estaBajoMinimo() bool
        + alcanzaPara(decimal cantidad) bool
        + desactivar() void
    }

    class UnidadMedida {
        <<enumeration>>
        KILOGRAMO
        GRAMO
        LITRO
        MILILITRO
        UNIDAD
    }

    class MovimientoInventario {
        - int idMovimiento
        - TipoMovimiento tipo
        - decimal cantidad
        - decimal costoUnitario
        - decimal stockResultante
        - String motivo
        - String proveedor
        - DateTime fecha
        + esEntrada() bool
        + calcularCostoTotal() decimal
    }

    class TipoMovimiento {
        <<enumeration>>
        ENTRADA
        CONSUMO_PEDIDO
        REINTEGRO_PEDIDO
        MERMA
        AJUSTE
    }

    class DetalleReceta {
        - decimal cantidadPorPorcion
        + cantidadPara(int porciones) decimal
    }

    class Producto {
        <<Módulo 2>>
        - int idProducto
        - String nombre
        - bool disponible
        - bool agotadoPorInventario
        + marcarDisponible(bool disponible) void
        + insumosNecesarios(int porciones) List~DetalleReceta~
    }

    class Pedido {
        <<Módulo 4>>
        - int idPedido
        - EstadoPedido estado
        + enviarACocina() void
        + cancelar() void
    }

    class DetallePedido {
        <<Módulo 4>>
        - int cantidad
    }

    class Usuario {
        <<Módulo 1>>
        - int idUsuario
        - String nombres
    }

    class InventarioService {
        + registrarInsumo(InsumoDTO datos) Insumo
        + actualizarInsumo(Insumo insumo, InsumoDTO datos) Insumo
        + eliminarInsumo(Insumo insumo) String
        + registrarEntrada(Insumo insumo, decimal cantidad, decimal costo, String proveedor) MovimientoInventario
        + registrarMerma(Insumo insumo, decimal cantidad, String motivo) MovimientoInventario
        + ajustarConteo(Insumo insumo, decimal stockContado, String motivo) MovimientoInventario
        + descontarPorPedido(Pedido pedido) void
        + reintegrarPorPedido(Pedido pedido, bool comoMerma) void
        + consultarKardex(Insumo insumo, Date desde, Date hasta) List~MovimientoInventario~
        + insumosBajoMinimo() List~Insumo~
    }

    class RecetaService {
        + receta(Producto producto) List~DetalleReceta~
        + guardar(Producto producto, List lineas) List~DetalleReceta~
    }

    class DisponibilidadMenuService {
        + actualizar(List idsInsumo) void
    }

    class InsumoRepositoryInterface {
        <<interface>>
        + obtenerPorId(int idInsumo) Insumo
        + guardar(Insumo insumo) Insumo
        + registrarMovimiento(MovimientoInventario movimiento) MovimientoInventario
        + bajoMinimo() List~Insumo~
    }

    class PedidoEnviadoACocina {
        <<event>>
        + Pedido pedido
    }

    class PedidoCancelado {
        <<event>>
        + Pedido pedido
        + bool estabaEnPreparacion
    }

    class StockBajo {
        <<event>>
        + Insumo insumo
    }

    class StockActualizado {
        <<event>>
        + List idsInsumo
    }

    class InventarioObserver {
        <<observer>>
        + handlePedidoEnviadoACocina(PedidoEnviadoACocina evento) void
        + handlePedidoCancelado(PedidoCancelado evento) void
    }

    class DisponibilidadMenuObserver {
        <<observer>>
        + handle(StockActualizado evento) void
    }

    class AlertaStockObserver {
        <<observer>>
        + handle(StockBajo evento) void
    }

    %% Relaciones del inventario
    Insumo "1" --> "1" UnidadMedida : se mide en
    Insumo "1" *-- "*" MovimientoInventario : historial (kárdex)
    MovimientoInventario "*" --> "1" TipoMovimiento : es de tipo
    MovimientoInventario "*" --> "1" Usuario : registrado por
    MovimientoInventario "*" --> "0..1" Pedido : originado por

    %% Integración con Menú (receta) y Pedidos
    Producto "1" *-- "*" DetalleReceta : receta
    DetalleReceta "*" --> "1" Insumo : usa
    Pedido "1" *-- "*" DetallePedido : contiene
    DetallePedido "*" --> "1" Producto : pide

    %% Capas y patrones
    InventarioService ..> InsumoRepositoryInterface : usa (Repository)
    InventarioService ..> Producto : consulta receta
    InventarioService ..> StockBajo : publica
    InventarioService ..> StockActualizado : publica
    RecetaService ..> DetalleReceta : guarda
    RecetaService ..> StockActualizado : publica

    Pedido ..> PedidoEnviadoACocina : publica
    Pedido ..> PedidoCancelado : publica
    InventarioObserver ..> PedidoEnviadoACocina : escucha
    InventarioObserver ..> PedidoCancelado : escucha
    InventarioObserver ..> InventarioService : descuenta / reintegra
    DisponibilidadMenuObserver ..> StockActualizado : escucha
    DisponibilidadMenuObserver ..> DisponibilidadMenuService : usa
    DisponibilidadMenuService ..> Producto : agota o reactiva
    AlertaStockObserver ..> StockBajo : escucha
```

### Cómo se conecta el inventario con los otros módulos

| Requerimiento | Clases involucradas | Qué pasa |
|---|---|---|
| RF23, RF24 | `Insumo`, `InventarioService` | Se crean, modifican y desactivan insumos. Un insumo con historial se desactiva en vez de borrarse. |
| RF25, RF26 | `MovimientoInventario`, `TipoMovimiento` | Cada entrada, merma o ajuste queda como un movimiento con su usuario, motivo y stock resultante. |
| RF27 | `InventarioService.consultarKardex()` | El kárdex es la lista de movimientos de un insumo en un rango de fechas. |
| RF38 | `Producto`, `DetalleReceta`, `Insumo` | La receta dice cuánto de cada insumo lleva una porción del producto. |
| RF39 | `PedidoEnviadoACocina`, `PedidoCancelado`, `InventarioObserver` | Al enviar un pedido a cocina se descuentan los insumos (`CONSUMO_PEDIDO`). Si se cancela antes de prepararse, se reintegran (`REINTEGRO_PEDIDO`); si ya estaba en preparación, se registra como `MERMA`. |
| RF26 | `InventarioService.registrarMerma()`, `ajustarConteo()` | La merma descuenta una cantidad (no puede superar el stock). El ajuste por conteo físico recibe el stock contado y registra la diferencia (positiva o negativa). Ambos exigen motivo. |
| RF40 | `StockBajo`, `StockActualizado`, `AlertaStockObserver`, `DisponibilidadMenuObserver` | Cuando un insumo llega a su mínimo se registra una alerta en la bitácora y aparece en la lista de alertas. Cada cambio de stock publica `StockActualizado`: los productos cuya receta ya no alcanza para una porción se marcan **no disponibles** (`agotadoPorInventario = true`), y cuando se repone el insumo vuelven a estar disponibles. Solo se reactivan los que agotó el inventario, no los que el cocinero marcó a mano (CU05). |
| RF35 | `MovimientoInventario` | El reporte de consumo de inventario se calcula a partir de los movimientos de tipo `CONSUMO_PEDIDO` y `MERMA`. |

### Patrones aplicados en este módulo

- **Observer:** `Pedido` publica `PedidoEnviadoACocina` y `PedidoCancelado`, y el inventario reacciona sin que el módulo de Pedidos lo conozca. Igual con `StockBajo` (lo escucha la alerta) y `StockActualizado` (lo escucha la disponibilidad del menú).

### Notas

- La cantidad de la receta está en la **unidad del insumo** (ej. arroz en kg → 0,150 por porción).
- El consumo por pedidos puede dejar el stock en **negativo**: indica que el inventario físico no coincide y hay que hacer un ajuste por conteo. No se bloquea el envío a cocina para no frenar la operación.
- Si se cancela un pedido que ya estaba **en preparación**, se registra un reintegro y una merma por la misma cantidad: el stock no cambia (lo cocinado no vuelve), pero el kárdex y el reporte RF35 lo muestran como merma y no como consumo.
- **Repository:** `InventarioService` trabaja con `InsumoRepositoryInterface`, no con la base de datos directamente.
- **DTO:** los datos de entrada (por ejemplo `InsumoDTO`) llegan validados al servicio, igual que en el Módulo 1.
