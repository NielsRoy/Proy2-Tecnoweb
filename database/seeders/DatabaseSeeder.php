<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // El orden importa: roles y modulos (con sus acciones) deben existir antes de
        // llenar la matriz (rol_accion) y de asignar roles a los usuarios.
        $this->call([
            RolSeeder::class,
            ModuloSeeder::class,
            RolAccionSeeder::class,
            UsuarioRolSeeder::class,
            // Datos de negocio (reusa los users demo de UsuarioRolSeeder).
            NegocioSeeder::class,
        ]);
    }
}
