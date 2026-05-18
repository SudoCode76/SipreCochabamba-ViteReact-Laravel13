<?php

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithLegacyAuth;
use Tests\TestCase;

class AuditApiTest extends TestCase
{
    use InteractsWithLegacyAuth;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLegacyAuthSchema();
    }

    public function test_admin_can_list_and_filter_audits(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        AuditLog::query()->create([
            'nombre_completo' => 'Ana Admin',
            'fecha_hora' => '2026-05-17 09:00:00',
            'ip' => '127.0.0.1',
            'proceso' => 'PROYECTOS: se creo el proyecto PUENTE',
        ]);
        AuditLog::query()->create([
            'nombre_completo' => 'Bruno Tecnico',
            'fecha_hora' => '2026-05-18 10:00:00',
            'ip' => '127.0.0.2',
            'proceso' => 'ITEMS: se actualizo el item ACERO',
        ]);

        $this->getJson('/api/v1/audits?user=bruno&search=item&date_from=2026-05-18&date_to=2026-05-18')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.user_name', 'Bruno Tecnico')
            ->assertJsonPath('data.items.0.process', 'ITEMS: se actualizo el item ACERO');
    }

    public function test_non_admin_cannot_access_audits(): void
    {
        Sanctum::actingAs($this->createNonAdminUser());

        $this->getJson('/api/v1/audits')->assertForbidden();
    }

    private function createNonAdminUser(): User
    {
        $role = Role::query()->create([
            'id_rol' => 2,
            'nombre_rol' => 'Tecnico',
            'estado' => 'AC',
        ]);

        $unit = Unit::query()->create([
            'id_unidad' => 2,
            'descripcion' => 'Unidad Tecnica',
            'estado' => 'AC',
        ]);

        return User::query()->create([
            'id_usuario' => 2,
            'funcionario' => 'Usuario Tecnico',
            'ci' => '87654321',
            'username' => 'tecnico',
            'clave' => Hash::make('secret123'),
            'estado' => 'AC',
            'id_unidad' => $unit->id_unidad,
            'rol' => $role->id_rol,
            'fecha' => now()->toDateString(),
            'subalcaldia' => null,
        ]);
    }
}
