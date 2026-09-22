<?php

namespace Tests\Feature;

use App\Models\LegacyUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrevaluationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('database.connections.legacy', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge('sqlite'); DB::purge('legacy');
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true]);
        Schema::connection('legacy')->create('tm_usuario', function (Blueprint $table): void {
            $table->integer('usu_id')->primary(); $table->string('usu_area'); $table->string('ine')->nullable(); $table->string('usu_telf')->nullable();
        });
        Schema::connection('legacy')->create('tm_categoria_widi', function (Blueprint $table): void {
            $table->integer('cat_id')->primary(); $table->string('cat_nom');
        });
        Schema::connection('legacy')->create('tm_documento', function (Blueprint $table): void {
            $table->integer('doc_id')->primary(); $table->integer('trami_id'); $table->integer('usu_id');
            $table->integer('num_doc'); $table->string('doc_exter'); $table->string('doc_estado'); $table->dateTime('fech_crea');
        });
        Schema::connection('legacy')->create('td_documentov3', function (Blueprint $table): void {
            $table->integer('det_id')->primary(); $table->integer('doc_id'); $table->integer('est'); $table->string('prop_escrito')->nullable();
        });
        foreach (['tm_documento_cafeteria', 'tm_documento_fotocopiado'] as $name) {
            Schema::connection('legacy')->create($name, function (Blueprint $table): void {
                $table->integer('doc_id'); $table->integer('est'); $table->decimal('tlacoyo')->nullable();
            });
        }
        foreach (['tm_preval_cafe_doc', 'tm_preval_foto_doc'] as $name) {
            Schema::connection('legacy')->create($name, function (Blueprint $table) use ($name): void {
                $table->increments($name === 'tm_preval_cafe_doc' ? 'id_preval_cafe' : 'id_preval_foto');
                $table->integer('doc_id'); $table->integer('usu_preval'); $table->integer('est');
                $table->string('campo_doc'); $table->text('comentario')->nullable(); $table->integer('cumple')->nullable();
                $table->integer('viable_estado')->nullable(); $table->dateTime('fecha_registro')->nullable();
                $table->text('observaciones_admin')->nullable(); $table->dateTime('fecha_observaciones_admin')->nullable();
            });
        }
        Schema::connection('legacy')->create('tm_mennu', function (Blueprint $table): void {
            $table->integer('men_id')->primary(); $table->string('men_nom'); $table->integer('est');
        });
        Schema::connection('legacy')->create('td_medu_detalle', function (Blueprint $table): void {
            $table->integer('rol_id'); $table->integer('men_id'); $table->string('mend_permi');
        });

        foreach ([18 => 'Administrador', 20 => 'Prevaluador', 21 => 'Prevaluador 2', 1 => 'Concursante'] as $id => $name) {
            DB::table('ccyf_roles')->insert(['rol_id' => $id, 'legacy_rol_id' => $id, 'rol_nom' => $name, 'est' => 1]);
            DB::table('ccyf_usuarios')->insert(['usu_id' => $id, 'legacy_usu_id' => $id, 'usu_area' => $name,
                'usu_correo' => $id.'@example.test', 'usu_pass' => Hash::make('Clave123456'), 'rol_id' => $id, 'est' => 1]);
        }
        DB::table('ccyf_role_permissions')->insert([
            ['rol_id' => 18, 'menu_key' => 'Prevaluaciones_admin', 'allowed' => 1],
            ['rol_id' => 20, 'menu_key' => 'prevaluacion', 'allowed' => 1],
            ['rol_id' => 21, 'menu_key' => 'prevaluacion', 'allowed' => 1],
        ]);
        DB::connection('legacy')->table('tm_usuario')->insert(['usu_id'=>4,'usu_area'=>'Permisionario Uno','ine'=>'ABCD800101HMCLRS09','usu_telf'=>'5217222123456']);
        DB::connection('legacy')->table('tm_categoria_widi')->insert(['cat_id'=>9,'cat_nom'=>'Novena convocatoria']);
        DB::connection('legacy')->table('tm_documento')->insert([
            ['doc_id'=>80,'trami_id'=>3,'usu_id'=>4,'num_doc'=>9,'doc_exter'=>'Plantel Centro','doc_estado'=>'Finalizado','fech_crea'=>'2026-06-01 10:00:00'],
            ['doc_id'=>81,'trami_id'=>4,'usu_id'=>4,'num_doc'=>9,'doc_exter'=>'Plantel Norte','doc_estado'=>'Finalizado','fech_crea'=>'2026-06-02 10:00:00'],
        ]);
        Storage::fake('local');
        Storage::disk('local')->put('assets/documents/80/propuesta.pdf', '%PDF-1.4 documento');
        config()->set('ccyf.legacy_files_root', Storage::disk('local')->path('assets/documents'));
        DB::connection('legacy')->table('td_documentov3')->insert(['det_id'=>1,'doc_id'=>80,'est'=>1,'prop_escrito'=>'propuesta.pdf']);
        DB::connection('legacy')->table('tm_documento_cafeteria')->insert(['doc_id'=>80,'est'=>1,'tlacoyo'=>15]);
    }

    public function test_one_view_shows_both_services_and_admin_controls(): void
    {
        $this->be(LegacyUser::findOrFail(18));
        $this->get('/prevaluaciones')->assertOk()->assertSee('Cafetería')->assertSee('Fotocopiado')
            ->assertSee('Permisionario Uno')->assertSee('Revisar expediente')->assertDontSee('actual-81');
        $this->get('/prevaluaciones?servicio=fotocopiado')->assertOk()->assertSee('Plantel Norte')->assertDontSee('Plantel Centro');
        $this->get('/prevaluaciones?registro=historico-80')->assertOk()->assertSee('Documentos enviados')
            ->assertSee('Visualización')->assertSee('Revisión documental')->assertSee('Observaciones del administrador')
            ->assertSee('/prevaluaciones/historico-80/documentos/prop_escrito')->assertSee('Abrir PDF');
    }

    public function test_evaluator_can_save_own_review_and_admin_can_only_save_observation(): void
    {
        $this->be(LegacyUser::findOrFail(20));
        $this->put('/prevaluaciones/historico-80', ['servicio'=>'cafeteria','resultado'=>'1','items'=>[
            'prop_escrito'=>['cumple'=>'1','comentario'=>'Completo'], 'tlacoyo'=>['cumple'=>'0','comentario'=>'Revisar precio'],
        ]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ccyf_prevaluaciones', ['origen'=>'historico','registro_id'=>80,'evaluador_id'=>20,'resultado'=>1]);
        $this->assertDatabaseHas('ccyf_prevaluacion_items', ['clave'=>'tlacoyo','cumple'=>0]);
        $this->assertSame(0, DB::connection('legacy')->table('tm_preval_cafe_doc')->count());
        $this->put('/prevaluaciones/historico-80/observaciones', ['observaciones'=>'Revisado'])->assertForbidden();
        $this->be(LegacyUser::findOrFail(21));
        $this->put('/prevaluaciones/historico-80', ['items'=>['prop_escrito'=>['cumple'=>'0']]])->assertForbidden();
        $this->be(LegacyUser::findOrFail(18));
        $this->put('/prevaluaciones/historico-80', ['items'=>['prop_escrito'=>['cumple'=>'0']]])->assertForbidden();
        $this->put('/prevaluaciones/historico-80/observaciones', ['observaciones'=>'Falta confirmar precio'])->assertRedirect();
        $this->get('/prevaluaciones?registro=historico-80')->assertOk()->assertSee('Falta confirmar precio');
    }

    public function test_pdf_is_inline_and_bound_to_document_and_permission(): void
    {
        $this->be(LegacyUser::findOrFail(20));
        $pdf = $this->get('/prevaluaciones/historico-80/documentos/prop_escrito')->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('private', $pdf->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $pdf->headers->get('Cache-Control'));
        $this->get('/prevaluaciones/historico-81/documentos/prop_escrito')->assertNotFound();
        $this->get('/prevaluaciones/historico-80/documentos/../../otro')->assertNotFound();
        $this->be(LegacyUser::findOrFail(1));
        $this->get('/prevaluaciones')->assertForbidden();
        $this->get('/prevaluaciones/historico-80/documentos/prop_escrito')->assertForbidden();
    }

    public function test_permission_import_keeps_local_choices(): void
    {
        DB::connection('legacy')->table('tm_mennu')->insert([
            ['men_id'=>58,'men_nom'=>'prevaluacion','est'=>1], ['men_id'=>59,'men_nom'=>'Prevaluaciones_admin','est'=>1],
        ]);
        DB::connection('legacy')->table('td_medu_detalle')->insert([
            ['rol_id'=>20,'men_id'=>58,'mend_permi'=>'si'], ['rol_id'=>18,'men_id'=>59,'mend_permi'=>'si'],
        ]);
        DB::table('ccyf_role_permissions')->where('rol_id',20)->where('menu_key','prevaluacion')->update(['allowed'=>0]);
        $this->artisan('ccyf:import-prevaluacion-permisos')->assertSuccessful();
        $this->assertDatabaseHas('ccyf_role_permissions', ['rol_id'=>20,'menu_key'=>'prevaluacion','allowed'=>0]);
        $this->assertDatabaseCount('ccyf_role_permissions', 3);
    }

    public function test_new_registration_uses_its_dynamic_requirements_prices_and_pdf(): void
    {
        $service = DB::table('ccyf_tipos_servicio')->insertGetId([
            'legacy_trami_id'=>3, 'nombre'=>'Cafetería', 'nombre_clave'=>'cafeteria', 'descripcion'=>'Servicio de cafetería',
        ]);
        $campus = DB::table('ccyf_planteles')->insertGetId(['nombre'=>'Plantel Nuevo','nombre_clave'=>'plantel-nuevo']);
        $call = DB::table('ccyf_convocatorias')->insertGetId(['numero'=>'Convocatoria 2026','numero_clave'=>'convocatoria-2026','servicio_id'=>$service]);
        $catalog = DB::table('ccyf_catalogos')->insertGetId(['tipo'=>'cafeteria','convocatoria_id'=>$call]);
        $type = DB::table('ccyf_tipos_documento')->insertGetId(['nombre'=>'Solicitud','nombre_clave'=>'solicitud']);
        $requirement = DB::table('ccyf_requisitos_documento')->insertGetId([
            'servicio_id'=>$service, 'clave'=>'solicitud', 'nombre'=>'Solicitud firmada', 'orden'=>1, 'activo'=>1,
        ]);
        $product = DB::table('ccyf_productos')->insertGetId(['catalogo_id'=>$catalog,'nombre'=>'Ensalada','unidad'=>'porción']);
        $registration = DB::table('ccyf_registros')->insertGetId([
            'convocatoria_id'=>$call, 'catalogo_id'=>$catalog, 'servicio_id'=>$service, 'plantel_id'=>$campus,
            'tipo_documento_id'=>$type, 'usu_id'=>1, 'solicitante'=>'Participante nuevo', 'dirigido_a'=>'Comité',
            'comentarios'=>'Participación', 'estado'=>'Recibido', 'enviado_at'=>'2026-09-22 12:00:00',
        ]);
        DB::table('ccyf_registro_precios')->insert([
            'registro_id'=>$registration, 'producto_id'=>$product, 'producto_nombre'=>'Ensalada', 'unidad'=>'porción', 'precio'=>35,
        ]);
        Storage::disk('local')->put('registros/nuevo/solicitud.pdf', '%PDF-1.4 solicitud');
        DB::table('ccyf_registro_archivos')->insert([
            'registro_id'=>$registration, 'requisito_id'=>$requirement, 'nombre_original'=>'solicitud.pdf',
            'ruta'=>'registros/nuevo/solicitud.pdf', 'mime'=>'application/pdf', 'bytes'=>18,
        ]);

        $this->be(LegacyUser::findOrFail(20));
        $this->get('/prevaluaciones?registro=actual-'.$registration)->assertOk()
            ->assertSee('Participante nuevo')->assertSee('Solicitud firmada')->assertSee('Ensalada')
            ->assertSee('/prevaluaciones/actual-'.$registration.'/documentos/req-'.$requirement);
        $this->get('/prevaluaciones/actual-'.$registration.'/documentos/req-'.$requirement)->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->put('/prevaluaciones/actual-'.$registration, ['resultado'=>'2','items'=>[
            'req-'.$requirement=>['cumple'=>'1','comentario'=>'Correcto'],
            'precio-'.$product=>['cumple'=>'1','comentario'=>'Precio revisado'],
        ]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ccyf_prevaluaciones', ['origen'=>'actual','registro_id'=>$registration,'resultado'=>2]);
        $this->assertDatabaseHas('ccyf_prevaluacion_items', ['clave'=>'precio-'.$product,'comentario'=>'Precio revisado']);
    }

    public function test_historical_assignee_and_comments_are_preserved(): void
    {
        DB::connection('legacy')->table('tm_preval_cafe_doc')->insert([
            'doc_id'=>80, 'usu_preval'=>21, 'est'=>1, 'campo_doc'=>'prop_escrito',
            'comentario'=>'Falta firma', 'cumple'=>0, 'viable_estado'=>2,
            'fecha_registro'=>'2026-06-03 10:00:00',
        ]);
        $this->be(LegacyUser::findOrFail(18));
        $this->get('/prevaluaciones?registro=historico-80')->assertOk()->assertSee('Falta firma')
            ->assertSee('value="2" selected', false);
        $this->be(LegacyUser::findOrFail(20));
        $this->put('/prevaluaciones/historico-80', ['resultado'=>'1','items'=>[
            'prop_escrito'=>['cumple'=>'1','comentario'=>'Alterado'],
        ]])->assertForbidden();
        $this->be(LegacyUser::findOrFail(21));
        $this->put('/prevaluaciones/historico-80', ['resultado'=>'2','items'=>[
            'prop_escrito'=>['cumple'=>'0','comentario'=>'Pendiente de firma'],
        ]])->assertRedirect();
        $this->assertDatabaseHas('ccyf_prevaluacion_items', ['clave'=>'prop_escrito','comentario'=>'Pendiente de firma']);
        $this->assertSame('Falta firma', DB::connection('legacy')->table('tm_preval_cafe_doc')->value('comentario'));
    }
}
