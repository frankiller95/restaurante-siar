<?php

namespace App\Contracts\Services;

/**
 * Actor externo "Servicio de Correo" de los casos de uso (docs/Diagrama_de_clases.md).
 * Cada método devuelve false si el correo no se pudo enviar (CU-01 flujo 7a).
 */
interface ServicioCorreo
{
    public function enviarCredenciales(string $correo, string $nombres, string $contrasena): bool;

    public function enviarNotificacionCambio(string $correo, string $nombres, string $mensaje): bool;

    public function enviarEnlaceRecuperacion(string $correo, string $nombres, string $enlace): bool;
}
