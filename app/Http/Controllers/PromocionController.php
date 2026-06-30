<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Producto;
use App\Models\Promocion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CU6 Promociones (modulo "promociones"): descuento por producto. Eliminar = BAJA LOGICA
 * (est=false) porque la referencian las ventas (detalle_venta.promocion_id). Regla clave: no se
 * permiten dos promos ACTIVAS del mismo producto con rangos de fecha solapados.
 */
class PromocionController extends Controller
{
    public function index(Request $request): Response
    {
        $hoy = today()->toDateString();

        $promociones = Promocion::with('producto:id,nombre')
            ->where('est', true)
            ->orderByDesc('fecha_inicio')
            ->get()
            ->map(fn (Promocion $p) => [
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
            'puedeCrear' => $request->user()->tienePermiso('promociones', 'registrar'),
            'puedeEditar' => $request->user()->tienePermiso('promociones', 'modificar'),
            'puedeEliminar' => $request->user()->tienePermiso('promociones', 'eliminar'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('promociones/Form', [
            'promocion' => null,
            'productos' => Producto::where('est', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        $promocion = Promocion::create([...$datos, 'est' => true]);

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
            'productos' => Producto::where('est', true)->orderBy('nombre')->get(['id', 'nombre']),
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
        $promocion->update(['est' => false]);

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
            'producto_id' => ['required', 'integer', Rule::exists('producto', 'id')->where('est', true)],
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
            ->where('est', true)
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
