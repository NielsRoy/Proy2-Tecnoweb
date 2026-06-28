<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Crea usuarios de demostracion (uno por rol) y les asigna su rol con vigencia.
 * Sirven para probar que cada rol ve un menu distinto. Password de todos: "password".
 */
class UsuarioRolSeeder extends Seeder
{
    public function run(): void
    {
        $usuarios = [
            ['name' => 'Propietario', 'email' => 'propietario@tiendadyd.com', 'rol' => 'Propietario'],
            ['name' => 'Vendedor Demo', 'email' => 'vendedor@tiendadyd.com', 'rol' => 'Vendedor'],
            ['name' => 'Cliente Demo', 'email' => 'cliente@tiendadyd.com', 'rol' => 'Cliente'],
            ['name' => 'Proveedor Demo', 'email' => 'proveedor@tiendadyd.com', 'rol' => 'Proveedor'],
        ];

        foreach ($usuarios as $u) {
            // firstOrCreate por email -> no duplica si el seeder se re-ejecuta.
            // El password se hashea solo (cast 'password' => 'hashed' en el modelo User).
            $user = User::firstOrCreate(
                ['email' => $u['email']],
                ['name' => $u['name'], 'password' => 'password'],
            );

            $rol = Rol::where('nombre', $u['rol'])->first();
            if (! $rol) {
                continue;
            }

            // Asignacion con vigencia: empieza hoy, sin fecha de fin (ffin = null).
            $user->roles()->syncWithoutDetaching([
                $rol->id => ['fini' => now()->toDateString(), 'ffin' => null, 'est' => true],
            ]);
        }
    }
}
