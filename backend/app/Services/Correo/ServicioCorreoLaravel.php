<?php

namespace App\Services\Correo;

use App\Contracts\Services\ServicioCorreo;
use App\Mail\CredencialesMail;
use App\Mail\EnlaceRecuperacionMail;
use App\Mail\NotificacionCambioMail;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Implementación del Servicio de Correo con el Mail de Laravel (SMTP, log, etc.
 * según MAIL_MAILER en el .env). Si el envío falla, devuelve false en vez de
 * romper la operación principal.
 */
class ServicioCorreoLaravel implements ServicioCorreo
{
    public function enviarCredenciales(string $correo, string $nombres, string $contrasena): bool
    {
        return $this->enviar($correo, new CredencialesMail($nombres, $correo, $contrasena));
    }

    public function enviarNotificacionCambio(string $correo, string $nombres, string $mensaje): bool
    {
        return $this->enviar($correo, new NotificacionCambioMail($nombres, $mensaje));
    }

    public function enviarEnlaceRecuperacion(string $correo, string $nombres, string $enlace): bool
    {
        return $this->enviar($correo, new EnlaceRecuperacionMail($nombres, $enlace));
    }

    private function enviar(string $correo, Mailable $mensaje): bool
    {
        try {
            Mail::to($correo)->send($mensaje);

            return true;
        } catch (Throwable $e) {
            Log::error('No se pudo enviar el correo', ['correo' => $correo, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
