<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unit = Unit::query()->firstOrCreate(
            ['id_unidad' => 1],
            [
                'descripcion' => 'Unidad Central',
                'estado' => 'AC',
            ],
        );

        $role = Role::query()->firstOrCreate(
            ['id_rol' => 1],
            [
                'nombre_rol' => 'Administrador',
                'estado' => 'AC',
            ],
        );

        return [
            'funcionario' => fake()->name(),
            'ci' => fake()->unique()->numerify('########'),
            'username' => fake()->unique()->userName(),
            'clave' => static::$password ??= Hash::make('password'),
            'estado' => 'AC',
            'id_unidad' => $unit->id_unidad,
            'rol' => $role->id_rol,
            'item' => null,
            'fecha' => now()->toDateString(),
            'subalcaldia' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'DC',
        ]);
    }
}
