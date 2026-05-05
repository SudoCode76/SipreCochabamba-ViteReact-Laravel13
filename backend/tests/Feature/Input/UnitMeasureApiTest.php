<?php

namespace Tests\Feature\Input;

use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithLegacyAuth;
use Tests\Concerns\InteractsWithLegacyInputs;
use Tests\TestCase;

class UnitMeasureApiTest extends TestCase
{
    use InteractsWithLegacyAuth;
    use InteractsWithLegacyInputs;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLegacyAuthSchema();
        $this->setUpLegacyInputSchema();
    }

    public function test_admin_can_list_context_show_create_update_and_delete_unit_measures(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createUnitMeasure(['id_unidad_medida' => 1, 'descripcion' => 'Pieza', 'abreviatura' => 'pza.', 'estado' => 'AC']);
        $this->createUnitMeasure(['id_unidad_medida' => 2, 'descripcion' => 'Metro', 'abreviatura' => 'm', 'estado' => 'DC']);
        $this->createUnitMeasure(['id_unidad_medida' => 3, 'descripcion' => 'Unidad Eliminada', 'abreviatura' => 'ue', 'estado' => 'DP']);

        $this->getJson('/api/v1/unit-measures')
            ->assertOk()
            ->assertJsonPath('data.items.0.id_unidad_medida', 2)
            ->assertJsonPath('data.items.0.status_label', 'INACTIVO')
            ->assertJsonPath('data.items.0.available_actions.edit', true)
            ->assertJsonPath('data.items.0.available_actions.delete', true)
            ->assertJsonPath('data.items.1.id_unidad_medida', 1)
            ->assertJsonMissing(['id_unidad_medida' => 3]);

        $this->getJson('/api/v1/unit-measures/context')
            ->assertOk()
            ->assertJsonPath('data.statuses.0.code', 'AC')
            ->assertJsonPath('data.statuses.1.code', 'DC')
            ->assertJsonPath('data.permissions.can_delete', true);

        $this->getJson('/api/v1/unit-measures/2')
            ->assertOk()
            ->assertJsonPath('data.unit_measure.id_unidad_medida', 2)
            ->assertJsonPath('data.unit_measure.descripcion', 'Metro');

        $this->postJson('/api/v1/unit-measures', [
            'descripcion' => '  Litro  ',
            'abreviatura' => '  lt  ',
            'estado' => 'AC',
        ])->assertCreated()
            ->assertJsonPath('data.unit_measure.descripcion', 'Litro')
            ->assertJsonPath('data.unit_measure.abreviatura', 'lt');

        $this->putJson('/api/v1/unit-measures/2', [
            'descripcion' => 'Metro lineal',
            'abreviatura' => 'ml',
            'estado' => 'AC',
        ])->assertOk()
            ->assertJsonPath('data.unit_measure.descripcion', 'Metro lineal')
            ->assertJsonPath('data.unit_measure.status_label', 'ACTIVO');

        $this->postJson('/api/v1/unit-measures/2/delete-authorization-request', [
            'nro_autorizacion' => 'AUTH-UM-1',
        ])->assertCreated()
            ->assertJsonPath('data.authorization.estado', 'PE');

        $this->getJson('/api/v1/unit-measures/2/delete-authorization-status')
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.usable', false);

        $this->createAuthorization([
            'id_autorizacion' => 2,
            'id_elemento' => 2,
            'elemento' => 'Metro lineal',
            'tipo_elemento' => 'unidad_medida',
            'tabla' => 'unidad_medida',
            'solicitante' => 1,
            'estado' => 'AP',
            'nro_autorizacion' => 'AUTH-OK',
        ]);

        $this->deleteJson('/api/v1/unit-measures/2', [
            'autorizacion' => 'AUTH-OK',
        ])->assertOk()
            ->assertJsonPath('data.unit_measure.estado', 'DP');
    }

    public function test_unit_measure_description_and_abbreviation_must_be_unique_except_deleted(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createUnitMeasure(['id_unidad_medida' => 1, 'descripcion' => 'Pieza', 'abreviatura' => 'pza.', 'estado' => 'AC']);
        $this->createUnitMeasure(['id_unidad_medida' => 2, 'descripcion' => 'Metro', 'abreviatura' => 'm', 'estado' => 'DP']);

        $this->postJson('/api/v1/unit-measures', [
            'descripcion' => 'Pieza',
            'abreviatura' => 'pz',
            'estado' => 'AC',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.descripcion.0', 'Ya existe una unidad de medida con la misma descripcion.');

        $this->postJson('/api/v1/unit-measures', [
            'descripcion' => 'Litro',
            'abreviatura' => 'pza.',
            'estado' => 'AC',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.abreviatura.0', 'Ya existe una unidad de medida con la misma abreviatura.');

        $this->postJson('/api/v1/unit-measures', [
            'descripcion' => 'Metro',
            'abreviatura' => 'm',
            'estado' => 'AC',
        ])->assertCreated();
    }

    public function test_unit_measure_endpoints_require_authentication(): void
    {
        $this->createUnitMeasure();

        $this->getJson('/api/v1/unit-measures')->assertUnauthorized();
        $this->getJson('/api/v1/unit-measures/context')->assertUnauthorized();
        $this->postJson('/api/v1/unit-measures', [])->assertUnauthorized();
        $this->getJson('/api/v1/unit-measures/1')->assertUnauthorized();
        $this->putJson('/api/v1/unit-measures/1', [])->assertUnauthorized();
        $this->deleteJson('/api/v1/unit-measures/1', [])->assertUnauthorized();
        $this->postJson('/api/v1/unit-measures/1/delete-authorization-request', [])->assertUnauthorized();
        $this->getJson('/api/v1/unit-measures/1/delete-authorization-status')->assertUnauthorized();
    }

    public function test_non_admin_cannot_manage_unit_measures(): void
    {
        $this->createLegacyAuthUser();
        $this->createUnitMeasure();

        $userRole = Role::query()->create([
            'id_rol' => 2,
            'nombre_rol' => 'Tecnico',
            'estado' => 'AC',
        ]);

        $unit = Unit::query()->firstOrCreate([
            'id_unidad' => 2,
        ], [
            'descripcion' => 'Unidad Tecnica',
            'estado' => 'AC',
        ]);

        $user = User::query()->create([
            'id_usuario' => 2,
            'funcionario' => 'Usuario Tecnico',
            'ci' => '87654321',
            'username' => 'tecnico',
            'clave' => Hash::make('secret123'),
            'estado' => 'AC',
            'id_unidad' => $unit->id_unidad,
            'rol' => $userRole->id_rol,
            'fecha' => now()->toDateString(),
            'subalcaldia' => null,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/unit-measures')->assertForbidden();
        $this->postJson('/api/v1/unit-measures', [
            'descripcion' => 'Litro',
            'abreviatura' => 'lt',
            'estado' => 'AC',
        ])->assertForbidden();
        $this->getJson('/api/v1/unit-measures/1')->assertForbidden();
        $this->putJson('/api/v1/unit-measures/1', [
            'descripcion' => 'Editado',
            'abreviatura' => 'ed',
            'estado' => 'AC',
        ])->assertForbidden();
    }
}
