@extends('errors.layout')
@section('title', 'No se pudo abrir la página')
@section('code'){{ $exception->getStatusCode() }}@endsection
@section('heading', 'No pudimos abrir esta página')
@section('description', 'La solicitud no pudo completarse. Revisa la dirección e inténtalo nuevamente.')
