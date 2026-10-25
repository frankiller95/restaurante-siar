@extends('emails.layout')

@section('contenido')
    <p>Hola {{ $nombres }},</p>
    <p>Recibimos una solicitud para restablecer tu contraseña. Usa este enlace:</p>
    <p><a href="{{ $enlace }}" style="display:inline-block;padding:10px 18px;background:#b45309;color:#ffffff;text-decoration:none;border-radius:6px;">Restablecer contraseña</a></p>
    <p>El enlace se puede usar una sola vez y vence en {{ $minutos }} minutos.</p>
    <p>Si no lo solicitaste, ignora este correo.</p>
@endsection
