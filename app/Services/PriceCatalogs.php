<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PriceCatalogs
{
    public function category(int $id, bool $activeOnly = false): ?object
    {
        $query = DB::table('ccyf_convocatorias')->where('id', $id);

        return ($activeOnly ? $query->where('activo', true) : $query)->first([
            'id as cat_id', 'legacy_cat_id', 'numero as cat_nom', 'activo as est', 'servicio_id',
        ]);
    }

    public function templates(string $type): array
    {
        return match ($type) {
            'cafeteria' => [
                ['Tlacoyo de nopales', 'pieza'], ['Torta de frijoles', 'pieza'],
                ['Torta de pollo', 'pieza'], ['Quesadilla', 'pieza'],
                ['Tostada de nopales', 'pieza'], ['Enfrijoladas', 'porción'],
                ['Gelatina con topping', 'vaso'], ['Yogurt con topping', 'porción'],
                ['Palomitas', 'bolsa'], ['Atole', 'vaso'],
                ['Agua simple', 'botella'], ['Agua de frutas', 'vaso'],
            ],
            'fotocopiado' => [
                ['Tamaño carta', 'copia'], ['Tamaño oficio', 'copia'],
                ['Mayoreo tamaño carta', 'copia'], ['Mayoreo tamaño oficio', 'copia'],
                ['Reciclada tamaño oficio', 'copia'], ['Reciclada tamaño carta', 'copia'],
                ['Mayoreo reciclada carta', 'copia'], ['Mayoreo reciclada oficio', 'copia'],
            ],
        };
    }

    public function prepare(int $convocationId): int
    {
        return DB::transaction(function () use ($convocationId): int {
            $existing = DB::table('ccyf_catalogos')->where('convocatoria_id', $convocationId)->value('id');

            if ($existing) {
                return (int) $existing;
            }

            $convocation = DB::table('ccyf_convocatorias')->where('id', $convocationId)->where('activo', true)->first();
            abort_unless($convocation, 404);
            $service = DB::table('ccyf_tipos_servicio')->where('id', $convocation->servicio_id)->where('activo', true)->first();
            abort_unless($service, 422, 'El tipo de servicio no está disponible.');
            $template = $service->plantilla;
            $now = now();
            $id = DB::table('ccyf_catalogos')->insertGetId([
                'legacy_cat_id' => $convocation->legacy_cat_id,
                'convocatoria_id' => $convocation->id,
                'tipo' => $template ?: 'personalizado',
                'servicio_id' => $service->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($template ? $this->templates($template) : [] as $index => [$name, $unit]) {
                DB::table('ccyf_productos')->insert([
                    'catalogo_id' => $id,
                    'nombre' => $name,
                    'unidad' => $unit,
                    'orden' => $index + 1,
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return $id;
        });
    }
}
