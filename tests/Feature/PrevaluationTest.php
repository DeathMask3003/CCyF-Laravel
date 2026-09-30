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

    public function test_admin_assigns_current_campuses_and_evaluators_only_receive_their_queue(): void
    {
        DB::connection('legacy')->table('tm_documento')->insert(['doc_id'=>82,'trami_id'=>3,'usu_id'=>4,'num_doc'=>9,
            'doc_exter'=>'CEMSAD Sur','doc_estado'=>'Finalizado','fech_crea'=>'2026-06-03 10:00:00']);
        $this->be(LegacyUser::findOrFail(18));
        $this->get('/prevaluaciones')->assertOk()->assertDontSee('data-assignment-row');
        $this->get('/prevaluaciones/asignaciones')->assertOk()->assertSee('Asignación de planteles')
            ->assertSee('CEMSAD Sur')->assertSee('data-assignment-row');
        foreach (['Plantel Centro' => 20, 'CEMSAD Sur' => 21] as $campus => $evaluator) {
            $this->post('/prevaluaciones/asignaciones', ['servicio'=>'cafeteria','convocatoria_id'=>9,
                'plantel'=>$campus,'evaluador_id'=>$evaluator])->assertRedirect();
        }
        $this->assertDatabaseHas('ccyf_prevaluador_planteles', ['servicio'=>'cafeteria',
            'convocatoria_id'=>9,'plantel'=>'Plantel Centro','evaluador_id'=>20]);
        $this->post('/prevaluaciones/asignaciones', ['servicio'=>'cafeteria','convocatoria_id'=>9,
            'plantel'=>'CEMSAD Sur','evaluador_id'=>''])->assertRedirect();
        $this->post('/prevaluaciones/asignaciones', ['servicio'=>'cafeteria','convocatoria_id'=>9,
            'plantel'=>'Plantel Centro','evaluador_id'=>''])->assertStatus(409);
        $this->post('/prevaluaciones/asignaciones', ['servicio'=>'cafeteria','convocatoria_id'=>9,
            'plantel'=>'CEMSAD Sur','evaluador_id'=>21])->assertRedirect();
        $this->post('/prevaluaciones/asignaciones', ['servicio'=>'cafeteria','convocatoria_id'=>8,
            'plantel'=>'Plantel Centro','evaluador_id'=>21])->assertStatus(409);

        $this->be(LegacyUser::findOrFail(20));
        $this->get('/prevaluaciones/asignaciones')->assertForbidden();
        $this->get('/prevaluaciones')->assertOk()->assertSee('Permisionario Uno')
            ->assertSee('Mostrando automáticamente')->assertDontSee('CEMSAD Sur')
            ->assertDontSee('Asignación de planteles');
        $this->get('/prevaluaciones?registro=historico-82')->assertNotFound();
        $this->post('/prevaluaciones/historico-82/tomar')->assertForbidden();
        $this->get('/prevaluaciones/historico-82/reporte.pdf')->assertForbidden();
        $this->post('/prevaluaciones/historico-80/tomar')->assertRedirect();

        $this->be(LegacyUser::findOrFail(18));
        $this->post('/prevaluaciones/asignaciones', ['servicio'=>'cafeteria','convocatoria_id'=>9,
            'plantel'=>'Plantel Centro','evaluador_id'=>21])->assertStatus(409);
        $this->be(LegacyUser::findOrFail(20));
        $this->delete('/prevaluaciones/historico-80/tomar')->assertRedirect();
        $this->be(LegacyUser::findOrFail(18));
        $this->post('/prevaluaciones/asignaciones', ['servicio'=>'cafeteria','convocatoria_id'=>9,
            'plantel'=>'Plantel Centro','evaluador_id'=>21])->assertRedirect();
        $this->be(LegacyUser::findOrFail(20));
        $this->get('/prevaluaciones')->assertOk()->assertDontSee('Permisionario Uno');
        $this->be(LegacyUser::findOrFail(21));
        $this->get('/prevaluaciones')->assertOk()->assertSee('Permisionario Uno')->assertSee('CEMSAD Sur');
    }

    public function test_admin_saves_all_campus_assignments_atomically(): void
    {
        DB::connection('legacy')->table('tm_documento')->insert(['doc_id'=>82,'trami_id'=>3,'usu_id'=>4,'num_doc'=>9,
            'doc_exter'=>'CEMSAD Sur','doc_estado'=>'Finalizado','fech_crea'=>'2026-06-03 10:00:00']);
        $this->be(LegacyUser::findOrFail(18));
        $this->get('/prevaluaciones/asignaciones')->assertOk()->assertSee('Guardar todas las asignaciones')
            ->assertSee('assignments[0][plantel]', false);
        $payload = ['servicio'=>'cafeteria','convocatoria_id'=>9,'assignments'=>[
            ['plantel'=>'Plantel Centro','evaluador_id'=>20],
            ['plantel'=>'CEMSAD Sur','evaluador_id'=>21],
        ]];
        $this->post('/prevaluaciones/asignaciones/todas', $payload)->assertRedirect()
            ->assertSessionHas('status', 'Se guardaron 2 asignaciones.');
        $this->assertDatabaseHas('ccyf_prevaluador_planteles', ['plantel'=>'Plantel Centro','evaluador_id'=>20]);
        $this->assertDatabaseHas('ccyf_prevaluador_planteles', ['plantel'=>'CEMSAD Sur','evaluador_id'=>21]);

        $this->be(LegacyUser::findOrFail(20));
        $this->post('/prevaluaciones/historico-80/tomar')->assertRedirect();
        $this->post('/prevaluaciones/asignaciones/todas', $payload)->assertForbidden();
        $this->be(LegacyUser::findOrFail(18));
        $payload['assignments'][0]['evaluador_id'] = 21;
        $payload['assignments'][1]['evaluador_id'] = 20;
        $this->post('/prevaluaciones/asignaciones/todas', $payload)->assertStatus(409);
        $this->assertDatabaseHas('ccyf_prevaluador_planteles', ['plantel'=>'Plantel Centro','evaluador_id'=>20]);
        $this->assertDatabaseHas('ccyf_prevaluador_planteles', ['plantel'=>'CEMSAD Sur','evaluador_id'=>21]);

        $payload['assignments'][0]['evaluador_id'] = 20;
        $payload['assignments'][1]['evaluador_id'] = null;
        $this->post('/prevaluaciones/asignaciones/todas', $payload)->assertRedirect();
        $this->assertDatabaseMissing('ccyf_prevaluador_planteles', ['plantel'=>'CEMSAD Sur']);
        $payload['assignments'][0]['evaluador_id'] = null;
        $this->post('/prevaluaciones/asignaciones/todas', $payload)->assertStatus(409);
        $this->assertDatabaseHas('ccyf_prevaluador_planteles', ['plantel'=>'Plantel Centro','evaluador_id'=>20]);
        $payload['assignments'][1]['plantel'] = 'Plantel ajeno';
        $this->post('/prevaluaciones/asignaciones/todas', $payload)->assertStatus(422);
    }

    public function test_evaluator_can_save_own_review_and_admin_can_only_save_observation(): void
    {
        $this->be(LegacyUser::findOrFail(20));
        $this->post('/prevaluaciones/historico-80/tomar', ['servicio'=>'cafeteria'])->assertRedirect();
        $this->put('/prevaluaciones/historico-80', ['servicio'=>'cafeteria','resultado'=>'1','items'=>[
            'prop_escrito'=>['cumple'=>'1','comentario'=>'Completo'], 'tlacoyo'=>['cumple'=>'0','comentario'=>'Revisar precio'],
        ]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ccyf_prevaluaciones', ['origen'=>'historico','registro_id'=>80,'evaluador_id'=>20,'resultado'=>1]);
        $this->assertDatabaseHas('ccyf_prevaluacion_items', ['clave'=>'tlacoyo','cumple'=>0]);
        $this->assertSame(0, DB::connection('legacy')->table('tm_preval_cafe_doc')->count());
        $this->put('/prevaluaciones/historico-80/observaciones', ['observaciones'=>'Revisado'])->assertForbidden();
        $this->get('/prevaluaciones?estado=evaluado')->assertOk()->assertSee('Mis prevaluados')
            ->assertSee('Permisionario Uno')->assertSee('Corregir prevaluación');
        $this->get('/prevaluaciones?estado=evaluado&registro=historico-80')->assertOk()
            ->assertSee('Puedes guardar esta prevaluación');
        $this->put('/prevaluaciones/historico-80', ['servicio'=>'cafeteria','estado'=>'evaluado','resultado'=>'3','items'=>[
            'prop_escrito'=>['cumple'=>'0','comentario'=>'Falta firma'],
            'tlacoyo'=>['cumple'=>'1','comentario'=>'Precio corregido'],
        ]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ccyf_prevaluaciones', ['origen'=>'historico','registro_id'=>80,'evaluador_id'=>20,'resultado'=>3]);
        $this->assertDatabaseHas('ccyf_prevaluacion_items', ['clave'=>'prop_escrito','cumple'=>0,'comentario'=>'Falta firma']);
        $this->be(LegacyUser::findOrFail(21));
        $this->get('/prevaluaciones?estado=evaluado')->assertOk()->assertDontSee('Permisionario Uno');
        $this->get('/prevaluaciones?registro=historico-80')->assertNotFound();
        $this->put('/prevaluaciones/historico-80', ['items'=>['prop_escrito'=>['cumple'=>'0']]])->assertForbidden();
        $this->be(LegacyUser::findOrFail(18));
        $this->put('/prevaluaciones/historico-80', ['items'=>['prop_escrito'=>['cumple'=>'0']]])->assertForbidden();
        $this->get('/prevaluaciones')->assertOk()->assertDontSee('Permisionario Uno');
        $this->get('/prevaluaciones?estado=evaluado')->assertOk()->assertSee('Permisionario Uno');
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

    public function test_pdf_viewed_marks_are_saved_per_user_for_evaluator_and_admin(): void
    {
        $this->be(LegacyUser::findOrFail(20));
        $this->get('/prevaluaciones?registro=historico-80')->assertOk()
            ->assertSee('PDF sin revisar')->assertSee('data-viewed-url=', false)
            ->assertSee('data-preval-field="prop_escrito"', false)
            ->assertSee('data-preval-criterion="prop_escrito"', false)
            ->assertSee('id="preval-review-jump"', false);
        $this->post('/prevaluaciones/historico-80/documentos/prop_escrito/visto')->assertNoContent();
        $this->post('/prevaluaciones/historico-80/documentos/prop_escrito/visto')->assertNoContent();
        $this->assertDatabaseCount('ccyf_prevaluacion_vistas', 1);
        $this->get('/prevaluaciones?registro=historico-80')->assertOk()->assertSee('PDF visto');

        $this->be(LegacyUser::findOrFail(18));
        $this->get('/prevaluaciones?registro=historico-80')->assertOk()->assertSee('PDF sin revisar');
        $this->post('/prevaluaciones/historico-80/documentos/prop_escrito/visto')->assertNoContent();
        $this->assertDatabaseCount('ccyf_prevaluacion_vistas', 2);
        $this->get('/prevaluaciones?registro=historico-80')->assertOk()->assertSee('PDF visto');

        $this->be(LegacyUser::findOrFail(20));
        $this->post('/prevaluaciones/historico-80/documentos/inexistente/visto')->assertNotFound();
        $this->be(LegacyUser::findOrFail(1));
        $this->post('/prevaluaciones/historico-80/documentos/prop_escrito/visto')->assertForbidden();
    }

    public function test_prevaluation_report_pdf_is_inline_and_uses_saved_price_review(): void
    {
        $this->be(LegacyUser::findOrFail(20));
        $this->post('/prevaluaciones/historico-80/tomar')->assertRedirect();
        $this->put('/prevaluaciones/historico-80', ['resultado'=>'2','items'=>[
            'prop_escrito'=>['cumple'=>'1','comentario'=>'Documento correcto'],
            'tlacoyo'=>['cumple'=>'0','comentario'=>'Precio fuera de rango'],
        ]])->assertRedirect();
        $this->get('/prevaluaciones?registro=historico-80')->assertOk()
            ->assertSee('Vista previa de prevaluación y precios')
            ->assertSee('/prevaluaciones/historico-80/reporte.pdf');
        $pdf = $this->get('/prevaluaciones/historico-80/reporte.pdf')->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertStringContainsString('inline;', $pdf->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $pdf->headers->get('Cache-Control'));
        $this->get('/prevaluaciones/historico-81/reporte.pdf')->assertOk();
        $this->get('/prevaluaciones/historico-999/reporte.pdf')->assertNotFound();
        $this->be(LegacyUser::findOrFail(1));
        $this->get('/prevaluaciones/historico-80/reporte.pdf')->assertForbidden();
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
        $lastProduct = null;
        for ($number = 1; $number <= 15; $number++) {
            $lastProduct = DB::table('ccyf_productos')->insertGetId([
                'catalogo_id'=>$catalog, 'nombre'=>'Producto '.$number, 'unidad'=>'pieza',
            ]);
            DB::table('ccyf_registro_precios')->insert([
                'registro_id'=>$registration, 'producto_id'=>$lastProduct,
                'producto_nombre'=>'Producto '.$number, 'unidad'=>'pieza', 'precio'=>$number + 10,
            ]);
        }
        Storage::disk('local')->put('registros/nuevo/solicitud.pdf', '%PDF-1.4 solicitud');
        DB::table('ccyf_registro_archivos')->insert([
            'registro_id'=>$registration, 'requisito_id'=>$requirement, 'nombre_original'=>'solicitud.pdf',
            'ruta'=>'registros/nuevo/solicitud.pdf', 'mime'=>'application/pdf', 'bytes'=>18,
        ]);

        $this->be(LegacyUser::findOrFail(20));
        $this->get('/prevaluaciones?registro=actual-'.$registration)->assertOk()
            ->assertSee('Participante nuevo')->assertSee('Solicitud firmada')->assertSee('Ensalada')
            ->assertSee('Producto 15')
            ->assertSee('/prevaluaciones/actual-'.$registration.'/documentos/req-'.$requirement);
        $this->get('/prevaluaciones/actual-'.$registration.'/documentos/req-'.$requirement)->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->post('/prevaluaciones/actual-'.$registration.'/tomar', ['servicio'=>'cafeteria'])->assertRedirect();
        $this->put('/prevaluaciones/actual-'.$registration, ['resultado'=>'2','items'=>[
            'req-'.$requirement=>['cumple'=>'1','comentario'=>'Correcto'],
            'precio-'.$product=>['cumple'=>'1','comentario'=>'Precio revisado'],
            'precio-'.$lastProduct=>['cumple'=>'0','comentario'=>'Revisar producto adicional'],
        ]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ccyf_prevaluaciones', ['origen'=>'actual','registro_id'=>$registration,'resultado'=>2]);
        $this->assertDatabaseHas('ccyf_prevaluacion_items', ['clave'=>'precio-'.$product,'comentario'=>'Precio revisado']);
        $records = app(\App\Services\PrevaluationRecords::class);
        $record = $records->find('actual-'.$registration);
        $detail = $records->detail($record);
        $this->assertCount(16, $detail['prices']);
        $html = view('prevaluaciones.report', compact('record', 'detail') + ['evaluator'=>'Prevaluador'])->render();
        $this->assertStringContainsString('Producto 15', $html);
        $this->assertStringContainsString('Revisar producto adicional', $html);
        $this->get('/prevaluaciones/actual-'.$registration.'/reporte.pdf')->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
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
        $this->post('/prevaluaciones/historico-80/tomar')->assertStatus(409);
        $this->get('/prevaluaciones')->assertOk()->assertDontSee('Permisionario Uno');
        $this->get('/prevaluaciones?estado=evaluado')->assertOk()->assertSee('Permisionario Uno');
        $this->put('/prevaluaciones/historico-80', ['resultado'=>'1','items'=>[
            'prop_escrito'=>['cumple'=>'1','comentario'=>'Documento corregido'],
        ]])->assertRedirect();
        $this->assertDatabaseHas('ccyf_prevaluaciones', ['origen'=>'historico','registro_id'=>80,'evaluador_id'=>21,'resultado'=>1]);
        $this->assertSame('Falta firma', DB::connection('legacy')->table('tm_preval_cafe_doc')->value('comentario'));
    }

    public function test_current_call_and_its_campuses_are_the_only_filter_options(): void
    {
        $service = DB::table('ccyf_tipos_servicio')->insertGetId(['legacy_trami_id'=>3, 'nombre'=>'Cafetería', 'nombre_clave'=>'cafeteria', 'descripcion'=>'Servicio de cafetería']);
        DB::table('ccyf_convocatorias')->insert(['id'=>9, 'legacy_cat_id'=>9, 'numero'=>'Novena convocatoria',
            'numero_clave'=>'novena', 'servicio_id'=>$service, 'activo'=>1]);
        $campus = DB::table('ccyf_planteles')->insertGetId(['nombre'=>'Plantel Centro', 'nombre_clave'=>'plantel-centro']);
        DB::table('ccyf_convocatoria_planteles')->insert(['convocatoria_id'=>9,'plantel_id'=>$campus]);
        DB::connection('legacy')->table('tm_categoria_widi')->insert(['cat_id'=>8,'cat_nom'=>'Convocatoria anterior']);
        DB::connection('legacy')->table('tm_documento')->insert(['doc_id'=>79,'trami_id'=>3,'usu_id'=>4,'num_doc'=>8,
            'doc_exter'=>'Plantel Norte','doc_estado'=>'Finalizado','fech_crea'=>'2026-05-01 10:00:00']);
        $this->be(LegacyUser::findOrFail(20));
        $this->get('/prevaluaciones?convocatoria=8')->assertOk()->assertSee('Novena convocatoria')
            ->assertSee('Plantel Centro')->assertDontSee('Plantel Norte')->assertDontSee('Convocatoria anterior');
    }

    public function test_claim_blocks_another_evaluator_and_completed_review_leaves_the_list(): void
    {
        $this->be(LegacyUser::findOrFail(20));
        $this->post('/prevaluaciones/historico-80/tomar', ['servicio'=>'cafeteria'])->assertRedirect();
        $this->get('/prevaluaciones?registro=historico-80')->assertOk()->assertSee('Liberar expediente');
        $this->be(LegacyUser::findOrFail(21));
        $this->post('/prevaluaciones/historico-80/tomar')->assertStatus(409);
        $this->get('/prevaluaciones')->assertOk()->assertDontSee('Permisionario Uno');
        $this->put('/prevaluaciones/historico-80', ['resultado'=>'1','items'=>['tlacoyo'=>['cumple'=>'1']]])->assertForbidden();
        $this->be(LegacyUser::findOrFail(20));
        $this->put('/prevaluaciones/historico-80', ['resultado'=>'1','items'=>['tlacoyo'=>['cumple'=>'1']]])->assertRedirect();
        $this->get('/prevaluaciones')->assertOk()->assertDontSee('Permisionario Uno');
        $this->post('/prevaluaciones/historico-80/tomar')->assertStatus(409);
        $this->put('/prevaluaciones/historico-80', ['resultado'=>'2','items'=>['tlacoyo'=>['cumple'=>'1']]])->assertRedirect();
        $this->assertDatabaseHas('ccyf_prevaluaciones', ['registro_id'=>80,'evaluador_id'=>20,'resultado'=>2]);
    }

    public function test_price_comparison_modal_shows_total_and_cheapest_item(): void
    {
        DB::connection('legacy')->table('tm_documento')->insert(['doc_id'=>82,'trami_id'=>3,'usu_id'=>4,'num_doc'=>9,
            'doc_exter'=>'Plantel Centro','doc_estado'=>'Finalizado','fech_crea'=>'2026-06-03 10:00:00']);
        DB::connection('legacy')->table('tm_documento_cafeteria')->insert(['doc_id'=>82,'est'=>1,'tlacoyo'=>12]);
        $this->be(LegacyUser::findOrFail(18));
        $this->get('/prevaluaciones?servicio=cafeteria&plantel=Plantel%20Centro&comparar=1')
            ->assertOk()->assertSee('Comparación de precios')->assertSee('Menor precio por producto')
            ->assertSee('$12.00');
    }

    public function test_unfinished_review_can_be_released_for_another_evaluator(): void
    {
        $this->be(LegacyUser::findOrFail(20));
        $this->post('/prevaluaciones/historico-80/tomar')->assertRedirect();
        $this->put('/prevaluaciones/historico-80', ['items'=>['tlacoyo'=>['cumple'=>'1']]])->assertRedirect();
        $this->assertDatabaseHas('ccyf_prevaluaciones', ['registro_id'=>80,'resultado'=>null,'evaluador_id'=>20]);
        $this->delete('/prevaluaciones/historico-80/tomar')->assertRedirect();
        $this->assertDatabaseMissing('ccyf_prevaluaciones', ['registro_id'=>80]);
        $this->be(LegacyUser::findOrFail(21));
        $this->post('/prevaluaciones/historico-80/tomar')->assertRedirect();
        $this->assertDatabaseHas('ccyf_prevaluaciones', ['registro_id'=>80,'evaluador_id'=>21]);
    }
    public function test_admin_summary_lists_all_current_proposals_and_annex_download_is_restricted(): void
    {
        DB::connection('legacy')->table('tm_categoria_widi')->insert(['cat_id'=>8,'cat_nom'=>'Convocatoria anterior']);
        DB::connection('legacy')->table('tm_documento')->insert([
            ['doc_id'=>82,'trami_id'=>3,'usu_id'=>4,'num_doc'=>9,'doc_exter'=>'Plantel Centro','doc_estado'=>'Finalizado','fech_crea'=>'2026-06-03 10:00:00'],
            ['doc_id'=>83,'trami_id'=>3,'usu_id'=>4,'num_doc'=>8,'doc_exter'=>'Plantel Anterior','doc_estado'=>'Finalizado','fech_crea'=>'2026-05-03 10:00:00'],
        ]);
        $this->be(LegacyUser::findOrFail(20));
        $this->post('/prevaluaciones/historico-80/tomar')->assertRedirect();
        $this->put('/prevaluaciones/historico-80', ['resultado'=>'1','items'=>[
            'prop_escrito'=>['cumple'=>'1','comentario'=>'Documento correcto'],
        ]])->assertRedirect();

        $this->be(LegacyUser::findOrFail(18));
        $this->get('/prevaluaciones')->assertOk()->assertSee('Ver prevaluaciones');
        $this->get('/prevaluaciones?servicio=cafeteria&resumen=1')->assertOk()
            ->assertSee('Resumen de prevaluaciones')->assertSee('Evaluación PDF')
            ->assertSee('/prevaluaciones/historico-80/reporte.pdf')
            ->assertSee('PDF pendiente')->assertSee('2 propuestas')
            ->assertDontSee('Plantel Anterior')->assertDontSee('Plantel Norte');
        $pdf = $this->get('/prevaluaciones/anexo-global.pdf?servicio=cafeteria')->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertStringContainsString('attachment;', $pdf->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $pdf->headers->get('Cache-Control'));

        $this->be(LegacyUser::findOrFail(20));
        $this->get('/prevaluaciones?resumen=1')->assertForbidden();
        $this->get('/prevaluaciones/anexo-global.pdf?servicio=cafeteria')->assertForbidden();
    }
}
