<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard (CU8): estadísticas del negocio como gráficos (barras/tortas). Cada gráfico es una ACCION
 * del módulo "dashboard" (permiso propio en la matriz): solo se calcula y se muestra si el usuario tiene
 * ese permiso (User::tienePermiso('dashboard', 'graf_*')). Todos los datos están ACOTADOS (top-10 o
 * buckets fijos: 12 meses / categorías fijas) para que no se vuelvan ilegibles al crecer los registros.
 */
class DashboardController extends Controller
{
    /** clave de acción => [título, tipo ('bar'|'pie'), método privado que calcula [labels, data]]. */
    private const GRAFICOS = [
        'graf_productos' => ['Productos más vendidos', 'bar', 'productosMasVendidos'],
        'graf_ingresos' => ['Ingresos por mes', 'bar', 'ingresosPorMes'],
        'graf_metodo_pago' => ['Pagos por método', 'pie', 'pagosPorMetodo'],
        'graf_tipo_pago' => ['Ventas por tipo de pago', 'pie', 'ventasPorTipoPago'],
        'graf_clientes' => ['Top clientes', 'bar', 'topClientes'],
        'graf_proveedores' => ['Top proveedores', 'bar', 'topProveedores'],
        'graf_stock' => ['Productos con bajo stock', 'bar', 'bajoStock'],
        'graf_promociones' => ['Top promociones por ingresos', 'bar', 'topPromociones'],
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $graficos = [];

        foreach (self::GRAFICOS as $clave => [$titulo, $tipo, $metodo]) {
            if (! $user->tienePermiso('dashboard', $clave)) {
                continue;
            }

            [$labels, $data] = $this->{$metodo}();
            $graficos[] = compact('clave', 'titulo', 'tipo', 'labels', 'data');
        }

        return Inertia::render('Dashboard', ['graficos' => $graficos]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Agregaciones (todas acotadas). Cada una devuelve [labels[], data[]].
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Top 10 productos por unidades vendidas (Σ cantidad), ventas no anuladas.
     *
     * @return array{0: array<int, mixed>, 1: array<int, mixed>}
     */
    private function productosMasVendidos(): array
    {
        $rows = DB::table('detalle_venta as dv')
            ->join('venta as v', 'v.id', '=', 'dv.venta_id')
            ->join('producto as p', 'p.id', '=', 'dv.producto_id')
            ->where('v.estado', '!=', Venta::ESTADO_ANULADA)
            ->groupBy('p.id', 'p.nombre')
            ->orderByDesc(DB::raw('SUM(dv.cantidad)'))
            ->limit(10)
            ->get(['p.nombre', DB::raw('SUM(dv.cantidad) as total')]);

        return [$rows->pluck('nombre')->all(), $rows->pluck('total')->map(fn ($v) => (int) $v)->all()];
    }

    /**
     * Ingresos (Σ monto_total) por mes en los últimos 12 meses; meses sin ventas van en 0.
     *
     * @return array{0: array<int, mixed>, 1: array<int, mixed>}
     */
    private function ingresosPorMes(): array
    {
        $desde = now()->startOfMonth()->subMonths(11);

        $totales = DB::table('venta')
            ->where('estado', '!=', Venta::ESTADO_ANULADA)
            ->whereDate('fecha_venta', '>=', $desde->toDateString())
            ->selectRaw("to_char(fecha_venta, 'YYYY-MM') as mes, SUM(monto_total) as total")
            ->groupBy('mes')
            ->pluck('total', 'mes');

        $labels = [];
        $data = [];
        for ($i = 0; $i < 12; $i++) {
            $mes = $desde->copy()->addMonths($i);
            $labels[] = $mes->format('m/Y');
            $data[] = (float) ($totales[$mes->format('Y-m')] ?? 0);
        }

        return [$labels, $data];
    }

    /**
     * Nº de cuotas pagadas por método (efectivo/transferencia/tarjeta/qr).
     *
     * @return array{0: array<int, mixed>, 1: array<int, mixed>}
     */
    private function pagosPorMetodo(): array
    {
        $rows = DB::table('pago')
            ->where('estado', Pago::ESTADO_PAGADO)
            ->whereNotNull('metodo')
            ->groupBy('metodo')
            ->get(['metodo', DB::raw('COUNT(*) as total')]);

        $labels = $rows->pluck('metodo')->map(fn ($m) => $m === 'qr' ? 'QR' : ucfirst((string) $m))->all();

        return [$labels, $rows->pluck('total')->map(fn ($v) => (int) $v)->all()];
    }

    /**
     * Nº de ventas por tipo de pago (contado/crédito), no anuladas.
     *
     * @return array{0: array<int, mixed>, 1: array<int, mixed>}
     */
    private function ventasPorTipoPago(): array
    {
        $rows = DB::table('venta')
            ->where('estado', '!=', Venta::ESTADO_ANULADA)
            ->groupBy('tipo_pago')
            ->get(['tipo_pago', DB::raw('COUNT(*) as total')]);

        $labels = $rows->pluck('tipo_pago')->map(fn ($t) => ucfirst((string) $t))->all();

        return [$labels, $rows->pluck('total')->map(fn ($v) => (int) $v)->all()];
    }

    /**
     * Top 10 clientes por monto vendido (Σ monto_total), ventas no anuladas.
     *
     * @return array{0: array<int, mixed>, 1: array<int, mixed>}
     */
    private function topClientes(): array
    {
        $rows = DB::table('venta as v')
            ->join('users as u', 'u.id', '=', 'v.cliente_id')
            ->where('v.estado', '!=', Venta::ESTADO_ANULADA)
            ->groupBy('u.id', 'u.name')
            ->orderByDesc(DB::raw('SUM(v.monto_total)'))
            ->limit(10)
            ->get(['u.name', DB::raw('SUM(v.monto_total) as total')]);

        return [$rows->pluck('name')->all(), $rows->pluck('total')->map(fn ($v) => (float) $v)->all()];
    }

    /**
     * Top 10 proveedores por monto comprado (Σ monto_total), compras no anuladas.
     *
     * @return array{0: array<int, mixed>, 1: array<int, mixed>}
     */
    private function topProveedores(): array
    {
        $rows = DB::table('compra as c')
            ->join('users as u', 'u.id', '=', 'c.proveedor_id')
            ->where('c.estado', '!=', Compra::ESTADO_ANULADA)
            ->groupBy('u.id', 'u.name')
            ->orderByDesc(DB::raw('SUM(c.monto_total)'))
            ->limit(10)
            ->get(['u.name', DB::raw('SUM(c.monto_total) as total')]);

        return [$rows->pluck('name')->all(), $rows->pluck('total')->map(fn ($v) => (float) $v)->all()];
    }

    /**
     * Top 10 productos activos con menor stock (alerta de reposición).
     *
     * @return array{0: array<int, mixed>, 1: array<int, mixed>}
     */
    private function bajoStock(): array
    {
        $rows = Producto::where('activo', true)
            ->orderBy('stock')
            ->orderBy('nombre')
            ->limit(10)
            ->get(['nombre', 'stock']);

        return [$rows->pluck('nombre')->all(), $rows->pluck('stock')->map(fn ($v) => (int) $v)->all()];
    }

    /**
     * Top 10 promociones por ingresos generados (Σ subtotal de líneas que aplicaron la promo).
     *
     * @return array{0: array<int, mixed>, 1: array<int, mixed>}
     */
    private function topPromociones(): array
    {
        $rows = DB::table('detalle_venta as dv')
            ->join('venta as v', 'v.id', '=', 'dv.venta_id')
            ->join('promocion as pr', 'pr.id', '=', 'dv.promocion_id')
            ->where('v.estado', '!=', Venta::ESTADO_ANULADA)
            ->groupBy('pr.id', 'pr.nombre')
            ->orderByDesc(DB::raw('SUM(dv.subtotal)'))
            ->limit(10)
            ->get(['pr.nombre', DB::raw('SUM(dv.subtotal) as total')]);

        return [$rows->pluck('nombre')->all(), $rows->pluck('total')->map(fn ($v) => (float) $v)->all()];
    }
}
