# Proyecto de Semestre

## Sistema Integral de Administración para Restaurantes (SIAR)

> **Versión actualizada.** Esta versión agrega el **Módulo 5. Inventario** (RF23 a RF27, más RF38 a RF40), que faltaba en el documento original. Ver [Cambios respecto al documento original](#cambios-respecto-al-documento-original).

## Descripción General

Desarrollar una aplicación web que permita gestionar integralmente la operación de un restaurante, incluyendo:

- Gestión de usuarios y roles.
- Administración del menú.
- Registro y seguimiento de pedidos.
- Gestión de mesas.
- Control de inventario.
- Facturación.
- Reportes gerenciales.
- Control de reservas.
- Dashboard administrativo.

Este proyecto se alinea con los resultados de aprendizaje de la asignatura:

- Implementación de prototipos funcionales aplicando patrones de diseño.
- Realización de pruebas unitarias, integración y funcionales.
- Documentación, despliegue y entrega del software.

## Requerimientos Funcionales

### Módulo 1. Gestión de Usuarios

- **RF01** Registrar usuarios.
- **RF02** Editar usuarios.
- **RF03** Eliminar usuarios.
- **RF04** Gestionar roles:
  - Administrador
  - Cajero
  - Mesero
  - Cocinero
- **RF05** Iniciar sesión.
- **RF06** Recuperar contraseña.

### Módulo 2. Gestión de Menú

- **RF07** Crear productos.
- **RF08** Modificar productos.
- **RF09** Eliminar productos.
- **RF10** Categorizar productos.
- **RF11** Gestionar disponibilidad.

### Módulo 3. Gestión de Mesas

- **RF12** Crear mesas.
- **RF13** Modificar mesas.
- **RF14** Consultar estado de mesas.
- **RF15** Reservar mesas.
- **RF16** Liberar mesas.

### Módulo 4. Pedidos

- **RF17** Crear pedido.
- **RF18** Agregar productos.
- **RF19** Modificar pedido.
- **RF20** Cancelar pedido.
- **RF21** Enviar pedido a cocina.
- **RF22** Consultar estado.

Estados:

- Pendiente
- En preparación
- Listo
- Entregado
- Facturado

### Módulo 5. Inventario *(nuevo)*

Controla los insumos (ingredientes y materiales) del restaurante: cuánto hay, qué entra, qué sale y qué hace falta comprar. Se conecta con el **Menú**, porque cada producto se prepara con insumos, y con **Pedidos**, porque cada plato enviado a cocina consume insumos.

**Gestión de insumos y movimientos**

- **RF23** Registrar insumos: nombre, unidad de medida (kg, g, L, ml, unidad), stock actual, stock mínimo y costo unitario.
- **RF24** Modificar y desactivar insumos. Un insumo con movimientos o usado en recetas no se borra: se desactiva para conservar el historial.
- **RF25** Registrar entradas de inventario (compras o reposiciones), con cantidad, costo, fecha, proveedor y usuario que la registra.
- **RF26** Registrar salidas y ajustes manuales: mermas, productos dañados o vencidos, y ajustes por conteo físico, siempre con un motivo.
- **RF27** Consultar existencias y el historial de movimientos (kárdex) de cada insumo, filtrando por fecha y tipo de movimiento.

**Integración con Menú y Pedidos**

- **RF38** Definir la receta de cada producto del menú: qué insumos lleva y en qué cantidad por porción.
- **RF39** Descontar automáticamente los insumos de la receta cuando un pedido se envía a cocina (RF21). Si el pedido se cancela (RF20) antes de pasar a "En preparación", los insumos se reintegran; si ya estaba en preparación, se registran como merma.
- **RF40** Alertar cuando un insumo llegue a su stock mínimo y marcar automáticamente como **no disponibles** (RF11) los productos del menú cuyos insumos no alcanzan para una porción. Cuando se registra una entrada que repone el insumo, el producto vuelve a quedar disponible.

> Los requerimientos RF38 a RF40 usan números nuevos, después de RF37, para no cambiar la numeración del resto del documento (RF28 a RF37).

### Módulo 6. Facturación

- **RF28** Generar factura.
- **RF29** Calcular impuestos.
- **RF30** Aplicar descuentos.
- **RF31** Registrar pago.
- **RF32** Emitir comprobante.

### Módulo 7. Reportes

- **RF33** Ventas por periodo.
- **RF34** Productos más vendidos.
- **RF35** Consumo de inventario. *(Se alimenta de los movimientos del Módulo 5.)*
- **RF36** Ocupación de mesas.
- **RF37** Indicadores financieros.

## Requerimientos No Funcionales

- **RNF01** Sistema web responsivo.
- **RNF02** Disponibilidad mínima del 95%.
- **RNF03** Tiempo de respuesta menor a 3 segundos.
- **RNF04** Contraseñas cifradas.
- **RNF05** Control de acceso por roles.
- **RNF06** Registro de auditoría.
- **RNF07** Arquitectura en capas.
- **RNF08** Uso obligatorio de Git.
- **RNF09** Cobertura mínima de pruebas del 70%.
- **RNF10** Documentación técnica completa.

## Entregables por Corte

### PRIMER CORTE (30%)

Semanas 1 a 6

**Objetivo**

Diseño e implementación inicial aplicando patrones de diseño.

**Entregables**

*Documento de Inicio*

- Problema identificado.
- Justificación.
- Objetivos.
- Alcance.
- Cronograma.

*Ingeniería de Requerimientos*

- Historias de usuario.
- Casos de uso.
- Requerimientos funcionales.
- Requerimientos no funcionales.

*Modelado*

- Diagrama de casos de uso.
- Diagrama de clases.
- Modelo entidad-relación.

*Diseño*

- Arquitectura del sistema.
- Patrones de diseño seleccionados.
- Justificación de cada patrón.

*Desarrollo*

Módulos completos:

- Usuarios
- Menú
- Mesas

*Evidencias*

- Repositorio GitHub.
- Commits individuales.
- Video demostración de 10 minutos.

**Sustentación Primer Corte**

Mostrar:

- Arquitectura.
- Base de datos.
- CRUD funcionales.
- Aplicación de patrones.

### SEGUNDO CORTE (30%)

Semanas 7 a 11

**Objetivo**

Pruebas y consolidación funcional.

**Entregables**

*Desarrollo*

Módulos completos:

- Pedidos
- Inventario
- Facturación

*Pruebas*

- Pruebas unitarias.
- Pruebas de integración.
- Pruebas funcionales.

*Evidencias*

Matriz de pruebas:

| Caso | Resultado |
|---|---|
| Unitarias | OK |
| Integración | OK |
| Funcionales | OK |

*Documentación Técnica*

- Arquitectura actualizada.
- Diagramas UML actualizados.
- Manual técnico preliminar.

**Sustentación**

Demostración completa del flujo:

Cliente → Pedido → Cocina → Factura.

### TERCER CORTE (40%)

Semanas 12 a 16

**Objetivo**

Optimización, despliegue y entrega final.

**Entregables**

*Desarrollo Final*

Módulos:

- Reportes
- Dashboard
- Reservas

*Optimización*

- Refactorización.
- Mejora de rendimiento.
- Corrección de hallazgos.

*Despliegue*

- Sistema publicado en Internet.
- URL funcional.

*Documentación Final*

Manual Técnico:

- Arquitectura.
- Patrones.
- API.
- Base de datos.

Manual de Usuario:

- Login.
- Pedidos.
- Inventario.
- Facturación.
- Reportes.

Informe Final:

- Introducción.
- Metodología.
- Desarrollo.
- Resultados.
- Conclusiones.
- Trabajo futuro.

**Sustentación Final**

20 a 30 minutos. Debe incluir:

- Problema.
- Solución.
- Arquitectura.
- Patrones utilizados.
- Pruebas ejecutadas.
- Métricas.
- Despliegue.
- Demostración en vivo.

---

## Cambios respecto al documento original

| Cambio | Motivo |
|---|---|
| Se agregó el **Módulo 5. Inventario** entre Pedidos (Módulo 4) y Facturación (Módulo 6). | El documento original pasaba del Módulo 4 al 6 y de RF22 a RF28. Además, ya mencionaba el inventario en la descripción general, en el segundo corte, en el manual de usuario y en RF35 "Consumo de inventario". |
| **RF23 a RF27**: gestión de insumos, entradas, salidas y ajustes, y kárdex. | Ocupan los números que faltaban en la secuencia original. |
| **RF38 a RF40**: recetas, descuento automático por pedido y alertas de stock con disponibilidad automática. | Conectan el inventario con el Menú (RF11) y con Pedidos (RF20, RF21). Usan números nuevos para no alterar los ya existentes. |
| Se aclaró que **RF35** se alimenta del Módulo 5. | Para que el reporte de consumo tenga una fuente de datos definida. |
