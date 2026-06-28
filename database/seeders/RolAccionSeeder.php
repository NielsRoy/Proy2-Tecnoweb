<?php

namespace Database\Seeders;

use App\Models\Accion;
use App\Models\Modulo;
use App\Models\Rol;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Llena la MATRIZ DE ACCESO (tabla rol_accion): que acciones tiene cada rol.
 * Es el estado inicial que luego el Propietario podra editar desde la UI.
 */
class RolAccionSeeder extends Seeder
{
    public function run(): void
    {
        // Mapa rol -> (modulo.clave => [accion.clave, ...]).  '*' = todas las acciones.
        $matriz = [
            'Propietario' => '*',
            'Vendedor' => [
                'dashboard' => ['ver'],
                'productos' => ['listar'],
                'ventas' => ['listar', 'registrar', 'modificar'],
                'inventarios' => ['listar'],
                'promociones' => ['listar'],
                'pagos' => ['listar', 'registrar'],
            ],
            'Cliente' => [
                'dashboard' => ['ver'],
                'productos' => ['listar'],
                'promociones' => ['listar'],
                'pagos' => ['listar'],
            ],
            'Proveedor' => [
                'dashboard' => ['ver'],
                'productos' => ['listar'],
                'compras' => ['listar', 'registrar', 'modificar'],
                'inventarios' => ['listar'],
            ],
        ];

        foreach ($matriz as $rolNombre => $permisos) {
            $rol = Rol::where('nombre', $rolNombre)->first();
            if (! $rol) {
                continue;
            }

            $accionIds = $permisos === '*'
                ? Accion::pluck('id')
                : $this->resolverAccionIds($permisos);

            // sync() reemplaza la matriz del rol por completo -> idempotente.
            $rol->acciones()->sync($accionIds->unique()->all());
        }
    }

    /** Convierte el mapa (modulo.clave => [accion.clave]) en ids de accion. */
    private function resolverAccionIds(array $permisos): Collection
    {
        $ids = collect();

        foreach ($permisos as $moduloClave => $accionClaves) {
            $modulo = Modulo::where('clave', $moduloClave)->first();
            if (! $modulo) {
                continue;
            }

            $ids = $ids->merge(
                Accion::where('modulo_id', $modulo->id)
                    ->whereIn('clave', $accionClaves)
                    ->pluck('id'),
            );
        }

        return $ids;
    }
}
