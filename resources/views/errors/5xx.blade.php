@extends('errors.layout')
@section('title', 'Servicio temporalmente no disponible')
@section('code'){{ $exception->getStatusCode() }}@endsection
@section('heading', 'Tuvimos un problema temporal')
@section('description', 'El portal no pudo completar esta operación. Inténtalo de nuevo en unos minutos.')
@section('tip', 'Si acababas de enviar un formulario, verifica primero si se guardó para evitar duplicarlo.')
