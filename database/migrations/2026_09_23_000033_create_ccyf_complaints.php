<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ccyf_quejas_participacion', function (Blueprint $table): void {
            $table->id();
            $table->string('origen', 12);
            $table->unsignedInteger('registro_id');
            $table->text('observacion');
            $table->boolean('calificacion');
            $table->string('evidencia_ruta', 500)->nullable();
            $table->string('evidencia_nombre', 255)->nullable();
            $table->string('evidencia_mime', 100)->nullable();
            $table->unsignedBigInteger('evidencia_bytes')->nullable();
            $table->unsignedInteger('creado_por');
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index(['origen', 'registro_id', 'activo']);
        });

        Schema::create('ccyf_quejas_manuales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plantel_id')->constrained('ccyf_planteles')->restrictOnDelete();
            $table->string('permisionario', 150);
            $table->text('queja');
            $table->unsignedInteger('creado_por');
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index(['plantel_id', 'activo']);
        });

        try {
            $legacy = DB::connection('legacy');
            if (! $legacy->getSchemaBuilder()->hasTable('tm_mennu')
                || ! $legacy->getSchemaBuilder()->hasTable('td_medu_detalle')) return;
            $grants = $legacy->table('td_medu_detalle as d')
                ->join('tm_mennu as m', 'm.men_id', '=', 'd.men_id')
                ->where('m.men_nom', 'quejas')->where('m.est', 1)->where('d.mend_permi', 'si')
                ->pluck('d.rol_id')->unique();
            foreach ($grants as $legacyRoleId) {
                $roleIds = DB::table('ccyf_roles')->where('legacy_rol_id', $legacyRoleId)->pluck('rol_id');
                foreach ($roleIds as $roleId) {
                    DB::table('ccyf_role_permissions')->insertOrIgnore([
                        'rol_id' => $roleId, 'menu_key' => 'quejas', 'allowed' => true,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        } catch (\Throwable) {
            // El esquema local puede migrarse antes de conectar la base histórica.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ccyf_quejas_manuales');
        Schema::dropIfExists('ccyf_quejas_participacion');
        DB::table('ccyf_role_permissions')->where('menu_key', 'quejas')->delete();
    }
};
