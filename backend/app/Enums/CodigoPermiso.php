<?php

namespace App\Enums;

/**
 * Catálogo de permisos del sistema. Se cargan en la tabla `permisos` con el
 * seeder y se usan en las rutas con el middleware `permiso:<codigo>`.
 */
enum CodigoPermiso: string
{
    case GESTIONAR_USUARIOS = 'gestionar_usuarios';
    case GESTIONAR_MENU = 'gestionar_menu';
    case CONSULTAR_MENU = 'consultar_menu';
    case GESTIONAR_MESAS = 'gestionar_mesas';
    case CONSULTAR_MESAS = 'consultar_mesas';
    case GESTIONAR_RESERVAS = 'gestionar_reservas';
    case TOMAR_PEDIDOS = 'tomar_pedidos';
    case PREPARAR_PEDIDOS = 'preparar_pedidos';
    case FACTURAR = 'facturar';
    case GESTIONAR_INVENTARIO = 'gestionar_inventario';
    case VER_REPORTES = 'ver_reportes';

    public function nombre(): string
    {
        return match ($this) {
            self::GESTIONAR_USUARIOS => 'Gestionar usuarios y roles',
            self::GESTIONAR_MENU => 'Gestionar menú',
            self::CONSULTAR_MENU => 'Consultar menú',
            self::GESTIONAR_MESAS => 'Gestionar mesas',
            self::CONSULTAR_MESAS => 'Consultar mesas',
            self::GESTIONAR_RESERVAS => 'Gestionar reservas',
            self::TOMAR_PEDIDOS => 'Tomar pedidos',
            self::PREPARAR_PEDIDOS => 'Preparar pedidos (cocina)',
            self::FACTURAR => 'Facturar y registrar pagos',
            self::GESTIONAR_INVENTARIO => 'Gestionar inventario',
            self::VER_REPORTES => 'Ver reportes y dashboard',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::GESTIONAR_USUARIOS => 'Registrar, editar y eliminar usuarios; administrar roles y permisos.',
            self::GESTIONAR_MENU => 'Crear, modificar y eliminar productos y categorías.',
            self::CONSULTAR_MENU => 'Ver el menú y la disponibilidad de productos.',
            self::GESTIONAR_MESAS => 'Crear y modificar mesas.',
            self::CONSULTAR_MESAS => 'Consultar el estado de las mesas.',
            self::GESTIONAR_RESERVAS => 'Reservar y liberar mesas.',
            self::TOMAR_PEDIDOS => 'Crear, modificar y enviar pedidos a cocina.',
            self::PREPARAR_PEDIDOS => 'Ver y actualizar el estado de los pedidos en cocina.',
            self::FACTURAR => 'Generar facturas, aplicar descuentos y registrar pagos.',
            self::GESTIONAR_INVENTARIO => 'Controlar existencias e insumos.',
            self::VER_REPORTES => 'Consultar reportes gerenciales e indicadores.',
        };
    }
}
