@extends('emails.layout')

@section('contenido')
    <p>Hola {{ $nombres }},</p>
    <p>{{ $mensaje }}</p>
    <p>Si no reconoces este cambio, comunícate con el administrador del restaurante.</p>
@endsection
