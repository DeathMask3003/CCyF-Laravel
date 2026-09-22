<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PriceCatalogs
{
    public function legacyCategory(int $id, bool $activeOnly = false): ?object
    {
        $query = DB::connection('legacy')->table('tm_categoria_widi')->where('cat_id', $id);

        return ($activeOnly ? $query->where('est', 1) : $query)->first(['cat_id', 'cat_nom', 'est']);
    }

    public function suggestedServiceId(int $id, string $name): ?int
    {
        $service = DB::connection('legacy')->table('tm_documento')
            ->where('num_doc', (string) $id)
            ->whereIn('trami_id', [3, 4])
            ->selectRaw('trami_id, count(*) as total')
            ->groupBy('trami_id')
            ->orderByDesc('total')
            ->value('trami_id');

        if ($service !== null) {
            return DB::table('ccyf_tipos_servicio')->where('legacy_trami_id', $service)->value('id');
        }

        $key = str_contains(mb_strtolower($name), 'cafeter') ? 'cafeteria'
            : (str_contains(mb_strtolower($name), 'fotocop') ? 'fotocopiado' : null);

        return $key ? DB::table('ccyf_tipos_servicio')->where('plantilla', $key)->value('id') : null;
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

    public function prepare(int $legacyId, int $serviceId): int
    {
        return DB::transaction(function () use ($legacyId, $serviceId): int {
            $existing = DB::table('ccyf_catalogos')->where('legacy_cat_id', $legacyId)->value('id');

            if ($existing) {
                return (int) $existing;
            }

            $service = DB::table('ccyf_tipos_servicio')->where('id', $serviceId)->where('activo', true)->first();
            abort_unless($service, 422, 'El tipo de servicio no está disponible.');
            $template = $service->plantilla;
            $now = now();
            $id = DB::table('ccyf_catalogos')->insertGetId([
                'legacy_cat_id' => $legacyId,
                'tipo' => $template ?: 'personalizado',
                'servicio_id' => $serviceId,
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
