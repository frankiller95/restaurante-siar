<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NotificacionCambioMail extends Mailable
{
    public function __construct(
        public readonly string $nombres,
        public readonly string $mensaje,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'SIAR - Cambios en tu cuenta');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.notificacion-cambio');
    }
}
