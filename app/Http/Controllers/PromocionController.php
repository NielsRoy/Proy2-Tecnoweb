<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Producto;
use App\Models\Promocion;
use App\Support\BusquedaTabla;
use App\Support\Reporte;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CU6 Promociones (modulo "promociones"): descuento por producto. Eliminar = BAJA LOGICA
 * (activo=false) porque la referencian las ventas (detalle_venta.promocion_id). Regla clave: no se
 * permiten dos promos ACTIVAS del mismo producto con rangos de fecha solapados.
 */
class PromocionController extends Controller
{
    public function index(Request $request): Response
    {
        $hoy = today()->toDateString();
        [$filtros, $query] = $this->consultaFiltrada($request);

        $promociones = $query->get()->map(fn (Promocion $p) => [
            'id' => $p->id,
            'producto' => $p->producto?->nombre,
            'nombre' => $p->nombre,
            'tipo_descuento' => $p->tipo_descuento,
            'valor' => $p->valor,
            'fecha_inicio' => $p->fecha_inicio?->toDateString(),
            'fecha_fin' => $p->fecha_fin?->toDateString(),
            'vigente' => $p->fecha_inicio?->toDateString() <= $hoy
                && $p->fecha_fin?->toDateString() >= $hoy,
        ]);

        return Inertia::render('promociones/Index', [
            'promociones' => $promociones,
            'filtros' => $filtros,
            'puedeCrear' => $request->user()->tienePermiso('promociones', 'registrar'),
            'puedeEditar' => $request->user()->tienePermiso('promociones', 'modificar'),
            'puedeEliminar' => $request->user()->tienePermiso('promociones', 'eliminar'),
            'puedeReportar' => $request->user()->tienePermiso('promociones', 'reportar'),
        ]);
    }

    /** Reporte (PDF/Excel/CSV) de las promociones activas (respeta los filtros). permiso:promociones,listar. */
    public function reporte(Request $request): mixed
    {
        $hoy = today()->toDateString();
        [$filtros, $query] = $this->consultaFiltrada($request);
        $promociones = $query->get();

        $columnas = ['Producto', 'Promoción', 'Tipo', 'Valor', 'Desde', 'Hasta', 'Vigente'];
        $filas = $promociones->map(function (Promocion $p) use ($hoy) {
            $vigente = $p->fecha_inicio?->toDateString() <= $hoy && $p->fecha_fin?->toDateString() >= $hoy;
            $valor = $p->tipo_descuento === Promocion::TIPO_PORCENTAJE
                ? rtrim(rtrim((string) $p->valor, '0'), '.').' %'
                : 'Bs '.number_format((float) $p->valor, 2, '.', '');

            return [
                $p->producto?->nombre,
                $p->nombre,
                ucfirst($p->tipo_descuento),
                $valor,
                $p->fecha_inicio?->format('d/m/Y'),
                $p->fecha_fin?->format('d/m/Y'),
                $vigente ? 'Sí' : 'No',
            ];
        })->all();

        return Reporte::generar($request->string('formato')->toString(), [
            'titulo' => 'Reporte de Promociones',
            'subtitulo' => $this->descripcionFiltros($filtros),
            'columnas' => $columnas,
            'filas' => $filas,
            'filaTotal' => ['Total: '.$promociones->count().' promociones', '', '', '', '', '', ''],
        ], 'promociones');
    }

    /**
     * Consulta de promociones activas aplicando filtros (producto + rango de fechas por solape).
     * El solape con [desde, hasta] = la promo no termina antes de 'desde' ni empieza después de 'hasta'.
     * Compartida por index y reporte.
     *
     * @return array{0: array<string, mixed>, 1: Builder}
     */
    private function consultaFiltrada(Request $request): array
    {
        $filtros = [
            'q' => $request->string('q')->toString() ?: null,
            'desde' => $request->date('desde')?->toDateString(),
            'hasta' => $request->date('hasta')?->toDateString(),
        ];

        $query = Promocion::with('producto:id,nombre')
            ->where('activo', true)
            ->when($filtros['q'], fn ($q, $t) => BusquedaTabla::aplicar($q, $t, ['nombre'], ['producto' => ['nombre']]))
            ->when($filtros['desde'], fn ($q, $d) => $q->whereDate('fecha_fin', '>=', $d))
            ->when($filtros['hasta'], fn ($q, $h) => $q->whereDate('fecha_inicio', '<=', $h))
            ->orderByDesc('fecha_inicio');

        return [$filtros, $query];
    }

    /** Texto legible de los filtros aplicados (para el subtitulo del reporte). */
    private function descripcionFiltros(array $f): string
    {
        $partes = [];
        if ($f['q']) {
            $partes[] = 'Búsqueda: «'.$f['q'].'»';
        }
        if ($f['desde']) {
            $partes[] = 'Activas desde: '.$f['desde'];
        }
        if ($f['hasta']) {
            $partes[] = 'Activas hasta: '.$f['hasta'];
        }
        $txt = $partes ? implode(' · ', $partes) : 'Todas las promociones activas';

        return $txt.' — Generado: '.now()->format('d/m/Y H:i');
    }

    public function create(): Response
    {
        return Inertia::render('promociones/Form', [
            'promocion' => null,
            'productos' => Producto::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        $promocion = Promocion::create([...$datos, 'activo' => true]);

        Bitacora::registrar('crear', "Creó la promoción «{$promocion->nombre}»", 'promociones');
        $this->toastExito('Promoción creada.');

        return redirect()->route('promociones.index');
    }

    public function edit(Promocion $promocion): Response
    {
        return Inertia::render('promociones/Form', [
            'promocion' => [
                'id' => $promocion->id,
                'producto_id' => $promocion->producto_id,
                'nombre' => $promocion->nombre,
                'descripcion' => $promocion->descripcion,
                'tipo_descuento' => $promocion->tipo_descuento,
                'valor' => $promocion->valor,
                'fecha_inicio' => $promocion->fecha_inicio?->toDateString(),
                'fecha_fin' => $promocion->fecha_fin?->toDateString(),
            ],
            'productos' => Producto::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function update(Request $request, Promocion $promocion): RedirectResponse
    {
        $datos = $this->validar($request, $promocion);

        $promocion->fill($datos)->save();

        Bitacora::registrar('modificar', "Modificó la promoción «{$promocion->nombre}»", 'promociones');
        $this->toastExito('Promoción actualizada.');

        return redirect()->route('promociones.index');
    }

    public function destroy(Promocion $promocion): RedirectResponse
    {
        // Baja logica (la referencian las ventas vía detalle_venta.promocion_id).
        $promocion->update(['activo' => false]);

        Bitacora::registrar('eliminar', "Dio de baja la promoción «{$promocion->nombre}»", 'promociones');
        $this->toastExito('Promoción eliminada.');

        return redirect()->route('promociones.index');
    }

    /**
     * Reglas de validacion (en espanol via lang/es). Ademas valida que no se solape con otra promo
     * ACTIVA del mismo producto (dos rangos se cruzan si inicioA <= finB y finA >= inicioB).
     *
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?Promocion $promocion = null): array
    {
        $datos = $request->validate([
            'producto_id' => ['required', 'integer', Rule::exists('producto', 'id')->where('activo', true)],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'tipo_descuento' => ['required', Rule::in([Promocion::TIPO_PORCENTAJE, Promocion::TIPO_MONTO])],
            'valor' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
        ], [], [
            'producto_id' => 'producto',
            'tipo_descuento' => 'tipo de descuento',
            'fecha_inicio' => 'fecha de inicio',
            'fecha_fin' => 'fecha de fin',
        ]);

        // Un porcentaje no puede pasar de 100.
        if ($datos['tipo_descuento'] === Promocion::TIPO_PORCENTAJE && $datos['valor'] > 100) {
            throw ValidationException::withMessages([
                'valor' => 'El porcentaje de descuento no puede ser mayor a 100.',
            ]);
        }

        // No solapar con otra promo activa del mismo producto.
        $solapa = Promocion::where('producto_id', $datos['producto_id'])
            ->where('activo', true)
            ->when($promocion, fn ($q) => $q->where('id', '!=', $promocion->id))
            ->whereDate('fecha_inicio', '<=', $datos['fecha_fin'])
            ->whereDate('fecha_fin', '>=', $datos['fecha_inicio'])
            ->exists();

        if ($solapa) {
            throw ValidationException::withMessages([
                'fecha_inicio' => 'Ya existe una promoción activa de este producto en esas fechas.',
            ]);
        }

        return $datos;
    }
}
