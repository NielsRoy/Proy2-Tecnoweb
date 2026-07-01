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
    // Modulos que el super-rol (Propietario, '*') NO recibe por defecto: la perspectiva CLIENTE
    // (autoservicio) no aplica al Propietario, que es back-office. Se pueden conceder luego en la matriz.
    private const EXCLUIDOS_SUPER = ['mis_compras', 'mis_pagos'];

    public function run(): void
    {
        // Mapa rol -> (modulo.clave => [accion.clave, ...]).  '*' = todas las acciones (con excepciones,
        // ver EXCLUIDOS_SUPER: el Propietario es back-office, no usa la perspectiva cliente).
        $matriz = [
            'Propietario' => '*',
            'Vendedor' => [
                'dashboard' => ['ver', 'graf_productos', 'graf_ingresos', 'graf_tipo_pago', 'graf_clientes', 'graf_stock', 'graf_promociones'],
                'productos' => ['listar', 'reportar'],
                'categorias' => ['listar'],
                'ventas' => ['listar', 'registrar', 'reportar'],
                'inventarios' => ['listar', 'reportar'],
                'promociones' => ['listar', 'reportar'],
                'pagos' => ['listar', 'registrar', 'reportar'],
                'mis_compras' => ['ver'],
                'mis_pagos' => ['ver', 'pagar'],
            ],
            'Cliente' => [
                'productos' => ['listar'],
                'categorias' => ['listar'],
                'promociones' => ['listar'],
                'mis_compras' => ['ver'],
                'mis_pagos' => ['ver', 'pagar'],
            ],
            'Proveedor' => [
                'dashboard' => ['ver', 'graf_productos', 'graf_proveedores', 'graf_stock'],
                'productos' => ['listar', 'reportar'],
                'categorias' => ['listar'],
                'compras' => ['listar', 'registrar', 'reportar'],
                'inventarios' => ['listar', 'reportar'],
                'mis_compras' => ['ver'],
                'mis_pagos' => ['ver', 'pagar'],
            ],
        ];

        foreach ($matriz as $rolNombre => $permisos) {
            $rol = Rol::where('nombre', $rolNombre)->first();
            if (! $rol) {
                continue;
            }

            $accionIds = $permisos === '*'
                ? Accion::whereHas('modulo', fn ($q) => $q->whereNotIn('clave', self::EXCLUIDOS_SUPER))->pluck('id')
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
