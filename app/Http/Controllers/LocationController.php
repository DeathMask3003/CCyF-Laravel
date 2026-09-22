<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function index(Request $request): View
    {
        $today = $request->boolean('hoy');
        $query = DB::table('ccyf_ubicaciones')->where('usu_id', $request->user()->getKey());
        if ($today) {
            $query->whereDate('fecha_registro', today());
        }

        $locations = $query->orderByDesc('fecha_registro')->orderByDesc('id')->paginate(20)->withQueryString();

        return view('ubicaciones.index', [
            'locations' => $locations,
            'mapPoints' => $locations->getCollection()->map(fn ($location) => [
                'lat' => (float) $location->latitud,
                'lng' => (float) $location->longitud,
                'city' => $location->ciudad ?: 'Ubicación registrada',
                'date' => $location->fecha_registro,
                'accuracy' => $location->precision_gps ? (float) $location->precision_gps : null,
            ])->all(),
            'today' => $today,
            'total' => DB::table('ccyf_ubicaciones')->where('usu_id', $request->user()->getKey())->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitud' => ['required', 'numeric', 'between:-90,90'],
            'longitud' => ['required', 'numeric', 'between:-180,180'],
            'precision_gps' => ['nullable', 'numeric', 'between:0,100000'],
        ]);

        DB::table('ccyf_ubicaciones')->insert([
            'usu_id' => $request->user()->getKey(),
            'latitud' => $data['latitud'],
            'longitud' => $data['longitud'],
            'precision_gps' => $data['precision_gps'] ?? null,
            'es_aproximada' => ($data['precision_gps'] ?? 0) > 5000,
            'fuente' => 'Navegador',
            'fecha_registro' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Ubicación guardada correctamente.'], 201);
    }
}
