<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Pago;
use App\Models\Venta;
use App\Support\BusquedaTabla;
use App\Support\Reporte;
use App\Support\Url;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CU7 Pagos (modulo "pagos") — VISTA ADMIN. Lista TODOS los pagos (cuotas) del sistema con filtros
 * (cliente, metodo, estado, rango de vencimiento y de fecha de pago) y permite COBRAR la proxima cuota
 * pendiente de cada venta a credito (la de menor numero_cuota; monto fijo, sin pago parcial). Reusa
 * Pago::saldar. Genera reportes (PDF/Excel/CSV) de la lista filtrada.
 */
class PagoController extends Controller
{
    private const METODOS = [Pago::METODO_EFECTIVO, Pago::METODO_TARJETA];

    public function index(Request $request): Response
    {
        [$filtros, $query] = $this->consultaFiltrada($request);

        $pagos = $query->paginate(20)->withQueryString();

        // La proxima cuota cobrable de cada venta = la de menor numero_cuota pendiente. Se calcula solo
        // para las ventas visibles en la pagina actual (para marcar cual tiene el boton "Registrar pago").
        $ventaIds = collect($pagos->items())->pluck('venta_id')->unique()->all();
        $minPendiente = Pago::whereIn('venta_id', $ventaIds)
            ->where('estado', Pago::ESTADO_PENDIENTE)
            ->selectRaw('venta_id, MIN(numero_cuota) as min_cuota')
            ->groupBy('venta_id')
            ->pluck('min_cuota', 'venta_id');

        $pagos->through(fn (Pago $p) => [
            'id' => $p->id,
            'venta_id' => $p->venta_id,
            'cliente' => $p->venta?->cliente?->name,
            'numero_cuota' => $p->numero_cuota,
            'total_cuotas' => $p->venta?->numero_cuotas,
            'monto' => $p->monto,
            'metodo' => $p->metodo,
            'fecha_vencimiento' => $p->fecha_vencimiento?->toDateString(),
            'fecha_pago' => $p->fecha_pago?->format('d/m/Y H:i'),
            'estado' => $p->estado,
            'es_proxima' => $p->estado === Pago::ESTADO_PENDIENTE
                && $p->numero_cuota === $minPendiente->get($p->venta_id),
            // URL del flujo de pago por QR (Url::path conserva el subdirectorio); vuelve a este index.
            'qr_url' => Url::path('pagos.qr', ['pago' => $p->id, 'retorno' => 'pagos.index']),
        ]);

        return Inertia::render('pagos/Index', [
            'pagos' => $pagos,
            'filtros' => $filtros,
            'metodos' => self::METODOS,
            'puedeRegistrar' => $request->user()->tienePermiso('pagos', 'registrar'),
            'puedeReportar' => $request->user()->tienePermiso('pagos', 'reportar'),
        ]);
    }

    /** Reporte (PDF/Excel/CSV) de la lista de pagos filtrada. Ruta con permiso:pagos,reportar. */
    public function reporte(Request $request): mixed
    {
        [$filtros, $query] = $this->consultaFiltrada($request);
        $pagos = $query->get();

        $columnas = ['Cliente', 'Venta', 'Cuota', 'Monto (Bs)', 'Método', 'Vence', 'Fecha de pago', 'Estado'];
        $filas = $pagos->map(fn (Pago $p) => [
            $p->venta?->cliente?->name,
            '#'.$p->venta_id,
            $p->numero_cuota.'/'.($p->venta?->numero_cuotas ?? '—'),
            number_format((float) $p->monto, 2, '.', ''),
            $p->metodo ? ucfirst($p->metodo) : '—',
            $p->fecha_vencimiento?->format('d/m/Y'),
            $p->fecha_pago?->format('d/m/Y H:i') ?? '—',
            $p->estado === Pago::ESTADO_PAGADO ? 'Pagado' : 'Pendiente',
        ])->all();
        $sumaTotal = number_format((float) $pagos->sum('monto'), 2, '.', '');

        return Reporte::generar($request->string('formato')->toString(), [
            'titulo' => 'Reporte de Pagos',
            'subtitulo' => $this->descripcionFiltros($filtros),
            'columnas' => $columnas,
            'filas' => $filas,
            'filaTotal' => ['TOTAL', '', '', $sumaTotal, '', '', '', ''],
        ], 'pagos');
    }

    /**
     * Consulta de pagos aplicando filtros (cliente/metodo/estado/rango de vencimiento/rango de fecha de
     * pago). Compartida por index() y reporte(). Excluye las cuotas de ventas anuladas.
     *
     * @return array{0: array<string, mixed>, 1: Builder}
     */
    private function consultaFiltrada(Request $request): array
    {
        $filtros = [
            'q' => $request->string('q')->toString() ?: null,
            'metodo' => $request->string('metodo')->toString() ?: null,
            'estado' => $request->string('estado')->toString() ?: null,
            'venc_desde' => $request->date('venc_desde')?->toDateString(),
            'venc_hasta' => $request->date('venc_hasta')?->toDateString(),
            'pago_desde' => $request->date('pago_desde')?->toDateString(),
            'pago_hasta' => $request->date('pago_hasta')?->toDateString(),
        ];

        $query = Pago::query()
            ->with('venta.cliente:id,name')
            ->whereHas('venta', fn ($q) => $q->where('estado', '!=', Venta::ESTADO_ANULADA))
            ->when($filtros['q'], fn ($q, $t) => BusquedaTabla::aplicar($q, $t, [], ['venta.cliente' => ['name']]))
            ->when($filtros['metodo'], fn ($q, $m) => $q->where('metodo', $m))
            ->when($filtros['estado'], fn ($q, $e) => $q->where('estado', $e))
            ->when($filtros['venc_desde'], fn ($q, $d) => $q->whereDate('fecha_vencimiento', '>=', $d))
            ->when($filtros['venc_hasta'], fn ($q, $h) => $q->whereDate('fecha_vencimiento', '<=', $h))
            ->when($filtros['pago_desde'], fn ($q, $d) => $q->whereDate('fecha_pago', '>=', $d))
            ->when($filtros['pago_hasta'], fn ($q, $h) => $q->whereDate('fecha_pago', '<=', $h))
            ->orderByDesc('fecha_vencimiento')
            ->orderByDesc('id');

        return [$filtros, $query];
    }

    /** Texto legible de los filtros aplicados (para el subtitulo del reporte). */
    private function descripcionFiltros(array $f): string
    {
        $partes = [];
        if ($f['q']) {
            $partes[] = 'Búsqueda: «'.$f['q'].'»';
        }
        if ($f['metodo']) {
            $partes[] = 'Método: '.ucfirst($f['metodo']);
        }
        if ($f['estado']) {
            $partes[] = 'Estado: '.ucfirst($f['estado']);
        }
        if ($f['venc_desde']) {
            $partes[] = 'Vence desde: '.$f['venc_desde'];
        }
        if ($f['venc_hasta']) {
            $partes[] = 'Vence hasta: '.$f['venc_hasta'];
        }
        if ($f['pago_desde']) {
            $partes[] = 'Pagado desde: '.$f['pago_desde'];
        }
        if ($f['pago_hasta']) {
            $partes[] = 'Pagado hasta: '.$f['pago_hasta'];
        }
        $txt = $partes ? implode(' · ', $partes) : 'Sin filtros (todos los pagos)';

        return $txt.' — Generado: '.now()->format('d/m/Y H:i');
    }

    /**
     * Cobra una cuota: debe estar pendiente, su venta no anulada, y ser la PROXIMA de su venta.
     * El monto es el de la cuota (no se acepta del request: sin pago parcial).
     */
    public function pagar(Request $request, Pago $pago): RedirectResponse
    {
        $datos = $request->validate([
            'metodo' => ['required', Rule::in(self::METODOS)],
        ], [], ['metodo' => 'método de pago']);

        $pago->load('venta');

        abort_if($pago->estado === Pago::ESTADO_PAGADO, 422, 'La cuota ya está pagada.');
        abort_if($pago->venta?->estado === Venta::ESTADO_ANULADA, 422, 'La venta está anulada.');

        // Debe ser la proxima cuota pendiente de su venta (no saltarse el orden).
        $minPendiente = Pago::where('venta_id', $pago->venta_id)
            ->where('estado', Pago::ESTADO_PENDIENTE)
            ->min('numero_cuota');

        abort_if($pago->numero_cuota !== $minPendiente, 422, 'Debes pagar primero la cuota anterior.');

        DB::transaction(function () use ($pago, $datos) {
            Pago::saldar($pago, $datos['metodo']);

            Bitacora::registrar(
                'modificar',
                "Cobró la cuota #{$pago->numero_cuota} de la venta #{$pago->venta_id} (Bs {$pago->monto})",
                'pagos',
            );
        });

        $concepto = "Cuota {$pago->numero_cuota}/{$pago->venta?->numero_cuotas} de la venta #{$pago->venta_id}";

        return $this->comprobantePago($pago, $concepto, 'pagos.index');
    }
}
