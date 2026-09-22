<?php

namespace Tests\Feature;

use App\Models\LegacyUser;
use App\Services\DocumentTypes;
use App\Services\CcyfStructure;
use App\Services\PriceCatalogs;
use App\Services\RegistrationRequirements;
use App\Services\ServiceTypes;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PriceCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('database.connections.legacy', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge('sqlite');
        DB::purge('legacy');
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true]);

        Schema::connection('legacy')->create('tm_usuario', function (Blueprint $table): void {
            $table->increments('usu_id');
            $table->string('usu_area');
            $table->integer('rol_id');
            $table->string('usu_pass');
        });
        Schema::connection('legacy')->create('tm_categoria_widi', function (Blueprint $table): void {
            $table->increments('cat_id');
            $table->string('cat_nom');
            $table->integer('est');
            $table->dateTime('fech_crea')->nullable();
            $table->dateTime('fech_modif')->nullable();
        });
        Schema::connection('legacy')->create('tm_documento', function (Blueprint $table): void {
            $table->increments('doc_id');
            $table->string('num_doc');
            $table->integer('trami_id');
            $table->integer('tipo_id')->nullable();
            $table->integer('area_id')->nullable();
            $table->integer('usu_id')->nullable();
            $table->string('doc_estado')->nullable();
            $table->string('doc_exter')->nullable();
            $table->string('doc_respuesta')->nullable();
            $table->boolean('doc_designado')->default(false);
            $table->text('doc_descrip')->nullable();
            $table->dateTime('fech_crea')->nullable();
            $table->dateTime('fech_concluido')->nullable();
            $table->date('doc_fech_ini')->nullable();
            $table->date('doc_fech_fin')->nullable();
            $table->decimal('monto', 12, 2)->nullable();
        });
        Schema::connection('legacy')->create('td_documentov3', function (Blueprint $table): void {
            $table->increments('det_id');
            $table->integer('doc_id');
            $table->integer('est')->default(1);
            $table->string('prop_escrito')->nullable();
        });
        Schema::connection('legacy')->create('tm_tipo', function (Blueprint $table): void {
            $table->increments('tipo_id');
            $table->string('tipo_nom', 50);
            $table->integer('est');
            $table->dateTime('fech_crea')->nullable();
            $table->dateTime('fech_modif')->nullable();
        });
        Schema::connection('legacy')->create('tm_tramite', function (Blueprint $table): void {
            $table->increments('trami_id');
            $table->string('trami_nom', 50);
            $table->string('trami_descrip', 200);
            $table->integer('est');
            $table->dateTime('fech_crea')->nullable();
            $table->dateTime('fech_modif')->nullable();
        });
        Schema::connection('legacy')->create('tm_mennu', function (Blueprint $table): void {
            $table->increments('men_id');
            $table->string('men_nom');
            $table->integer('est');
        });
        Schema::connection('legacy')->create('td_medu_detalle', function (Blueprint $table): void {
            $table->increments('mend_id');
            $table->integer('rol_id');
            $table->integer('men_id');
            $table->string('mend_permi');
        });
        Schema::connection('legacy')->create('tm_areas', function (Blueprint $table): void {
            $table->increments('area_id');
            $table->string('area_nom');
            $table->string('area_correo')->nullable();
            $table->string('direccion_plantel')->nullable();
            $table->string('espacio')->nullable();
            $table->integer('matricula')->nullable();
            $table->decimal('monto', 11, 2)->nullable();
            $table->decimal('garantia', 11, 2)->nullable();
            $table->string('espacio_foto')->nullable();
            $table->integer('matricula_foto')->nullable();
            $table->decimal('monto_foto', 11, 2)->nullable();
            $table->decimal('garantia_foto', 11, 2)->nullable();
            $table->integer('est');
            $table->dateTime('fech_crea')->nullable();
            $table->dateTime('fech_modif')->nullable();
        });
        Schema::connection('legacy')->create('tm_subcategoria', function (Blueprint $table): void {
            $table->increments('cats_id');
            $table->integer('cat_id');
            $table->text('cats_nom')->nullable();
        });

        DB::connection('legacy')->table('tm_usuario')->insert([
            ['usu_id' => 1, 'usu_area' => 'Administración', 'rol_id' => 18, 'usu_pass' => 'x'],
            ['usu_id' => 2, 'usu_area' => 'Concursante', 'rol_id' => 9, 'usu_pass' => 'x'],
        ]);
        DB::connection('legacy')->table('tm_categoria_widi')->insert([
            ['cat_id' => 2, 'cat_nom' => 'SEPTIMA', 'est' => 0],
            ['cat_id' => 8, 'cat_nom' => 'Convocatoria de Cafetería', 'est' => 1],
            ['cat_id' => 9, 'cat_nom' => 'Convocatoria de Fotocopiado', 'est' => 1],
        ]);
        DB::connection('legacy')->table('tm_mennu')->insert([
            ['men_id' => 4, 'men_nom' => 'NuevoOficio', 'est' => 1],
            ['men_id' => 9, 'men_nom' => 'Tipo', 'est' => 1],
            ['men_id' => 10, 'men_nom' => 'Asuntos', 'est' => 1],
            ['men_id' => 11, 'men_nom' => 'Areas', 'est' => 1],
            ['men_id' => 16, 'men_nom' => 'Categorias_widi', 'est' => 1],
            ['men_id' => 17, 'men_nom' => 'Subcategorias_widi', 'est' => 1],
            ['men_id' => 5, 'men_nom' => 'gestionOficio', 'est' => 1],
            ['men_id' => 6, 'men_nom' => 'buscarOficio', 'est' => 1],
        ]);
        DB::connection('legacy')->table('td_medu_detalle')->insert([
            ['rol_id' => 18, 'men_id' => 16, 'mend_permi' => 'si'],
            ['rol_id' => 18, 'men_id' => 9, 'mend_permi' => 'si'],
            ['rol_id' => 18, 'men_id' => 10, 'mend_permi' => 'si'],
            ['rol_id' => 18, 'men_id' => 11, 'mend_permi' => 'si'],
            ['rol_id' => 18, 'men_id' => 17, 'mend_permi' => 'si'],
            ['rol_id' => 18, 'men_id' => 5, 'mend_permi' => 'si'],
            ['rol_id' => 18, 'men_id' => 6, 'mend_permi' => 'si'],
            ['rol_id' => 9, 'men_id' => 4, 'mend_permi' => 'si'],
        ]);
        DB::connection('legacy')->table('tm_tipo')->insert([
            ['tipo_id' => 1, 'tipo_nom' => 'Convocatoria', 'est' => 1],
            ['tipo_id' => 2, 'tipo_nom' => 'Oficio Externo', 'est' => 0],
        ]);
        DB::connection('legacy')->table('tm_tramite')->insert([
            ['trami_id' => 3, 'trami_nom' => 'Cafetería', 'trami_descrip' => 'Servicio de alimentos', 'est' => 1],
            ['trami_id' => 4, 'trami_nom' => 'Fotocopiado', 'trami_descrip' => 'Servicio de copias', 'est' => 1],
            ['trami_id' => 5, 'trami_nom' => 'Cafetería y Fotocopiado', 'trami_descrip' => 'Servicio combinado', 'est' => 0],
        ]);
        DB::connection('legacy')->table('tm_areas')->insert([
            'area_id' => 23, 'area_nom' => 'Plantel Atlacomulco', 'area_correo' => 'atlacomulco@cobaemex.edu.mx', 'direccion_plantel' => 'Atlacomulco, México', 'espacio' => '120 m²', 'matricula' => 800, 'monto' => 1000, 'garantia' => 500, 'est' => 1,
        ]);
        DB::connection('legacy')->table('tm_areas')->insert([
            'area_id' => 24, 'area_nom' => 'CEMSaD Acambay', 'area_correo' => 'acambay@cobaemex.edu.mx', 'direccion_plantel' => 'Acambay, México', 'espacio_foto' => '20 m²', 'matricula_foto' => 300, 'monto_foto' => 400, 'garantia_foto' => 200, 'est' => 1,
        ]);
        DB::connection('legacy')->table('tm_areas')->insert([
            'area_id' => 99, 'area_nom' => 'Dirección Académica', 'area_correo' => null, 'direccion_plantel' => null, 'est' => 1,
        ]);
        DB::connection('legacy')->table('tm_subcategoria')->insert([
            ['cat_id' => 2, 'cats_nom' => 'Plantel Atlacomulco'],
            ['cat_id' => 8, 'cats_nom' => 'Plantel Atlacomulco'],
            ['cat_id' => 9, 'cats_nom' => 'CEMSaD Acambay'],
        ]);
        app(DocumentTypes::class)->importLegacy();
        app(ServiceTypes::class)->importLegacy();
        app(CcyfStructure::class)->importLegacy();
    }

    public function test_admin_can_change_one_convocation_without_affecting_another(): void
    {
        $this->asUser(1);
        $this->post('/catalogos/8', ['servicio_id' => 1])->assertRedirect('/catalogos/8');
        $this->post('/catalogos/9', ['servicio_id' => 2])->assertRedirect('/catalogos/9');
        $this->post('/catalogos/8/productos', ['nombre' => 'Fruta picada', 'unidad' => 'vaso'])
            ->assertSessionHasNoErrors();

        $cafeteria = DB::table('ccyf_catalogos')->where('legacy_cat_id', 8)->value('id');
        $fotocopia = DB::table('ccyf_catalogos')->where('legacy_cat_id', 9)->value('id');
        $item = DB::table('ccyf_productos')->where('catalogo_id', $cafeteria)->where('nombre', 'Fruta picada')->first();
        $this->assertNotNull($item);
        $this->assertFalse(DB::table('ccyf_productos')->where('catalogo_id', $fotocopia)->where('nombre', 'Fruta picada')->exists());

        $this->put("/catalogos/8/productos/{$item->id}", [
            'nombre' => 'Fruta picada', 'unidad' => 'vaso', 'orden' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertEquals(0, DB::table('ccyf_productos')->where('id', $item->id)->value('activo'));
        $this->get('/nuevo-oficio/8')->assertOk()->assertDontSee('Fruta picada');
        $this->get('/nuevo-oficio/9')->assertOk()->assertSee('Tamaño carta');
    }

    public function test_proposal_draft_uses_only_current_products_and_rejects_extra_prices(): void
    {
        $catalog = app(PriceCatalogs::class)->prepare(8);
        $products = DB::table('ccyf_productos')->where('catalogo_id', $catalog)->pluck('id');
        $prices = $products->mapWithKeys(fn ($id) => [$id => '12.50'])->all();
        $this->asUser(2);

        $this->post('/nuevo-oficio/8/precios', ['tipo_documento_id' => 1, 'plantel_id' => 23, 'precios' => $prices + [99999 => '0.01']])
            ->assertSessionHasErrors('precios');
        $this->assertDatabaseCount('ccyf_precio_borradores', 0);

        $this->post('/nuevo-oficio/8/precios', ['tipo_documento_id' => 1, 'plantel_id' => 23, 'precios' => $prices])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('ccyf_precio_borrador_detalles', $products->count());
        $this->assertDatabaseHas('ccyf_precio_borradores', ['tipo_documento_id' => 1, 'usu_id' => 2]);
        $this->get('/nuevo-oficio/8')->assertOk()->assertSee('12.50');
    }

    public function test_concursante_cannot_edit_catalog_and_admin_cannot_save_a_proposal(): void
    {
        app(PriceCatalogs::class)->prepare(8);
        $this->asUser(2);
        $this->get('/catalogos')->assertForbidden();
        $this->post('/catalogos/8/productos', ['nombre' => 'Nuevo'])->assertForbidden();

        $this->asUser(1);
        $this->post('/nuevo-oficio/8/precios', ['precios' => []])->assertForbidden();
    }

    public function test_document_types_are_imported_once_and_administrator_can_edit_them(): void
    {
        $this->assertSame(0, app(DocumentTypes::class)->importLegacy());
        $this->assertDatabaseCount('ccyf_tipos_documento', 2);
        $this->asUser(1);
        $this->get('/tipos-documento')->assertOk()->assertSee('Convocatoria')->assertSee('Oficio Externo');
        $this->post('/tipos-documento', ['nombre' => 'convocatoria'])->assertSessionHasErrors('nombre');
        $this->put('/tipos-documento/1', ['nombre' => 'Convocatoria'])->assertSessionHasNoErrors();
        $this->post('/tipos-documento', ['nombre' => 'Aviso'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ccyf_tipos_documento', ['nombre' => 'Aviso', 'activo' => 1]);
    }

    public function test_inactive_types_are_excluded_and_last_active_type_cannot_be_deactivated(): void
    {
        $catalog = app(PriceCatalogs::class)->prepare(8);
        $prices = DB::table('ccyf_productos')->where('catalogo_id', $catalog)
            ->pluck('id')->mapWithKeys(fn ($id) => [$id => '10.00'])->all();
        $this->asUser(1);
        $this->patch('/tipos-documento/1/estado')->assertSessionHasErrors('tipo');
        $this->patch('/tipos-documento/2/estado')->assertSessionHasNoErrors();
        $this->patch('/tipos-documento/1/estado')->assertSessionHasNoErrors();

        $this->asUser(2);
        $this->get('/nuevo-oficio/8')->assertOk()->assertSee('Oficio Externo')->assertDontSee('<option value="1"', false);
        $this->post('/nuevo-oficio/8/precios', ['tipo_documento_id' => 1, 'plantel_id' => 23, 'precios' => $prices])
            ->assertSessionHasErrors('tipo_documento_id');
        $this->post('/nuevo-oficio/8/precios', ['tipo_documento_id' => 2, 'plantel_id' => 23, 'precios' => $prices])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ccyf_precio_borradores', ['tipo_documento_id' => 2, 'usu_id' => 2]);
    }

    public function test_concursante_cannot_manage_document_types(): void
    {
        $this->asUser(2);
        $this->get('/tipos-documento')->assertForbidden();
        $this->post('/tipos-documento', ['nombre' => 'Otro'])->assertForbidden();
        $this->patch('/tipos-documento/1/estado')->assertForbidden();
    }

    public function test_service_types_import_once_and_keep_legacy_usage(): void
    {
        DB::connection('legacy')->table('tm_documento')->insert([
            'num_doc' => '8', 'trami_id' => 3, 'tipo_id' => 1,
        ]);
        $this->assertSame(0, app(ServiceTypes::class)->importLegacy());
        $this->assertDatabaseCount('ccyf_tipos_servicio', 3);

        $this->asUser(1);
        $this->get('/tipos-servicios')->assertOk()
            ->assertSee('Cafetería')->assertSee('Fotocopiado')->assertSee('1 oficios anteriores');
    }

    public function test_administrator_can_manage_services_and_names_update_new_office(): void
    {
        app(PriceCatalogs::class)->prepare(8);
        $this->asUser(1);
        $this->post('/tipos-servicios', [
            'nombre' => 'cafetería', 'descripcion' => 'Duplicado',
        ])->assertSessionHasErrors('nombre');
        $this->post('/tipos-servicios', [
            'nombre' => 'Máquinas expendedoras', 'descripcion' => 'Venta automatizada',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ccyf_tipos_servicio', ['nombre' => 'Máquinas expendedoras', 'activo' => 1]);
        $this->put('/tipos-servicios/1', [
            'nombre' => 'Cafetería escolar', 'descripcion' => 'Servicio de alimentos en plantel',
        ])->assertSessionHasNoErrors();
        $this->get('/nuevo-oficio')->assertOk()->assertSee('Cafetería escolar');
        $this->get('/nuevo-oficio/8')->assertOk()->assertSee('Cafetería escolar');
    }

    public function test_inactive_service_is_not_available_for_new_offices_or_catalogs(): void
    {
        app(PriceCatalogs::class)->prepare(8);
        $this->asUser(1);
        $this->patch('/tipos-servicios/1/estado')->assertSessionHasNoErrors();
        $this->get('/nuevo-oficio')->assertOk()->assertDontSee('Convocatoria de Cafetería');
        $this->get('/nuevo-oficio/8')->assertNotFound();
        $this->get('/catalogos/9')->assertOk()->assertDontSee('<option value="1"', false);
    }

    public function test_last_service_cannot_be_deactivated_and_concursante_cannot_manage_services(): void
    {
        $this->asUser(1);
        $this->patch('/tipos-servicios/1/estado')->assertSessionHasNoErrors();
        $this->patch('/tipos-servicios/2/estado')->assertSessionHasErrors('servicio');

        $this->asUser(2);
        $this->get('/tipos-servicios')->assertForbidden();
        $this->post('/tipos-servicios', ['nombre' => 'Otro', 'descripcion' => 'Otro'])->assertForbidden();
        $this->patch('/tipos-servicios/2/estado')->assertForbidden();
    }

    public function test_legacy_planteles_and_convocations_are_imported_once_with_real_assignments(): void
    {
        $result = app(CcyfStructure::class)->importLegacy();

        $this->assertSame(['campuses' => 0, 'convocations' => 0, 'assignments' => 0], $result);
        $this->assertDatabaseCount('ccyf_planteles', 2);
        $this->assertDatabaseCount('ccyf_convocatorias', 3);
        $this->assertDatabaseCount('ccyf_convocatoria_planteles', 3);
        $this->assertDatabaseHas('ccyf_convocatorias', [
            'legacy_cat_id' => 2, 'numero' => 'SEPTIMA', 'servicio_id' => null, 'activo' => 0,
        ]);
        $this->assertDatabaseHas('ccyf_plantel_servicios', [
            'plantel_id' => 23, 'servicio_id' => 1, 'matricula' => 800,
        ]);
        $this->assertDatabaseHas('ccyf_convocatoria_planteles', [
            'convocatoria_id' => 9, 'plantel_id' => 24,
        ]);
    }

    public function test_administrator_can_manage_campuses_and_their_service_conditions(): void
    {
        $this->asUser(1);
        $this->get('/planteles')->assertOk()->assertSee('Plantel Atlacomulco')->assertSee('CEMSaD Acambay');
        $this->post('/planteles', [
            'nombre' => 'Plantel Metepec',
            'correo' => 'metepec@cobaemex.edu.mx',
            'direccion' => 'Metepec, México',
            'servicios' => [1 => [
                'habilitado' => 1, 'espacio' => '95 m²', 'matricula' => 640,
                'monto' => '1250.50', 'garantia' => '600.00',
            ]],
        ])->assertSessionHasNoErrors();

        $campusId = DB::table('ccyf_planteles')->where('nombre', 'Plantel Metepec')->value('id');
        $this->assertNotNull($campusId);
        $this->assertDatabaseHas('ccyf_plantel_servicios', ['plantel_id' => $campusId, 'servicio_id' => 1]);
        $this->put("/planteles/{$campusId}", [
            'nombre' => 'Plantel Metepec Centro', 'correo' => 'centro@cobaemex.edu.mx',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ccyf_planteles', ['id' => $campusId, 'nombre' => 'Plantel Metepec Centro']);
        $this->patch("/planteles/{$campusId}/estado")->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ccyf_planteles', ['id' => $campusId, 'activo' => 0]);
    }

    public function test_administrator_can_manage_convocations_and_assign_campuses(): void
    {
        $this->asUser(1);
        $this->get('/convocatorias')->assertOk()->assertSee('Convocatoria de Cafetería')->assertSee('1 planteles');
        $this->post('/convocatorias', [
            'numero' => 'Sexta-2027-Cafetería', 'servicio_id' => 1,
        ])->assertSessionHasNoErrors();

        $id = DB::table('ccyf_convocatorias')->where('numero', 'Sexta-2027-Cafetería')->value('id');
        $this->assertNotNull($id);
        $this->assertDatabaseHas('ccyf_convocatorias', ['id' => $id, 'activo' => 0]);
        $this->put("/enlaces-convocatoria/{$id}", ['planteles' => [23, 24]])->assertSessionHasNoErrors();
        $this->assertSame(2, DB::table('ccyf_convocatoria_planteles')->where('convocatoria_id', $id)->count());
        $this->put("/convocatorias/{$id}", [
            'numero' => 'Sexta-2027', 'servicio_id' => 2,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ccyf_convocatorias', ['id' => $id, 'numero' => 'Sexta-2027', 'servicio_id' => 2]);
        $this->patch("/convocatorias/{$id}/estado")->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ccyf_convocatorias', ['id' => $id, 'activo' => 1]);
    }

    public function test_service_cannot_change_after_convocation_products_are_configured(): void
    {
        app(PriceCatalogs::class)->prepare(8);
        $this->asUser(1);

        $this->put('/convocatorias/8', [
            'numero' => 'Convocatoria de Cafetería', 'servicio_id' => 2,
        ])->assertSessionHasErrors('servicio_id');
        $this->assertDatabaseHas('ccyf_convocatorias', ['id' => 8, 'servicio_id' => 1]);
    }

    public function test_historical_convocation_without_service_cannot_be_activated_until_completed(): void
    {
        $this->asUser(1);

        $this->patch('/convocatorias/2/estado')->assertSessionHasErrors('convocatoria');
        $this->assertDatabaseHas('ccyf_convocatorias', ['id' => 2, 'activo' => 0, 'servicio_id' => null]);
        $this->put('/convocatorias/2', [
            'numero' => 'SEPTIMA', 'servicio_id' => 1,
        ])->assertSessionHasNoErrors();
        $this->patch('/convocatorias/2/estado')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ccyf_convocatorias', ['id' => 2, 'activo' => 1, 'servicio_id' => 1]);
    }

    public function test_new_office_accepts_only_campuses_assigned_to_the_convocation(): void
    {
        $catalog = app(PriceCatalogs::class)->prepare(8);
        $prices = DB::table('ccyf_productos')->where('catalogo_id', $catalog)
            ->pluck('id')->mapWithKeys(fn ($id) => [$id => '18.50'])->all();
        $this->asUser(2);

        $this->get('/nuevo-oficio/8')->assertOk()
            ->assertSee('Plantel Atlacomulco')->assertDontSee('CEMSaD Acambay');
        $this->post('/nuevo-oficio/8/precios', [
            'tipo_documento_id' => 1, 'plantel_id' => 24, 'precios' => $prices,
        ])->assertSessionHasErrors('plantel_id');
        $this->post('/nuevo-oficio/8/precios', [
            'tipo_documento_id' => 1, 'plantel_id' => 23, 'precios' => $prices,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ccyf_precio_borradores', ['usu_id' => 2, 'plantel_id' => 23]);
    }

    public function test_concursante_cannot_manage_campuses_or_convocations(): void
    {
        $this->asUser(2);
        $this->get('/planteles')->assertForbidden();
        $this->post('/planteles', ['nombre' => 'Plantel Nuevo'])->assertForbidden();
        $this->get('/convocatorias')->assertForbidden();
        $this->post('/convocatorias', [
            'numero' => 'Nueva', 'servicio_id' => 1, 'planteles' => [23],
        ])->assertForbidden();
        $this->get('/enlaces-convocatoria')->assertForbidden();
        $this->put('/enlaces-convocatoria/8', ['planteles' => [23]])->assertForbidden();
    }

    public function test_links_module_updates_only_the_selected_convocation(): void
    {
        $this->asUser(1);
        $this->get('/enlaces-convocatoria')->assertOk()
            ->assertSee('Convocatoria de Cafetería')->assertSee('1 plantel vinculado');
        $this->get('/enlaces-convocatoria/8/editar')->assertOk()
            ->assertSee('Plantel Atlacomulco')->assertSee('CEMSaD Acambay');
        $this->put('/enlaces-convocatoria/8', ['planteles' => [23, 24]])
            ->assertRedirect('/enlaces-convocatoria')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ccyf_convocatoria_planteles', ['convocatoria_id' => 8, 'plantel_id' => 24]);
        $this->assertDatabaseHas('ccyf_convocatoria_planteles', ['convocatoria_id' => 9, 'plantel_id' => 24]);
    }

    public function test_complete_new_registration_stores_prices_documents_and_folio(): void
    {
        Storage::fake('local');
        $catalog = app(PriceCatalogs::class)->prepare(8);
        $products = DB::table('ccyf_productos')->where('catalogo_id', $catalog)->pluck('id');
        $prices = $products->mapWithKeys(fn ($id) => [$id => '22.50'])->all();
        $requirements = app(RegistrationRequirements::class)->activeFor(1);
        $documents = $requirements->mapWithKeys(fn ($requirement) => [
            $requirement->clave => UploadedFile::fake()->create($requirement->clave.'.pdf', 120, 'application/pdf'),
        ])->all();
        $this->asUser(2);

        $this->post('/nuevo-oficio/8/registrar', [
            'tipo_documento_id' => 1,
            'plantel_id' => 23,
            'comentarios' => 'Presento mi propuesta completa para la convocatoria.',
            'precios' => $prices,
            'documentos' => $documents,
        ])->assertRedirect('/nuevo-oficio/registros/1')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ccyf_registros', [
            'id' => 1, 'folio' => 'CCYF-'.now()->format('Y').'-00001', 'usu_id' => 2,
            'convocatoria_id' => 8, 'plantel_id' => 23, 'estado' => 'Recibido',
        ]);
        $this->assertDatabaseCount('ccyf_registro_precios', $products->count());
        $this->assertDatabaseCount('ccyf_registro_archivos', $requirements->count());
        $this->assertCount($requirements->count(), Storage::disk('local')->allFiles('ccyf/registros/1'));
        $this->get('/nuevo-oficio/registros/1')->assertOk()->assertSee('CCYF-'.now()->format('Y').'-00001');
    }

    public function test_complete_registration_rejects_missing_documents_and_duplicates(): void
    {
        Storage::fake('local');
        $catalog = app(PriceCatalogs::class)->prepare(8);
        $products = DB::table('ccyf_productos')->where('catalogo_id', $catalog)->pluck('id');
        $prices = $products->mapWithKeys(fn ($id) => [$id => '10.00'])->all();
        $requirements = app(RegistrationRequirements::class)->activeFor(1);
        $this->asUser(2);

        $base = [
            'tipo_documento_id' => 1, 'plantel_id' => 23,
            'comentarios' => 'Propuesta de prueba', 'precios' => $prices,
        ];
        $this->post('/nuevo-oficio/8/registrar', $base)->assertSessionHasErrors('documentos');
        $this->assertDatabaseCount('ccyf_registros', 0);

        $base['documentos'] = $requirements->mapWithKeys(fn ($requirement) => [
            $requirement->clave => UploadedFile::fake()->create($requirement->clave.'.pdf', 80, 'application/pdf'),
        ])->all();
        $this->post('/nuevo-oficio/8/registrar', $base)->assertSessionHasNoErrors();
        $base['documentos'] = $requirements->mapWithKeys(fn ($requirement) => [
            $requirement->clave => UploadedFile::fake()->create('duplicate-'.$requirement->clave.'.pdf', 80, 'application/pdf'),
        ])->all();
        $this->post('/nuevo-oficio/8/registrar', $base)->assertSessionHasErrors('plantel_id');
        $this->assertDatabaseCount('ccyf_registros', 1);
    }

    public function test_pending_review_requires_permission_and_designation_data(): void
    {
        $record = $this->reviewRecord();
        $this->asUser(2);
        $this->get('/convocatorias-pendientes')->assertForbidden();
        $this->post("/expedientes/{$record}/finalizar", [
            'decision' => 'no_aceptado', 'respuesta' => 'No cumple con los requisitos.',
        ])->assertForbidden();
        $this->get("/expedientes/{$record}")->assertOk()->assertSee('CCYF-2026-00001');

        $this->asUser(1);
        $this->get('/convocatorias-pendientes')->assertOk()->assertSee('CCYF-2026-00001');
        $this->post("/expedientes/{$record}/finalizar", [
            'decision' => 'designado', 'respuesta' => 'Se designa al participante.',
        ])->assertSessionHasErrors(['fecha_inicio', 'fecha_fin', 'monto']);
        $this->assertDatabaseHas('ccyf_registros', ['id' => $record, 'estado' => 'Recibido']);

        $this->post("/expedientes/{$record}/finalizar", [
            'decision' => 'designado', 'respuesta' => 'Se designa al participante.',
            'fecha_inicio' => '2026-10-01', 'fecha_fin' => '2027-09-30', 'monto' => '1200.50',
        ])->assertRedirect('/convocatorias-finalizadas');
        $this->assertDatabaseHas('ccyf_registros', [
            'id' => $record, 'estado' => 'Finalizado', 'decision' => 'designado',
            'monto' => '1200.5', 'revisado_por' => 1,
        ]);
        $this->post("/expedientes/{$record}/finalizar", [
            'decision' => 'no_aceptado', 'respuesta' => 'Otra respuesta.',
        ])->assertStatus(409);
        $this->get('/convocatorias-finalizadas')->assertOk()->assertSee('CCYF-2026-00001');
    }

    public function test_bulk_rejection_is_atomic_and_historical_result_is_read_only(): void
    {
        $record = $this->reviewRecord();
        $this->asUser(1);
        $this->post('/convocatorias-pendientes/no-aceptadas', [
            'registros' => [$record, 9999], 'respuesta' => 'PROPUESTA NO ACEPTADA',
        ])->assertSessionHasErrors('registros');
        $this->assertDatabaseHas('ccyf_registros', ['id' => $record, 'estado' => 'Recibido']);
        $this->post('/convocatorias-pendientes/no-aceptadas', [
            'registros' => [$record], 'respuesta' => 'PROPUESTA NO ACEPTADA',
        ])->assertRedirect('/convocatorias-finalizadas');
        $this->assertDatabaseHas('ccyf_registros', ['id' => $record, 'decision' => 'no_aceptado']);

        DB::connection('legacy')->table('tm_documento')->insert([
            'doc_id' => 700, 'num_doc' => '8', 'trami_id' => 3, 'tipo_id' => 1,
            'area_id' => 23, 'usu_id' => 2, 'doc_estado' => 'Finalizado',
            'doc_exter' => 'Plantel Atlacomulco', 'doc_respuesta' => 'No aceptada.',
            'fech_crea' => '2026-01-01 10:00:00', 'fech_concluido' => '2026-01-15 10:00:00',
        ]);
        $this->get('/convocatorias-finalizadas')->assertOk()->assertSee('HIST-00700');
        $this->get('/convocatorias-finalizadas/historico/700')->assertOk()->assertSee('No aceptada.');
        $this->get('/convocatorias-finalizadas?buscar=HIST-00700')->assertOk()->assertSee('HIST-00700');
        $this->get('/convocatorias-finalizadas?servicio=1')->assertOk()->assertSee('HIST-00700');
        $this->assertDatabaseCount('ccyf_registros', 1);
        $this->asUser(2);
        $this->get('/convocatorias-finalizadas')->assertOk()->assertSee('HIST-00700');
        $this->get('/convocatorias-finalizadas/historico/700')->assertOk();
    }

    public function test_historical_pdf_is_served_only_from_its_record(): void
    {
        Storage::fake('local');
        $root = Storage::disk('local')->path('historical');
        mkdir($root.DIRECTORY_SEPARATOR.'700', 0777, true);
        file_put_contents($root.DIRECTORY_SEPARATOR.'700'.DIRECTORY_SEPARATOR.'propuesta.pdf', '%PDF-1.4 test');
        config()->set('ccyf.legacy_files_root', $root);
        DB::connection('legacy')->table('tm_documento')->insert([
            'doc_id' => 700, 'num_doc' => '8', 'trami_id' => 3, 'tipo_id' => 1,
            'usu_id' => 2, 'doc_estado' => 'Finalizado',
        ]);
        DB::connection('legacy')->table('td_documentov3')->insert([
            'doc_id' => 700, 'est' => 1, 'prop_escrito' => 'propuesta.pdf',
        ]);
        $this->asUser(1);
        $this->get('/convocatorias-finalizadas/historico/700/archivo/prop_escrito')->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename=prop_escrito-700.pdf');
        $this->get('/convocatorias-finalizadas/historico/700/archivo/acta_nac')->assertNotFound();
    }

    private function reviewRecord(): int
    {
        $catalog = app(PriceCatalogs::class)->prepare(8);
        return DB::table('ccyf_registros')->insertGetId([
            'folio' => 'CCYF-2026-00001', 'convocatoria_id' => 8, 'catalogo_id' => $catalog,
            'servicio_id' => 1, 'plantel_id' => 23, 'tipo_documento_id' => 1,
            'usu_id' => 2, 'solicitante' => 'Concursante', 'dirigido_a' => 'Dirección',
            'comentarios' => 'Propuesta de prueba', 'estado' => 'Recibido',
            'enviado_at' => '2026-09-22 12:00:00', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function asUser(int $id): void
    {
        $this->be(LegacyUser::query()->findOrFail($id));
    }
}
