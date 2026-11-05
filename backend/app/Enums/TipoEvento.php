<?php

namespace App\Enums;

/** Tipos de evento que se registran en la bitácora de auditoría (RNF06). */
enum TipoEvento: string
{
    case INICIO_SESION = 'inicio_sesion';
    case INICIO_SESION_FALLIDO = 'inicio_sesion_fallido';
    case CIERRE_SESION = 'cierre_sesion';
    case USUARIO_REGISTRADO = 'usuario_registrado';
    case USUARIO_ACTUALIZADO = 'usuario_actualizado';
    case USUARIO_INACTIVADO = 'usuario_inactivado';
    case USUARIO_ELIMINADO = 'usuario_eliminado';
    case CREDENCIALES_REENVIADAS = 'credenciales_reenviadas';
    case CORREO_NO_ENVIADO = 'correo_no_enviado';
    case RECUPERACION_SOLICITADA = 'recuperacion_solicitada';
    case CONTRASENA_RESTABLECIDA = 'contrasena_restablecida';
    case CONTRASENA_CAMBIADA = 'contrasena_cambiada';
    case ROL_CREADO = 'rol_creado';
    case ROL_ACTUALIZADO = 'rol_actualizado';
    case ROL_ELIMINADO = 'rol_eliminado';
    case PERMISOS_ACTUALIZADOS = 'permisos_actualizados';
    case CATEGORIA_CREADA = 'categoria_creada';
    case CATEGORIA_ACTUALIZADA = 'categoria_actualizada';
    case CATEGORIA_ELIMINADA = 'categoria_eliminada';
    case PRODUCTO_CREADO = 'producto_creado';
    case PRODUCTO_ACTUALIZADO = 'producto_actualizado';
    case PRODUCTO_ELIMINADO = 'producto_eliminado';
    case DISPONIBILIDAD_CAMBIADA = 'disponibilidad_cambiada';
    case MESA_CREADA = 'mesa_creada';
    case MESA_ACTUALIZADA = 'mesa_actualizada';
    case MESA_ELIMINADA = 'mesa_eliminada';
    case MESA_ESTADO_CAMBIADO = 'mesa_estado_cambiado';
    case RESERVA_CREADA = 'reserva_creada';
    case RESERVA_CANCELADA = 'reserva_cancelada';
    case RESERVA_CUMPLIDA = 'reserva_cumplida';
    case RESERVA_NO_ASISTIO = 'reserva_no_asistio';
    case PEDIDO_CREADO = 'pedido_creado';
    case PEDIDO_ACTUALIZADO = 'pedido_actualizado';
    case PEDIDO_ENVIADO_COCINA = 'pedido_enviado_cocina';
    case PEDIDO_EN_PREPARACION = 'pedido_en_preparacion';
    case PEDIDO_LISTO = 'pedido_listo';
    case PEDIDO_ENTREGADO = 'pedido_entregado';
    case PEDIDO_CANCELADO = 'pedido_cancelado';
    case INSUMO_CREADO = 'insumo_creado';
    case INSUMO_ACTUALIZADO = 'insumo_actualizado';
    case INSUMO_ELIMINADO = 'insumo_eliminado';
    case INVENTARIO_ENTRADA = 'inventario_entrada';
    case INVENTARIO_MERMA = 'inventario_merma';
    case INVENTARIO_AJUSTE = 'inventario_ajuste';
    case STOCK_BAJO = 'stock_bajo';
    case RECETA_ACTUALIZADA = 'receta_actualizada';
    case PRODUCTO_AGOTADO_INVENTARIO = 'producto_agotado_inventario';
    case PRODUCTO_REPUESTO_INVENTARIO = 'producto_repuesto_inventario';
}
