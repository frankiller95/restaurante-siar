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
        + registrarEntrada(Insumo insumo, decimal cantidad, decimal costo, String proveedor) MovimientoInventario
        + registrarSalida(Insumo insumo, decimal cantidad, TipoMovimiento tipo, String motivo) MovimientoInventario
        + descontarPorPedido(Pedido pedido) void
        + reintegrarPorPedido(Pedido pedido) void
        + consultarKardex(Insumo insumo, Date desde, Date hasta) List~MovimientoInventario~
        + insumosBajoMinimo() List~Insumo~
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

    class InventarioObserver {
        <<observer>>
        + alEnviarACocina(PedidoEnviadoACocina evento) void
        + alCancelarPedido(PedidoCancelado evento) void
    }

    class DisponibilidadMenuObserver {
        <<observer>>
        + alStockBajo(StockBajo evento) void
    }

    class AlertaStockObserver {
        <<observer>>
        + alStockBajo(StockBajo evento) void
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

    Pedido ..> PedidoEnviadoACocina : publica
    Pedido ..> PedidoCancelado : publica
    InventarioObserver ..> PedidoEnviadoACocina : escucha
    InventarioObserver ..> PedidoCancelado : escucha
    InventarioObserver ..> InventarioService : descuenta / reintegra
    DisponibilidadMenuObserver ..> StockBajo : escucha
    DisponibilidadMenuObserver ..> Producto : marca no disponible
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
| RF40 | `StockBajo`, `DisponibilidadMenuObserver`, `AlertaStockObserver` | Cuando un insumo baja del mínimo se avisa al administrador, y los productos que ya no se pueden preparar se marcan como no disponibles (RF11). |
| RF35 | `MovimientoInventario` | El reporte de consumo de inventario se calcula a partir de los movimientos de tipo `CONSUMO_PEDIDO` y `MERMA`. |

### Patrones aplicados en este módulo

- **Observer:** `Pedido` publica `PedidoEnviadoACocina` y `PedidoCancelado`, y el inventario reacciona sin que el módulo de Pedidos lo conozca. Igual con `StockBajo`, que lo escuchan la alerta y la disponibilidad del menú.
- **Repository:** `InventarioService` trabaja con `InsumoRepositoryInterface`, no con la base de datos directamente.
- **DTO:** los datos de entrada (por ejemplo `InsumoDTO`) llegan validados al servicio, igual que en el Módulo 1.
