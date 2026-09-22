<?php

namespace Tests\Feature;

use App\Models\LegacyUser;
use App\Services\AcceptedProposals;
use App\Services\ContractDocuments;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AcceptedProposalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('database.connections.legacy', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge('sqlite'); DB::purge('legacy');
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true]);
        Schema::connection('legacy')->create('tm_areas', function (Blueprint $table): void {
            $table->integer('area_id')->primary(); $table->string('area_nom'); $table->string('direccion_plantel');
        });
        Schema::connection('legacy')->create('tm_usuario', function (Blueprint $table): void {
            $table->integer('usu_id')->primary(); $table->string('usu_area'); $table->string('usu_correo');
            $table->string('usu_telf')->nullable(); $table->string('direcc')->nullable();
            $table->string('ine2')->nullable(); $table->string('cont_alter')->nullable(); $table->string('telf_alter')->nullable();
        });
        Schema::connection('legacy')->create('tm_categoria_widi', function (Blueprint $table): void {
            $table->integer('cat_id')->primary(); $table->string('cat_nom');
        });
        Schema::connection('legacy')->create('tm_documento', function (Blueprint $table): void {
            $table->integer('doc_id')->primary(); $table->integer('usu_id'); $table->integer('trami_id');
            $table->string('doc_exter'); $table->integer('num_doc'); $table->dateTime('fech_crea');
            $table->date('doc_fech_ini')->nullable(); $table->date('doc_fech_fin')->nullable();
            $table->decimal('monto', 12, 2)->nullable(); $table->string('doc_respuesta');
            $table->boolean('contrato_enviado')->default(false); $table->dateTime('contrato_fech_envio')->nullable();
            $table->string('doc_estado'); $table->integer('est');
        });
        Schema::connection('legacy')->create('tm_plantilla_contrato', function (Blueprint $table): void {
            $table->integer('plantilla_id')->primary(); $table->integer('trami_id'); $table->integer('est');
            $table->longText('plantilla_body'); $table->dateTime('fech_modif')->nullable();
            $table->dateTime('fech_crea')->nullable();
        });
        Schema::connection('legacy')->create('tm_documento_fotocopiado', function (Blueprint $table): void {
            $table->integer('doc_id')->primary(); $table->integer('est')->default(1);
            $table->decimal('carta')->nullable(); $table->decimal('oficio')->nullable();
            $table->decimal('mtc')->nullable(); $table->decimal('mto')->nullable();
        });
        Schema::connection('legacy')->create('tm_documento_cafeteria', function (Blueprint $table): void {
            $table->integer('doc_id')->primary(); $table->integer('est')->default(1);
        });
        DB::connection('legacy')->table('tm_areas')->insert(['area_id'=>1,'area_nom'=>'Plantel Centro','direccion_plantel'=>'Calle Principal 12']);
        DB::connection('legacy')->table('tm_usuario')->insert(['usu_id'=>7,'usu_area'=>'Ana Prueba','usu_correo'=>'ana@example.org']);
        DB::connection('legacy')->table('tm_categoria_widi')->insert(['cat_id'=>9,'cat_nom'=>'Convocatoria 9']);
        DB::connection('legacy')->table('tm_documento')->insert([
            ['doc_id'=>80,'usu_id'=>7,'trami_id'=>3,'doc_exter'=>'Plantel Centro','num_doc'=>9,
                'fech_crea'=>'2026-02-01 10:00:00','doc_fech_ini'=>'2026-03-01','doc_fech_fin'=>'2027-03-01',
                'monto'=>2500,'doc_respuesta'=>'PROPUESTA ACEPTADA','doc_estado'=>'Finalizado','est'=>0],
            ['doc_id'=>81,'usu_id'=>7,'trami_id'=>3,'doc_exter'=>'Plantel Centro','num_doc'=>9,
                'fech_crea'=>'2026-02-02 10:00:00','doc_fech_ini'=>null,'doc_fech_fin'=>null,'monto'=>null,
                'doc_respuesta'=>'PROPUESTA NO ACEPTADA','doc_estado'=>'Finalizado','est'=>0],
            ['doc_id'=>82,'usu_id'=>7,'trami_id'=>3,'doc_exter'=>'Plantel Centro','num_doc'=>9,
                'fech_crea'=>'2026-02-03 10:00:00','doc_fech_ini'=>null,'doc_fech_fin'=>null,'monto'=>null,
                'doc_respuesta'=>'PROPUESTA ACEPTADA','doc_estado'=>'Finalizado','est'=>0],
        ]);
        DB::connection('legacy')->table('tm_documento')->insert([
            'doc_id'=>83,'usu_id'=>7,'trami_id'=>3,'doc_exter'=>'Plantel Centro','num_doc'=>9,
            'fech_crea'=>'2026-02-04 10:00:00','doc_fech_ini'=>'2026-03-01','doc_fech_fin'=>'2027-03-01',
            'monto'=>2500,'doc_respuesta'=>'PROPUESTA ACEPTADA','doc_estado'=>'Finalizado','est'=>0,
            'contrato_enviado'=>1,'contrato_fech_envio'=>null,
        ]);
        DB::connection('legacy')->table('tm_plantilla_contrato')->insert(['plantilla_id'=>1,'trami_id'=>3,
            'est'=>0,'plantilla_body'=>'<h1>CONTRATO</h1><p>{permisionario} prestará servicio en {plantel} por {monto} ({monto_letras}) de {fecha_ini} a {fecha_fin}.</p>',
            'fech_crea'=>'2026-02-01']);
        foreach ([1=>'Otro',2=>'Jurídico'] as $id=>$name) {
            DB::table('ccyf_roles')->insert(['rol_id'=>$id,'rol_nom'=>$name,'legacy_rol_id'=>$id,'est'=>1]);
            DB::table('ccyf_usuarios')->insert(['usu_id'=>$id,'rol_id'=>$id,'usu_area'=>$name,
                'usu_correo'=>$id.'@example.org','usu_pass'=>Hash::make('test-pass'),'est'=>1]);
        }
        DB::table('ccyf_role_permissions')->insert(['rol_id'=>2,'menu_key'=>'Permisionarios_aceptados_vujeig','allowed'=>1]);
        Storage::fake('local');
    }

    public function test_only_authorized_users_see_accepted_records(): void
    {
        $this->be(LegacyUser::findOrFail(1));
        $this->get('/contratos-permisionarios')->assertForbidden();
        $this->get('/contratos-permisionarios/historico-80')->assertForbidden();

        $this->be(LegacyUser::findOrFail(2));
        $this->get('/contratos-permisionarios?servicio=cafeteria')->assertOk()->assertSee('Ana Prueba')
            ->assertSee('02-2026-80')->assertDontSee('02-2026-81');
        $this->get('/contratos-permisionarios/historico-80')->assertOk()->assertSee('Calle Principal 12');
        $this->get('/contratos-permisionarios/historico-81')->assertNotFound();
    }

    public function test_pdf_preview_and_template_version_do_not_change_legacy(): void
    {
        $this->be(LegacyUser::findOrFail(2));
        $preview = $this->get('/contratos-permisionarios/historico-80/borrador')->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $preview->getContent());
        $body = '<h1>Contrato revisado</h1><p>{permisionario} prestará el servicio en {plantel} por una aportación mensual de {monto}, desde {fecha_ini} hasta {fecha_fin}.</p>';
        $this->post('/contratos-permisionarios/plantilla/cafeteria', $this->reviewedTemplate($body))->assertRedirect();
        $this->assertDatabaseHas('ccyf_contract_templates', ['legacy_trami_id'=>3,'body'=>$body,'edited_by'=>2]);
        $this->assertStringContainsString('CONTRATO', DB::connection('legacy')->table('tm_plantilla_contrato')->value('plantilla_body'));
        $version = DB::table('ccyf_contract_templates')->value('id');
        $this->get('/contratos-permisionarios/plantilla/cafeteria?version='.$version)->assertOk()
            ->assertSee('Estás consultando la versión');
        $this->post('/contratos-permisionarios/plantilla/cafeteria', $this->reviewedTemplate($body.' *****'))
            ->assertSessionHasErrors('body');
    }

    public function test_delivery_is_blocked_on_local_mail_and_prevents_duplicates(): void
    {
        $this->be(LegacyUser::findOrFail(2));
        $this->post('/contratos-permisionarios/historico-80/enviar')->assertStatus(409);
        $this->assertDatabaseCount('ccyf_contract_deliveries', 0);

        $body = '<h1>Contrato revisado</h1><p>{permisionario} prestará el servicio en {plantel} por una aportación mensual de {monto}, desde {fecha_ini} hasta {fecha_fin}.</p>';
        $this->post('/contratos-permisionarios/plantilla/cafeteria', $this->reviewedTemplate($body))->assertRedirect();

        config()->set('mail.default', 'smtp');
        config()->set('mail.from.address', 'contracts@example.org');
        Mail::fake();
        $this->post('/contratos-permisionarios/historico-80/enviar')->assertRedirect();
        $this->assertDatabaseHas('ccyf_contract_deliveries', ['origin'=>'historico','registration_id'=>80,
            'status'=>'sent','recipient'=>'ana@example.org']);
        $delivery = DB::table('ccyf_contract_deliveries')->first();
        Storage::disk('local')->assertExists($delivery->pdf_path);
        $this->get('/contratos-permisionarios/historico-80/enviado')->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->post('/contratos-permisionarios/historico-80/enviar')->assertStatus(409);
    }

    public function test_contract_terms_complete_historical_record_without_changing_original(): void
    {
        $this->be(LegacyUser::findOrFail(2));
        $this->get('/contratos-permisionarios/historico-82')->assertOk()->assertSee('Aportación mensual');
        $this->put('/contratos-permisionarios/historico-82/datos', [
            'amount'=>'1800.50','starts'=>'2026-04-01','ends'=>'2027-04-01',
            'campus_address'=>'Calle Principal 12','email'=>'contrato@example.org',
        ])->assertRedirect('/contratos-permisionarios/historico-82')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ccyf_contract_terms', ['origin'=>'historico','registration_id'=>82,
            'amount'=>1800.50,'email'=>'contrato@example.org','updated_by'=>2]);
        $this->assertNull(DB::connection('legacy')->table('tm_documento')->where('doc_id',82)->value('monto'));
        $this->get('/contratos-permisionarios/historico-82')->assertOk()->assertSee('$1,800.50 MXN')
            ->assertSee('contrato@example.org')->assertSee('Historial de ajustes');
        $this->put('/contratos-permisionarios/historico-82/datos', [
            'starts'=>'2027-04-01','ends'=>'2026-04-01',
        ])->assertSessionHasErrors('ends');
    }

    public function test_historical_sent_flag_blocks_changes_even_without_a_date(): void
    {
        $this->be(LegacyUser::findOrFail(2));
        $this->get('/contratos-permisionarios/historico-83')->assertOk()->assertSee('en el sistema anterior');
        $this->put('/contratos-permisionarios/historico-83/datos', ['amount'=>2000])->assertStatus(409);
    }

    public function test_current_photocopy_registration_uses_its_own_product_prices(): void
    {
        $service = DB::table('ccyf_tipos_servicio')->insertGetId([
            'nombre'=>'Fotocopiado','nombre_clave'=>'fotocopiado','descripcion'=>'Servicio de copias',
            'legacy_trami_id'=>4,
        ]);
        $campus = DB::table('ccyf_planteles')->insertGetId([
            'nombre'=>'Plantel Nuevo','nombre_clave'=>'plantel-nuevo','direccion'=>'Calle Nueva 5',
        ]);
        $call = DB::table('ccyf_convocatorias')->insertGetId([
            'numero'=>'Convocatoria nueva','numero_clave'=>'convocatoria-nueva','servicio_id'=>$service,
        ]);
        $catalog = DB::table('ccyf_catalogos')->insertGetId([
            'tipo'=>'fotocopiado','convocatoria_id'=>$call,'servicio_id'=>$service,
        ]);
        $type = DB::table('ccyf_tipos_documento')->insertGetId([
            'nombre'=>'Solicitud','nombre_clave'=>'solicitud',
        ]);
        $registration = DB::table('ccyf_registros')->insertGetId([
            'convocatoria_id'=>$call,'catalogo_id'=>$catalog,'servicio_id'=>$service,
            'plantel_id'=>$campus,'tipo_documento_id'=>$type,'usu_id'=>2,
            'solicitante'=>'Participante nuevo','dirigido_a'=>'Comité','comentarios'=>'Solicitud',
            'estado'=>'Finalizado','decision'=>'designado','enviado_at'=>'2026-09-22 10:00:00',
            'fecha_inicio'=>'2026-10-01','fecha_fin'=>'2027-10-01','monto'=>500,
        ]);
        foreach (['Tamaño carta'=>1.25,'Tamaño oficio'=>1.50,'Mayoreo tamaño carta'=>0.85,
            'Mayoreo tamaño oficio'=>0.95,'Otro producto dinámico'=>2.10] as $name=>$price) {
            $product = DB::table('ccyf_productos')->insertGetId(['catalogo_id'=>$catalog,
                'nombre'=>$name,'unidad'=>'copia','orden'=>1,'activo'=>1]);
            DB::table('ccyf_registro_precios')->insert(['registro_id'=>$registration,'producto_id'=>$product,
                'producto_nombre'=>$name,'precio'=>$price,'unidad'=>'copia']);
        }
        $row = app(AcceptedProposals::class)->find('actual-'.$registration);
        $this->assertNotNull($row);
        $values = app(ContractDocuments::class)->values($row);
        $this->assertSame('$1.25', $values['precio_carta']);
        $this->assertSame('$0.85', $values['precio_carta_mayor']);
        $this->assertSame('$0.95', $values['precio_oficio_mayor']);
        $this->assertSame('Precios disponibles', $values['tabla_precios']);
    }

    private function reviewedTemplate(string $body): array
    {
        return ['body'=>$body,'confirmacion'=>'1',
            'institution_signer'=>'Representante Institucional','institution_role'=>'Apoderado legal',
            'witness_signer'=>'Testigo Institucional','witness_role'=>'Jefatura de departamento'];
    }
}
