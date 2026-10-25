<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CredencialesMail extends Mailable
{
    public function __construct(
        public readonly string $nombres,
        public readonly string $correo,
        public readonly string $contrasena,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'SIAR - Tus credenciales de acceso');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.credenciales', with: [
            'urlIngreso' => rtrim(config('siar.frontend_url'), '/').'/login',
        ]);
    }
}
