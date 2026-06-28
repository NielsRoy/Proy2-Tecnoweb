<?php

namespace App\Http\Controllers;

use App\Models\Accion;
use App\Models\Bitacora;
use App\Models\Modulo;
use App\Models\Rol;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Matriz de Acceso (vista del administrador): grid Roles x Acciones.
 * Es la misma data que alimenta el menu dinamico, pero editable.
 */
class AccesoController extends Controller
{
    /** Rol que siempre conserva acceso total (no editable para no auto-bloquearse). */
    private const ROL_SUPER = 'Propietario';

    /** Muestra la matriz: roles, modulos con sus acciones, y las asignaciones actuales. */
    public function matriz(Request $request): Response
    {
        // ¿El usuario actual puede editar la matriz? Si solo tiene "ver matriz", la ve en
        // modo solo-lectura (checkboxes deshabilitados y sin boton de guardar en el front).
        $puedeEditar = $request->user()->tienePermiso('acceso', 'modificar');

        $roles = Rol::where('est', true)->orderBy('id')->get(['id', 'nombre', 'descripcion']);

        $modulos = Modulo::where('est', true)
            ->orderBy('orden')->orderBy('id')
            ->with(['acciones' => fn ($q) => $q->where('est', true)->orderBy('id')])
            ->get(['id', 'clave', 'nombre', 'orden']);

        // Asignaciones actuales como mapa  rol_id => [accion_id, ...]  (comodo para los checkboxes).
        $asignaciones = DB::table('rol_accion')
            ->get(['rol_id', 'accion_id'])
            ->groupBy('rol_id')
            ->map(fn ($filas) => $filas->pluck('accion_id')->map(fn ($id) => (int) $id)->values());

        return Inertia::render('acceso/Matriz', [
            'roles' => $roles->map(fn (Rol $r) => [
                'id' => $r->id,
                'nombre' => $r->nombre,
                'descripcion' => $r->descripcion,
                // El super-rol se muestra pero con checkboxes bloqueados (siempre tiene todo).
                'editable' => $r->nombre !== self::ROL_SUPER,
            ]),
            'modulos' => $modulos->map(fn (Modulo $m) => [
                'id' => $m->id,
                'clave' => $m->clave,
                'nombre' => $m->nombre,
                'acciones' => $m->acciones->map(fn (Accion $a) => [
                    'id' => $a->id,
                    'clave' => $a->clave,
                    'nombre' => $a->nombre,
                ])->values(),
            ]),
            'asignaciones' => $asignaciones,
            'puedeEditar' => $puedeEditar,
        ]);
    }

    /** Guarda la matriz: sincroniza rol_accion segun los checkboxes enviados. */
    public function actualizarMatriz(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'asignaciones' => ['present', 'array'],          // mapa rol_id => [accion_id, ...]
            'asignaciones.*' => ['array'],
            'asignaciones.*.*' => ['integer', 'exists:accion,id'],
        ]);

        $todasLasAcciones = Accion::pluck('id')->all();

        foreach (Rol::where('est', true)->get() as $rol) {
            // El super-rol siempre conserva todo, ignorando lo que llegue (anti auto-bloqueo).
            if ($rol->nombre === self::ROL_SUPER) {
                $rol->acciones()->sync($todasLasAcciones);

                continue;
            }

            $accionIds = $datos['asignaciones'][$rol->id] ?? [];
            $accionIds = $this->normalizarDependencias($accionIds);
            $rol->acciones()->sync($accionIds);          // reemplaza la fila completa del rol
        }

        Bitacora::registrar('modificar', 'Actualizó la matriz de acceso', 'acceso');

        $this->toastExito('Matriz de acceso actualizada.');

        return back();
    }

    /**
     * Garantiza coherencia: si un rol tiene una accion de escritura (registrar/modificar/
     * eliminar) en un modulo, debe tener tambien su "listar" (no se puede editar sin ver).
     * Es la misma regla que aplica el front, reforzada aqui por seguridad.
     *
     * @param  array<int, int>  $accionIds
     * @return array<int, int>
     */
    private function normalizarDependencias(array $accionIds): array
    {
        if (empty($accionIds)) {
            return [];
        }

        $seleccionadas = Accion::whereIn('id', $accionIds)->get(['id', 'modulo_id', 'clave']);

        $modulosConEscritura = $seleccionadas
            ->whereIn('clave', ['registrar', 'modificar', 'eliminar'])
            ->pluck('modulo_id')->unique();

        $ids = collect($accionIds);

        if ($modulosConEscritura->isNotEmpty()) {
            $ids = $ids->merge(
                Accion::whereIn('modulo_id', $modulosConEscritura)
                    ->where('clave', 'listar')->where('est', true)
                    ->pluck('id'),
            );
        }

        return $ids->unique()->values()->all();
    }
}
