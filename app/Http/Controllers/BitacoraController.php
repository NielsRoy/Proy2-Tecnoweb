<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\User;
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
        $filtros = [
            'accion' => $request->string('accion')->toString() ?: null,
            'user_id' => $request->integer('user_id') ?: null,
            'q' => $request->string('q')->toString() ?: null,
            'desde' => $request->date('desde')?->toDateString(),
            'hasta' => $request->date('hasta')?->toDateString(),
        ];

        $registros = Bitacora::query()
            ->with('usuario:id,name')
            ->when($filtros['accion'], fn ($q, $a) => $q->where('accion', $a))
            ->when($filtros['user_id'], fn ($q, $id) => $q->where('user_id', $id))
            ->when($filtros['q'], fn ($q, $texto) => $q->where('descripcion', 'like', "%{$texto}%"))
            ->when($filtros['desde'], fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filtros['hasta'], fn ($q, $h) => $q->whereDate('created_at', '<=', $h))
            ->orderByDesc('created_at')
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
        ]);
    }
}
