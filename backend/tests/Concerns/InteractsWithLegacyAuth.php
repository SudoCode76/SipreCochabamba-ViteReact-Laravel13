<?php

namespace Tests\Concerns;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemFunction;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

trait InteractsWithLegacyAuth
{
    protected function setUpLegacyAuthSchema(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('permiso');
        Schema::dropIfExists('usuario');
        Schema::dropIfExists('unidad');
        Schema::dropIfExists('rol');
        Schema::dropIfExists('funcion');
        Schema::dropIfExists('auditoria');
        Schema::enableForeignKeyConstraints();

        Schema::create('auditoria', function (Blueprint $table): void {
            $table->increments('id_auditoria');
            $table->string('nombre_completo', 50)->nullable();
            $table->timestamp('fecha_hora')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('proceso', 250)->nullable();
        });

        Schema::create('funcion', function (Blueprint $table): void {
            $table->increments('id_funcion');
            $table->string('nombre_funcion', 100)->nullable();
            $table->string('descripcion', 100)->nullable();
            $table->string('clase', 30)->nullable();
            $table->string('estado', 2)->nullable();
        });

        Schema::create('rol', function (Blueprint $table): void {
            $table->increments('id_rol');
            $table->string('nombre_rol', 35)->nullable();
            $table->string('estado', 2)->nullable();
        });

        Schema::create('unidad', function (Blueprint $table): void {
            $table->increments('id_unidad');
            $table->text('descripcion')->nullable();
            $table->string('estado', 2)->nullable();
        });

        Schema::create('usuario', function (Blueprint $table): void {
            $table->increments('id_usuario');
            $table->string('funcionario', 80)->nullable();
            $table->string('ci', 30)->nullable();
            $table->string('username', 50)->nullable();
            $table->string('clave', 255)->nullable();
            $table->string('estado', 2)->nullable();
            $table->unsignedInteger('id_unidad')->nullable();
            $table->unsignedInteger('rol');
            $table->integer('item')->nullable();
            $table->date('fecha')->nullable();
            $table->integer('subalcaldia')->nullable();

            $table->foreign('id_unidad')->references('id_unidad')->on('unidad');
            $table->foreign('rol')->references('id_rol')->on('rol');
        });

        Schema::create('permiso', function (Blueprint $table): void {
            $table->increments('id_permiso');
            $table->unsignedInteger('id_rol');
            $table->string('nombre_rol', 20)->nullable();
            $table->unsignedInteger('id_funcion');
            $table->string('descripcion', 100)->nullable();
            $table->string('estado', 2)->nullable();

            $table->foreign('id_rol')->references('id_rol')->on('rol');
            $table->foreign('id_funcion')->references('id_funcion')->on('funcion');
        });
    }

    protected function createLegacyAuthUser(array $overrides = [], string $functionName = 'auth.login', string $functionDescription = 'Iniciar sesion'): User
    {
        $unit = Unit::query()->create([
            'id_unidad' => 1,
            'descripcion' => 'Unidad Central',
            'estado' => 'AC',
        ]);

        $role = Role::query()->create([
            'id_rol' => 1,
            'nombre_rol' => 'Administrador',
            'estado' => 'AC',
        ]);

        $function = SystemFunction::query()->create([
            'id_funcion' => 1,
            'nombre_funcion' => $functionName,
            'descripcion' => $functionDescription,
            'clase' => 'Auth',
            'estado' => 'AC',
        ]);

        Permission::query()->create([
            'id_permiso' => 1,
            'id_rol' => $role->id_rol,
            'nombre_rol' => $role->nombre_rol,
            'id_funcion' => $function->id_funcion,
            'descripcion' => 'Puede autenticarse',
            'estado' => 'AC',
        ]);

        return User::query()->create(array_merge([
            'id_usuario' => 1,
            'funcionario' => 'Usuario Demo',
            'ci' => '12345678',
            'username' => 'demo',
            'clave' => Hash::make('secret123'),
            'estado' => 'AC',
            'id_unidad' => $unit->id_unidad,
            'rol' => $role->id_rol,
            'fecha' => now()->toDateString(),
            'subalcaldia' => null,
        ], $overrides));
    }
}
