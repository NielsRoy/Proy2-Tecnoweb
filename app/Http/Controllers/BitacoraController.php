<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\User;
use App\Support\Reporte;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vista de solo-lectura de la Bitacora (modulo "bitacora"). Lista paginada con filtros
 * por accion, usuario, texto y rango de fechas. Protegida con permiso:bitacora,listar.
 */
class BitacoraController extends Controller
{
    public function index(Request $request): Response
    {
        [$filtros, $query] = $this->consultaFiltrada($request);

        $registros = $query
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Bitacora $b) => [
                'id' => $b->id,
                'fecha' => $b->created_at?->format('d/m/Y H:i'),
                'usuario' => $b->usuario?->name,
                'accion' => $b->accion,
                'modulo' => $b->modulo,
                'descripcion' => $b->descripcion,
                'ip' => $b->ip,
            ]);

        return Inertia::render('bitacora/Index', [
            'registros' => $registros,
            'filtros' => $filtros,
            // Opciones para los selects de filtro.
            'acciones' => Bitacora::query()->distinct()->orderBy('accion')->pluck('accion'),
            'usuarios' => User::orderBy('name')->get(['id', 'name']),
            'puedeReportar' => $request->user()->tienePermiso('bitacora', 'reportar'),
        ]);
    }

    /** Reporte (PDF/Excel/CSV) de la bitacora filtrada. Ruta con permiso:bitacora,listar. */
    public function reporte(Request $request): mixed
    {
        [$filtros, $query] = $this->consultaFiltrada($request);
        $registros = $query->get();

        $columnas = ['Fecha', 'Usuario', 'Acción', 'Módulo', 'Descripción'];
        $filas = $registros->map(fn (Bitacora $b) => [
            $b->created_at?->format('d/m/Y H:i'),
            $b->usuario?->name ?? '—',
            $b->accion,
            $b->modulo ?? '—',
            $b->descripcion,
        ])->all();

        return Reporte::generar($request->string('formato')->toString(), [
            'titulo' => 'Reporte de Bitácora',
            'subtitulo' => $this->descripcionFiltros($filtros),
            'columnas' => $columnas,
            'filas' => $filas,
            'filaTotal' => ['Total: '.$registros->count().' registros', '', '', '', ''],
        ], 'bitacora');
    }

    /**
     * Consulta de bitacora aplicando filtros (accion/usuario/texto/fechas). Compartida por index y reporte.
     *
     * @return array{0: array<string, mixed>, 1: Builder}
     */
    private function consultaFiltrada(Request $request): array
    {
        $filtros = [
            'accion' => $request->string('accion')->toString() ?: null,
            'user_id' => $request->integer('user_id') ?: null,
            'q' => $request->string('q')->toString() ?: null,
            'desde' => $request->date('desde')?->toDateString(),
            'hasta' => $request->date('hasta')?->toDateString(),
        ];

        $query = Bitacora::query()
            ->with('usuario:id,name')
            ->when($filtros['accion'], fn ($q, $a) => $q->where('accion', $a))
            ->when($filtros['user_id'], fn ($q, $id) => $q->where('user_id', $id))
            ->when($filtros['q'], fn ($q, $texto) => $q->where('descripcion', 'like', "%{$texto}%"))
            ->when($filtros['desde'], fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filtros['hasta'], fn ($q, $h) => $q->whereDate('created_at', '<=', $h))
            ->orderByDesc('created_at');

        return [$filtros, $query];
    }

    /** Texto legible de los filtros aplicados (para el subtitulo del reporte). */
    private function descripcionFiltros(array $f): string
    {
        $partes = [];
        if ($f['accion']) {
            $partes[] = 'Acción: '.$f['accion'];
        }
        if ($f['user_id']) {
            $partes[] = 'Usuario: '.(User::find($f['user_id'])?->name ?? $f['user_id']);
        }
        if ($f['q']) {
            $partes[] = 'Texto: "'.$f['q'].'"';
        }
        if ($f['desde']) {
            $partes[] = 'Desde: '.$f['desde'];
        }
        if ($f['hasta']) {
            $partes[] = 'Hasta: '.$f['hasta'];
        }
        $txt = $partes ? implode(' · ', $partes) : 'Sin filtros (toda la bitácora)';

        return $txt.' — Generado: '.now()->format('d/m/Y H:i');
    }
}
