<?php

namespace App\Http\Controllers;

use App\Models\Carrito;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Promocion;
use App\Models\Venta;
use App\Services\RegistrarVenta;
use App\Support\PlanPago;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Carrito + checkout de la TIENDA autoservicio (rutas de libre acceso para todo usuario autenticado,
 * fuera de la matriz). El carrito es persistente (tabla carrito, PK compuesta cliente_id+producto_id);
 * por eso se opera con where()+query builder, no con find()/save(). El checkout reusa el servicio
 * RegistrarVenta (la misma logica que la venta del admin): el comprador es el usuario logueado.
 */
class CarritoController extends Controller
{
    private const METODOS = [Pago::METODO_EFECTIVO, Pago::METODO_TARJETA, Pago::METODO_QR];

    /** Vista del carrito + formulario de compra. */
    public function index(Request $request): Response
    {
        $lineas = $this->lineasCarrito($request->user()->id);
        $base = round(array_sum(array_column($lineas, 'subtotal')), 2);

        return Inertia::render('tienda/Carrito', [
            'lineas' => $lineas,
            'base' => $base,
            'metodos' => self::METODOS,
            'credito' => $this->tablasCredito(),
        ]);
    }

    /** Agrega un producto al carrito (o suma a la cantidad existente), sin pasar del stock. */
    public function agregar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'producto_id' => ['required', 'integer', Rule::exists('producto', 'id')->where('activo', true)],
            'cantidad' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ], [], ['producto_id' => 'producto']);

        $producto = Producto::where('activo', true)->findOrFail($datos['producto_id']);

        if ($producto->stock < 1) {
            $this->toastError("«{$producto->nombre}» no tiene stock disponible.");

            return back();
        }

        $uid = $request->user()->id;
        $actual = Carrito::where('cliente_id', $uid)->where('producto_id', $producto->id)->first();
        $nueva = min(($actual?->cantidad ?? 0) + ($datos['cantidad'] ?? 1), $producto->stock);

        $this->guardarLinea($uid, $producto->id, $nueva);

        $this->toastExito("«{$producto->nombre}» agregado al carrito.");

        return back();
    }

    /** Fija la cantidad de una linea del carrito (acotada al stock). */
    public function actualizar(Request $request, Producto $producto): RedirectResponse
    {
        $datos = $request->validate([
            'cantidad' => ['required', 'integer', 'min:1', 'max:1000000'],
        ]);

        $uid = $request->user()->id;
        $nueva = min($datos['cantidad'], max($producto->stock, 1));

        $this->guardarLinea($uid, $producto->id, $nueva);

        if ($datos['cantidad'] > $producto->stock) {
            $this->toastError("Solo hay {$producto->stock} unidades de «{$producto->nombre}».");
        } else {
            $this->toastExito('Carrito actualizado.');
        }

        return back();
    }

    /** Quita un producto del carrito. */
    public function quitar(Request $request, Producto $producto): RedirectResponse
    {
        Carrito::where('cliente_id', $request->user()->id)
            ->where('producto_id', $producto->id)
            ->delete();

        $this->toastExito('Producto quitado del carrito.');

        return back();
    }

    /**
     * Finaliza la compra: arma las lineas desde el carrito y delega en RegistrarVenta (el comprador
     * es el usuario logueado). La direccion ES requerida aqui (compra del propio cliente). Al exito
     * vacia el carrito.
     */
    public function checkout(Request $request, RegistrarVenta $registrar): RedirectResponse
    {
        $esContado = $request->input('tipo_pago') === Venta::TIPO_CONTADO;
        $esCredito = $request->input('tipo_pago') === Venta::TIPO_CREDITO;

        $datos = $request->validate([
            'direccion_envio' => ['required', 'string', 'max:1000'],
            'tipo_pago' => ['required', Rule::in([Venta::TIPO_CONTADO, Venta::TIPO_CREDITO])],
            'metodo' => [Rule::requiredIf($esContado), Rule::in(self::METODOS)],
            'numero_cuotas' => [Rule::requiredIf($esCredito), 'integer', 'min:2', 'max:'.PlanPago::CUOTAS_TOPE],
        ], [], [
            'direccion_envio' => 'dirección de envío',
            'tipo_pago' => 'tipo de pago',
            'metodo' => 'método de pago',
            'numero_cuotas' => 'número de cuotas',
        ]);

        $uid = $request->user()->id;
        $lineasCarrito = Carrito::where('cliente_id', $uid)->get();

        if ($lineasCarrito->isEmpty()) {
            $this->toastError('Tu carrito está vacío.');

            return back();
        }

        $lineas = $lineasCarrito
            ->map(fn (Carrito $c) => ['producto_id' => $c->producto_id, 'cantidad' => $c->cantidad])
            ->all();

        // Contado por QR: NO se registra la venta todavia. Se valida el stock/monto y se guarda la
        // intencion de compra en sesion; la venta se registra recien al confirmar el pago del QR.
        if ($datos['tipo_pago'] === Venta::TIPO_CONTADO && ($datos['metodo'] ?? null) === Pago::METODO_QR) {
            $monto = $registrar->previsualizar($lineas); // valida stock; lanza ValidationException

            $request->session()->put('qr_venta', [
                'payment_number' => null,
                'transaction_id' => null,
                'payload' => [
                    'cliente_id' => $uid,
                    'fecha_venta' => today()->toDateString(),
                    'direccion_envio' => $datos['direccion_envio'],
                    'tipo_pago' => Venta::TIPO_CONTADO,
                    'metodo' => Pago::METODO_QR,
                    'numero_cuotas' => null,
                    'lineas' => $lineas,
                ],
                'monto' => $monto,
                'origen' => 'tienda',
                'retorno' => 'inicio',
                'titulo' => 'Pago de tu compra',
            ]);

            return redirect()->route('pagos.qr-venta');
        }

        // Pedido: contado + efectivo desde la tienda -> la venta nace 'pedido' (descuenta stock pero
        // NO se cobra al instante; el admin la confirma). No hay comprobante de pago.
        $esPedido = $esContado && ($datos['metodo'] ?? null) === Pago::METODO_EFECTIVO;

        // Resto (contado por otros medios o credito): RegistrarVenta valida stock/promo/credito/bloqueo
        // y lanza ValidationException si algo falla.
        $venta = $registrar->ejecutar([
            'cliente_id' => $uid,
            'fecha_venta' => today()->toDateString(),
            'direccion_envio' => $datos['direccion_envio'],
            'tipo_pago' => $datos['tipo_pago'],
            'metodo' => $datos['metodo'] ?? null,
            'numero_cuotas' => $datos['numero_cuotas'] ?? null,
            'es_pedido' => $esPedido,
            'lineas' => $lineas,
        ]);

        // Compra registrada: vaciar el carrito.
        Carrito::where('cliente_id', $uid)->delete();

        // Pedido (efectivo): pantalla de "Pedido realizado" (paga en efectivo al recibir). No es un
        // comprobante de pago porque aun no se cobro; el admin lo confirma despues.
        if ($esPedido) {
            return $this->comprobantePedido($venta, 'inicio');
        }

        // Contado no-QR: se pago al comprar -> comprobante. Credito: solo se registro el plan (toast).
        if ($datos['tipo_pago'] === Venta::TIPO_CONTADO) {
            $cuota = Pago::where('venta_id', $venta->id)->where('numero_cuota', 1)->first();
            if ($cuota) {
                return $this->comprobantePago($cuota, "Compra #{$venta->id}", 'inicio');
            }
        }

        $this->toastExito('¡Compra realizada con éxito!');

        return redirect()->route('inicio');
    }

    /** Crea o actualiza (sin save() por la PK compuesta) la linea del carrito. */
    private function guardarLinea(int $clienteId, int $productoId, int $cantidad): void
    {
        $existe = Carrito::where('cliente_id', $clienteId)->where('producto_id', $productoId)->exists();

        if ($existe) {
            Carrito::where('cliente_id', $clienteId)->where('producto_id', $productoId)
                ->update(['cantidad' => $cantidad]);
        } else {
            Carrito::create([
                'cliente_id' => $clienteId,
                'producto_id' => $productoId,
                'cantidad' => $cantidad,
            ]);
        }
    }

    /**
     * Lineas del carrito del usuario con su precio (promo vigente aplicada) y subtotal.
     *
     * @return array<int, array<string, mixed>>
     */
    private function lineasCarrito(int $clienteId): array
    {
        $promosVigentes = Promocion::vigente()->get()->keyBy('producto_id');

        return Carrito::with('producto:id,nombre,precio,stock,foto')
            ->where('cliente_id', $clienteId)
            ->get()
            ->filter(fn (Carrito $c) => $c->producto !== null)
            ->map(function (Carrito $c) use ($promosVigentes) {
                $producto = $c->producto;
                $promo = $promosVigentes->get($producto->id);
                $precioFinal = $promo ? $promo->precioConDescuento((float) $producto->precio) : (float) $producto->precio;

                return [
                    'producto_id' => $producto->id,
                    'nombre' => $producto->nombre,
                    'foto_url' => $producto->foto ? Storage::disk('public')->url($producto->foto) : null,
                    'precio' => (float) $producto->precio,
                    'precio_final' => $precioFinal,
                    'promocion_nombre' => $promo?->nombre,
                    'stock' => $producto->stock,
                    'cantidad' => $c->cantidad,
                    'subtotal' => round($precioFinal * $c->cantidad, 2),
                ];
            })
            ->values()
            ->all();
    }

    /** Tablas de credito para el preview en vivo del checkout (el server recalcula al confirmar). */
    private function tablasCredito(): array
    {
        return [
            'montoMinimo' => PlanPago::MONTO_MINIMO_CREDITO,
            'tramosCuotas' => [
                ['hasta' => 299.99, 'cuotas' => 3],
                ['hasta' => 599.99, 'cuotas' => 6],
                ['hasta' => 999.99, 'cuotas' => 9],
                ['hasta' => null, 'cuotas' => 12],
            ],
            'tramosInteres' => [
                ['hastaCuotas' => 3, 'interes' => 0.05],
                ['hastaCuotas' => 6, 'interes' => 0.10],
                ['hastaCuotas' => 9, 'interes' => 0.15],
                ['hastaCuotas' => 12, 'interes' => 0.20],
            ],
        ];
    }
}
