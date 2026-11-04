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
