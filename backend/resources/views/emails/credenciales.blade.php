@extends('emails.layout')

@section('contenido')
    <p>Hola {{ $nombres }},</p>
    <p>Se creó tu cuenta en SIAR. Estos son tus datos de acceso:</p>
    <p style="background:#f9fafb;padding:12px 16px;border-radius:6px;">
        <strong>Correo:</strong> {{ $correo }}<br>
        <strong>Contraseña temporal:</strong> {{ $contrasena }}
    </p>
    <p>Ingresa en <a href="{{ $urlIngreso }}">{{ $urlIngreso }}</a> y cambia tu contraseña desde "Mi perfil".</p>
@endsection
