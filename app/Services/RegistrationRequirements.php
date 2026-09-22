<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class RegistrationRequirements
{
    public function ensureDefaults(): int
    {
        $inserted = 0;
        foreach ($this->templates() as $template => $requirements) {
            $serviceId = DB::table('ccyf_tipos_servicio')->where('plantilla', $template)->value('id');
            if (! $serviceId) {
                continue;
            }
            foreach ($requirements as $order => [$key, $name]) {
                if (DB::table('ccyf_requisitos_documento')->where([
                    'servicio_id' => $serviceId, 'clave' => $key,
                ])->exists()) {
                    continue;
                }
                DB::table('ccyf_requisitos_documento')->insert([
                    'servicio_id' => $serviceId,
                    'clave' => $key,
                    'nombre' => $name,
                    'orden' => $order + 1,
                    'requerido' => true,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $inserted++;
            }
        }

        return $inserted;
    }

    public function activeFor(int $serviceId)
    {
        $this->ensureDefaults();

        return DB::table('ccyf_requisitos_documento')->where('servicio_id', $serviceId)
            ->where('activo', true)->orderBy('orden')->orderBy('id')->get();
    }

    private function templates(): array
    {
        $common = [
            ['prop_escrito', 'Propuesta por escrito'],
            ['acta_nac', 'Acta de nacimiento'],
            ['identi_ofici', 'Identificación oficial'],
            ['domicilio', 'Comprobante de domicilio'],
            ['dat_grals', 'Datos generales'],
            ['dos_cartas', 'Primera carta de recomendación'],
            ['ine_cartarecom_1', 'INE de la primera carta de recomendación'],
            ['carta_recom2', 'Segunda carta de recomendación'],
            ['ine_cartarecom_2', 'INE de la segunda carta de recomendación'],
            ['carta_protesta', 'Carta protesta'],
        ];

        return [
            'cafeteria' => array_merge($common, [
                ['menu_aval', 'Menú avalado por nutriólogo'],
                ['forma_prec', 'Formato para publicar precios'],
                ['mobiliario', 'Mobiliario que utilizará'],
                ['menuali_precio', 'Menú alimenticio con precios'],
                ['marca_porcion', 'Marcas y porciones de alimentos'],
            ]),
            'fotocopiado' => array_merge($common, [
                ['forma_prec', 'Formato para publicar precios'],
                ['mobiliario', 'Mobiliario que utilizará'],
                ['menuali_precio', 'Listado de precios'],
            ]),
        ];
    }
}
