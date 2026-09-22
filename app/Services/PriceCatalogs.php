<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PriceCatalogs
{
    public const TYPES = ['cafeteria', 'fotocopiado'];

    public function legacyCategory(int $id, bool $activeOnly = false): ?object
    {
        $query = DB::connection('legacy')->table('tm_categoria_widi')->where('cat_id', $id);

        return ($activeOnly ? $query->where('est', 1) : $query)->first(['cat_id', 'cat_nom', 'est']);
    }

    public function suggestedType(int $id, string $name): ?string
    {
        $service = DB::connection('legacy')->table('tm_documento')
            ->where('num_doc', (string) $id)
            ->whereIn('trami_id', [3, 4])
            ->selectRaw('trami_id, count(*) as total')
            ->groupBy('trami_id')
            ->orderByDesc('total')
            ->value('trami_id');

        if ($service !== null) {
            return (int) $service === 3 ? 'cafeteria' : 'fotocopiado';
        }

        $name = Str::lower($name);

        return Str::contains($name, 'cafeter') ? 'cafeteria'
            : (Str::contains($name, 'fotocop') ? 'fotocopiado' : null);
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

    public function prepare(int $legacyId, string $type): int
    {
        return DB::transaction(function () use ($legacyId, $type): int {
            $existing = DB::table('ccyf_catalogos')->where('legacy_cat_id', $legacyId)->value('id');

            if ($existing) {
                return (int) $existing;
            }

            $now = now();
            $id = DB::table('ccyf_catalogos')->insertGetId([
                'legacy_cat_id' => $legacyId,
                'tipo' => $type,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($this->templates($type) as $index => [$name, $unit]) {
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
