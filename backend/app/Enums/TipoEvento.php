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
}
