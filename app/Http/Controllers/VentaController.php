<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Inventario;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Promocion;
use App\Models\User;
use App\Models\Venta;
use App\Support\PlanPago;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CU4 Ventas (modulo "ventas") — VISTA ADMIN (Propietario/Vendedor). El cliente NO registra ventas
 * aqui (eso sera el modulo "tienda" a futuro): aqui se elige al cliente de una lista.
 *
 * Flujo de pago (mejora sobre el sistema por correo):
 *  - CONTADO: se paga al registrarse -> la venta nace 'pagada' con su unica cuota saldada.
 *  - CREDITO: genera el cronograma de cuotas 'pendiente'; se cobran luego en el modulo Pagos.
 *
 * Todo el dinero se calcula en el server (precio con promo vigente por linea). Reusa
 * Inventario::registrarMovimiento (salida/venta), Promocion::vigente, Pago::saldar y PlanPago.
 * ANULAR (destroy) devuelve stock y borra las cuotas, solo si ninguna cuota fue pagada.
 */
class VentaController extends Controller
{
    /** Metodos de pago disponibles por ahora (el QR del requisito #10 queda pendiente). */
    private const METODOS = [Pago::METODO_EFECTIVO, Pago::METODO_TRANSFERENCIA, Pago::METODO_TARJETA];

    public function index(Request $request): Response
    {
        $ventas = Venta::with('cliente:id,name')
            ->withCount('detalles')
            ->orderByDesc('fecha_venta')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Venta $v) => [
                'id' => $v->id,
                'fecha' => $v->fecha_venta?->format('d/m/Y'),
                'cliente' => $v->cliente?->name,
                'items' => $v->detalles_count,
                'monto_total' => $v->monto_total,
                'tipo_pago' => $v->tipo_pago,
                'estado_pago' => $v->estado_pago,
                'estado' => $v->estado,
            ]);

        return Inertia::render('ventas/Index', [
            'ventas' => $ventas,
            'puedeCrear' => $request->user()->tienePermiso('ventas', 'registrar'),
            'puedeEliminar' => $request->user()->tienePermiso('ventas', 'eliminar'),
        ]);
    }

    public function create(): Response
    {
        // Una sola promo vigente por producto (la regla de no solapamiento lo garantiza).
        $promosVigentes = Promocion::vigente()->get()->keyBy('producto_id');

        $productos = Producto::where('est', true)->orderBy('nombre')->get()
            ->map(function (Producto $p) use ($promosVigentes) {
                $promo = $promosVigentes->get($p->id);

                return [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'precio' => (float) $p->precio,
                    'stock' => $p->stock,
                    'promocion_id' => $promo?->id,
                    'promocion_nombre' => $promo?->nombre,
                    'precio_final' => $promo ? $promo->precioConDescuento((float) $p->precio) : (float) $p->precio,
                ];
            });

        return Inertia::render('ventas/Form', [
            'clientes' => User::conRolVigente('Cliente')->orderBy('name')->get(['id', 'name']),
            'productos' => $productos,
            'metodos' => self::METODOS,
            // Tablas de credito para el preview en vivo (el server recalcula al guardar).
            'credito' => [
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
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        // 1) Calcular lineas en el server (precio con promo vigente) y validar stock.
        [$lineas, $base] = $this->resolverLineas($datos['lineas']);

        $esCredito = $datos['tipo_pago'] === Venta::TIPO_CREDITO;
        $numeroCuotas = 1;
        $montoTotal = $base;

        // 2) Reglas de credito + bloqueo del cliente.
        if ($esCredito) {
            $numeroCuotas = (int) $datos['numero_cuotas'];
            $cuotasMax = PlanPago::cuotasMaximas($base);

            if ($cuotasMax === 0) {
                throw ValidationException::withMessages([
                    'tipo_pago' => 'El monto base ('.number_format($base, 2).') es menor al mínimo de Bs '
                        .PlanPago::MONTO_MINIMO_CREDITO.' para vender a crédito.',
                ]);
            }
            if ($numeroCuotas < 2 || $numeroCuotas > $cuotasMax) {
                throw ValidationException::withMessages([
                    'numero_cuotas' => "Para este monto el número de cuotas debe estar entre 2 y {$cuotasMax}.",
                ]);
            }

            // Un solo plan a credito activo por cliente.
            $tienePlanActivo = Venta::where('cliente_id', $datos['cliente_id'])
                ->where('tipo_pago', Venta::TIPO_CREDITO)
                ->where('estado_pago', Venta::PAGO_PENDIENTE)
                ->where('estado', Venta::ESTADO_REGISTRADA)
                ->exists();

            if ($tienePlanActivo) {
                throw ValidationException::withMessages([
                    'cliente_id' => 'El cliente ya tiene un plan a crédito activo (solo se permite uno a la vez).',
                ]);
            }

            $montoTotal = PlanPago::montoTotalCredito($base, $numeroCuotas);
        }

        // 3) Persistir todo en una transaccion.
        DB::transaction(function () use ($datos, $lineas, $base, $montoTotal, $esCredito, $numeroCuotas) {
            $venta = Venta::create([
                'cliente_id' => $datos['cliente_id'],
                'fecha_venta' => $datos['fecha_venta'],
                'direccion_envio' => $datos['direccion_envio'],
                'monto_total' => $montoTotal,
                'tipo_pago' => $datos['tipo_pago'],
                'numero_cuotas' => $numeroCuotas,
                'estado_pago' => Venta::PAGO_PENDIENTE, // se vuelve 'pagada' al saldar (contado)
                'estado' => Venta::ESTADO_REGISTRADA,
            ]);

            foreach ($lineas as $linea) {
                $venta->detalles()->create([
                    'producto_id' => $linea['producto']->id,
                    'cantidad' => $linea['cantidad'],
                    'precio_unitario' => $linea['precio_unitario'],
                    'subtotal' => $linea['subtotal'],
                    'promocion_id' => $linea['promocion_id'],
                ]);

                // La venta saca stock (motivo venta).
                Inventario::registrarMovimiento(
                    $linea['producto'],
                    Inventario::SALIDA,
                    $linea['cantidad'],
                    Inventario::MOTIVO_VENTA,
                    $datos['fecha_venta'],
                );
            }

            if ($esCredito) {
                // Cronograma de cuotas, todas pendientes.
                foreach (PlanPago::cronograma($montoTotal, $numeroCuotas, $datos['fecha_venta']) as $cuota) {
                    Pago::create([
                        'venta_id' => $venta->id,
                        'numero_cuota' => $cuota['numero_cuota'],
                        'monto' => $cuota['monto'],
                        'fecha_vencimiento' => $cuota['fecha_vencimiento'],
                        'estado' => Pago::ESTADO_PENDIENTE,
                    ]);
                }
            } else {
                // Contado: una cuota que se salda ya con el metodo elegido (-> venta 'pagada').
                $cuota = Pago::create([
                    'venta_id' => $venta->id,
                    'numero_cuota' => 1,
                    'monto' => $montoTotal,
                    'fecha_vencimiento' => $datos['fecha_venta'],
                    'estado' => Pago::ESTADO_PENDIENTE,
                ]);
                Pago::saldar($cuota, $datos['metodo']);
            }

            Bitacora::registrar(
                'crear',
                "Registró la venta #{$venta->id} ({$venta->tipo_pago}, Bs {$montoTotal})",
                'ventas',
            );
        });

        $this->toastExito('Venta registrada.');

        return redirect()->route('ventas.index');
    }

    public function show(Venta $venta): Response
    {
        $venta->load('cliente:id,name', 'detalles.producto:id,nombre,precio', 'detalles.promocion:id,nombre');

        return Inertia::render('ventas/Show', [
            'venta' => [
                'id' => $venta->id,
                'fecha' => $venta->fecha_venta?->format('d/m/Y'),
                'cliente' => $venta->cliente?->name,
                'direccion_envio' => $venta->direccion_envio,
                'tipo_pago' => $venta->tipo_pago,
                'numero_cuotas' => $venta->numero_cuotas,
                'monto_total' => $venta->monto_total,
                'estado_pago' => $venta->estado_pago,
                'estado' => $venta->estado,
                'detalles' => $venta->detalles->map(fn ($d) => [
                    'producto' => $d->producto?->nombre,
                    'promocion' => $d->promocion?->nombre,
                    'cantidad' => $d->cantidad,
                    'precio_base' => $d->producto?->precio,    // precio de catalogo actual
                    'precio_unitario' => $d->precio_unitario,  // precio aplicado en la venta (con promo)
                    'subtotal' => $d->subtotal,
                ]),
                'cuotas' => $venta->pagos()->orderBy('numero_cuota')->get()->map(fn (Pago $p) => [
                    'numero_cuota' => $p->numero_cuota,
                    'monto' => $p->monto,
                    'fecha_vencimiento' => $p->fecha_vencimiento?->toDateString(),
                    'fecha_pago' => $p->fecha_pago?->toDateString(),
                    'metodo' => $p->metodo,
                    'estado' => $p->estado,
                ]),
            ],
        ]);
    }

    /**
     * ANULAR: devuelve el stock de cada linea (ingreso/correccion) y borra las cuotas, marcando la
     * venta 'anulada'. Solo si ninguna cuota fue pagada (las contado pagan al registrarse, asi que
     * en la practica solo se podran anular ventas a credito sin pagos). No borra la venta (historial).
     */
    public function destroy(Venta $venta): RedirectResponse
    {
        abort_if($venta->estado === Venta::ESTADO_ANULADA, 422, 'La venta ya está anulada.');

        $tienePagada = $venta->pagos()->where('estado', Pago::ESTADO_PAGADO)->exists();
        if ($tienePagada) {
            $this->toastError('No se puede anular: la venta ya tiene cuotas pagadas.');

            return back();
        }

        $venta->load('detalles.producto');

        DB::transaction(function () use ($venta) {
            foreach ($venta->detalles as $detalle) {
                // Devolver stock (ingreso de correccion).
                Inventario::registrarMovimiento(
                    $detalle->producto,
                    Inventario::INGRESO,
                    $detalle->cantidad,
                    Inventario::MOTIVO_CORRECCION,
                );
            }

            $venta->pagos()->delete(); // ninguna estaba pagada
            $venta->update(['estado' => Venta::ESTADO_ANULADA]);

            Bitacora::registrar('eliminar', "Anuló la venta #{$venta->id}", 'ventas');
        });

        $this->toastExito('Venta anulada y stock devuelto.');

        return redirect()->route('ventas.index');
    }

    /**
     * Resuelve las lineas con el precio del server (promo vigente aplicada), valida stock y devuelve
     * [lineas, base]. Lanza ValidationException en espanol si falta stock.
     *
     * @param  array<int, array{producto_id:int, cantidad:int}>  $lineasInput
     * @return array{0: array<int, array<string, mixed>>, 1: float}
     */
    private function resolverLineas(array $lineasInput): array
    {
        $promosVigentes = Promocion::vigente()->get()->keyBy('producto_id');
        $lineas = [];
        $base = 0;

        foreach ($lineasInput as $i => $entrada) {
            $producto = Producto::where('est', true)->findOrFail($entrada['producto_id']);

            if ($producto->stock < $entrada['cantidad']) {
                throw ValidationException::withMessages([
                    "lineas.{$i}.cantidad" => "Stock insuficiente: «{$producto->nombre}» tiene {$producto->stock} unidades.",
                ]);
            }

            $promo = $promosVigentes->get($producto->id);
            $precioUnitario = $promo
                ? $promo->precioConDescuento((float) $producto->precio)
                : (float) $producto->precio;
            $subtotal = round($entrada['cantidad'] * $precioUnitario, 2);
            $base += $subtotal;

            $lineas[] = [
                'producto' => $producto,
                'cantidad' => $entrada['cantidad'],
                'precio_unitario' => $precioUnitario,
                'subtotal' => $subtotal,
                'promocion_id' => $promo?->id,
            ];
        }

        return [$lineas, round($base, 2)];
    }

    /**
     * Reglas de validacion (en espanol via lang/es). `metodo` solo se exige al contado;
     * `numero_cuotas` solo al credito (el rango exacto se valida en store contra el monto base).
     *
     * @return array<string, mixed>
     */
    private function validar(Request $request): array
    {
        $esContado = $request->input('tipo_pago') === Venta::TIPO_CONTADO;
        $esCredito = $request->input('tipo_pago') === Venta::TIPO_CREDITO;

        $datos = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:users,id'],
            'fecha_venta' => ['required', 'date'],
            // Opcional en la venta admin; sera requerida cuando el cliente compre (modulo tienda futuro).
            'direccion_envio' => ['nullable', 'string', 'max:1000'],
            'tipo_pago' => ['required', Rule::in([Venta::TIPO_CONTADO, Venta::TIPO_CREDITO])],
            'metodo' => [Rule::requiredIf($esContado), Rule::in(self::METODOS)],
            'numero_cuotas' => [Rule::requiredIf($esCredito), 'integer', 'min:2', 'max:'.PlanPago::CUOTAS_TOPE],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.producto_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('producto', 'id')->where('est', true),
            ],
            'lineas.*.cantidad' => ['required', 'integer', 'min:1', 'max:1000000'],
        ], [
            'lineas.required' => 'Agrega al menos un producto a la venta.',
            'lineas.*.producto_id.distinct' => 'No repitas el mismo producto en dos líneas.',
        ], [
            'cliente_id' => 'cliente',
            'fecha_venta' => 'fecha',
            'direccion_envio' => 'dirección de envío',
            'tipo_pago' => 'tipo de pago',
            'metodo' => 'método de pago',
            'numero_cuotas' => 'número de cuotas',
        ]);

        // El cliente debe tener el rol Cliente vigente.
        $esCliente = User::conRolVigente('Cliente')->whereKey($datos['cliente_id'])->exists();
        if (! $esCliente) {
            throw ValidationException::withMessages([
                'cliente_id' => 'El usuario seleccionado no es un cliente vigente.',
            ]);
        }

        return $datos;
    }
}
