<?php

namespace Database\Seeders;

use App\Models\Rol;
use Illuminate\Database\Seeder;

class RolSeeder extends Seeder
{
    public function run(): void
    {
        // Propietario = super-rol (no cuenta como rol de negocio); siempre existe y tiene todo.
        // Vendedor / Cliente / Proveedor = roles de negocio (requisito #2: >=2).
        $roles = [
            ['nombre' => 'Propietario', 'descripcion' => 'Acceso total: gestiona usuarios y la matriz de acceso'],
            ['nombre' => 'Vendedor', 'descripcion' => 'Gestiona ventas y consulta inventario'],
            ['nombre' => 'Cliente', 'descripcion' => 'Consulta el catalogo y sus compras/pagos'],
            ['nombre' => 'Proveedor', 'descripcion' => 'Gestiona compras y abastecimiento'],
        ];

        foreach ($roles as $r) {
            // updateOrCreate -> el seeder es idempotente (se puede re-ejecutar sin duplicar).
            Rol::updateOrCreate(['nombre' => $r['nombre']], [
                'descripcion' => $r['descripcion'],
                'activo' => true,
            ]);
        }
    }
}
