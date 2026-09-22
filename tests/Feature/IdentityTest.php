<?php

namespace Tests\Feature;

use App\Models\LegacyUser;
use App\Services\LegacyMenu;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IdentityTest extends TestCase
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

        Schema::connection('legacy')->create('tm_rol', function (Blueprint $table): void {
            $table->increments('rol_id');
            $table->string('rol_nom');
            $table->integer('est');
            $table->dateTime('fech_crea')->nullable();
            $table->dateTime('fech_modif')->nullable();
        });
        Schema::connection('legacy')->create('tm_usuario', function (Blueprint $table): void {
            $table->increments('usu_id');
            $table->string('usu_area');
            $table->string('usu_correo');
            $table->string('usu_pass');
            $table->integer('rol_id');
            $table->integer('area_id')->nullable();
            $table->string('usu_telf')->nullable();
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
            $table->integer('est');
        });

        DB::connection('legacy')->table('tm_rol')->insert([
            ['rol_id' => 1, 'rol_nom' => 'Concursante', 'est' => 1],
            ['rol_id' => 18, 'rol_nom' => 'Administrador', 'est' => 1],
        ]);
        DB::connection('legacy')->table('tm_usuario')->insert([
            ['usu_id' => 1, 'usu_area' => 'Administradora', 'usu_correo' => 'admin@example.test',
                'usu_pass' => Hash::make('clave-original'), 'rol_id' => 18, 'est' => 1],
            ['usu_id' => 2, 'usu_area' => 'Participante', 'usu_correo' => 'participante@example.test',
                'usu_pass' => Hash::make('clave-original'), 'rol_id' => 1, 'est' => 1],
        ]);
        DB::connection('legacy')->table('tm_mennu')->insert([
            ['men_id' => 4, 'men_nom' => 'NuevoOficio', 'est' => 1],
            ['men_id' => 7, 'men_nom' => 'Usuarios', 'est' => 1],
            ['men_id' => 8, 'men_nom' => 'Rol', 'est' => 1],
        ]);
        DB::connection('legacy')->table('td_medu_detalle')->insert([
            ['rol_id' => 18, 'men_id' => 7, 'mend_permi' => 'si'],
            ['rol_id' => 18, 'men_id' => 8, 'mend_permi' => 'si'],
            ['rol_id' => 1, 'men_id' => 4, 'mend_permi' => 'si'],
            ['rol_id' => 99, 'men_id' => 4, 'mend_permi' => 'si'],
        ]);
        DB::connection('legacy')->table('tm_areas')->insert(['area_id' => 23, 'area_nom' => 'Plantel 23', 'est' => 1]);
        $this->artisan('ccyf:import-identity')->assertSuccessful();
    }

    public function test_import_preserves_accounts_and_is_not_reapplied(): void
    {
        $this->assertDatabaseCount('ccyf_usuarios', 2);
        $this->assertDatabaseHas('ccyf_usuarios', ['usu_id' => 2, 'legacy_usu_id' => 2]);
        $this->assertTrue(Hash::check('clave-original', DB::table('ccyf_usuarios')->where('usu_id', 1)->value('usu_pass')));
        $this->assertTrue(app(LegacyMenu::class)->allows(LegacyUser::findOrFail(1), 'Usuarios'));
        DB::table('ccyf_usuarios')->where('usu_id', 1)->update(['usu_area' => 'Nombre editado']);
        $this->artisan('ccyf:import-identity')->assertSuccessful();
        $this->assertDatabaseHas('ccyf_usuarios', ['usu_id' => 1, 'usu_area' => 'Nombre editado']);
    }

    public function test_user_and_role_management_changes_effective_access(): void
    {
        $this->be(LegacyUser::findOrFail(1));
        $this->get('/usuarios')->assertOk()->assertSee('Participante');
        $this->get('/roles')->assertOk()->assertSee('Administrador');

        $this->post('/roles', ['nombre' => 'Revisor CCyF', 'permisos' => ['buscarOficio']])
            ->assertRedirect();
        $role = DB::table('ccyf_roles')->where('rol_nom', 'Revisor CCyF')->first();
        $this->assertNotNull($role);
        $this->post('/usuarios', [
            'nombre' => 'Nueva persona', 'correo' => 'nueva@example.test', 'telefono' => '7221234567',
            'area_id' => 23, 'rol_id' => $role->rol_id,
            'password' => 'clave-segura-123', 'password_confirmation' => 'clave-segura-123',
        ])->assertRedirect();
        $new = DB::table('ccyf_usuarios')->where('usu_correo', 'nueva@example.test')->first();
        $this->assertNotNull($new);
        $this->assertNull($new->legacy_usu_id);
        $this->assertTrue(Hash::check('clave-segura-123', $new->usu_pass));
        $this->assertTrue(app(LegacyMenu::class)->allows(LegacyUser::findOrFail($new->usu_id), 'buscarOficio'));
        $this->assertFalse(app(LegacyMenu::class)->allows(LegacyUser::findOrFail($new->usu_id), 'Usuarios'));

        $this->put('/roles/'.$role->rol_id, ['nombre' => 'Revisor CCyF', 'permisos' => ['NuevoOficio']])
            ->assertRedirect();
        $this->assertFalse(app(LegacyMenu::class)->allows(LegacyUser::findOrFail($new->usu_id), 'buscarOficio'));
        $this->assertTrue(app(LegacyMenu::class)->allows(LegacyUser::findOrFail($new->usu_id), 'NuevoOficio'));

        $this->put('/usuarios/'.$new->usu_id, [
            'nombre' => 'Nueva persona editada', 'correo' => 'nueva@example.test', 'rol_id' => $role->rol_id,
            'password' => '',
        ])->assertRedirect();
        $this->assertDatabaseHas('ccyf_usuarios', ['usu_id' => $new->usu_id, 'usu_area' => 'Nueva persona editada']);
        $this->patch('/usuarios/1/estado')->assertSessionHasErrors('usuario');
        $this->patch('/usuarios/'.$new->usu_id.'/estado')->assertRedirect();
        $this->assertDatabaseHas('ccyf_usuarios', ['usu_id' => $new->usu_id, 'est' => 0]);
        $this->patch('/roles/'.$role->rol_id.'/estado')->assertRedirect();
        $this->assertDatabaseHas('ccyf_roles', ['rol_id' => $role->rol_id, 'est' => 0]);

        Mail::fake();
        $this->post('/salir');
        $this->post('/acceso', ['email' => 'nueva@example.test', 'password' => 'clave-segura-123'])
            ->assertSessionHasErrors('email');
    }

    public function test_non_admin_cannot_manage_accounts_or_roles(): void
    {
        $this->be(LegacyUser::findOrFail(2));
        $this->get('/usuarios')->assertForbidden();
        $this->get('/roles')->assertForbidden();
        $this->post('/roles', ['nombre' => 'Acceso total', 'permisos' => ['Usuarios', 'Rol']])->assertForbidden();
    }
}
