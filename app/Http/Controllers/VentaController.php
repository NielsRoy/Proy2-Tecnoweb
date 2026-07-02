<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Inventario;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Promocion;
use App\Models\User;
use App\Models\Venta;
use App\Services\RegistrarVenta;
use App\Support\BusquedaTabla;
use App\Support\PlanPago;
use App\Support\Reporte;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
    /** Metodos de pago disponibles (contado). QR (#10) difiere el cobro al flujo asincrono. */
    private const METODOS = [Pago::METODO_EFECTIVO, Pago::METODO_TRANSFERENCIA, Pago::METODO_TARJETA, Pago::METODO_QR];

    public function index(Request $request): Response
    {
        [$filtros, $query] = $this->consultaFiltrada($request);

        $ventas = $query
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
            'filtros' => $filtros,
            'puedeCrear' => $request->user()->tienePermiso('ventas', 'registrar'),
            'puedeEliminar' => $request->user()->tienePermiso('ventas', 'eliminar'),
            'puedeReportar' => $request->user()->tienePermiso('ventas', 'reportar'),
        ]);
    }

    /**
     * Genera el reporte de la lista FILTRADA en PDF o CSV (Excel). Reusa los mismos filtros que el
     * index. Contenido: una fila por venta + total. Ruta protegida con permiso:ventas,listar.
     */
    public function reporte(Request $request): mixed
    {
        [$filtros, $query] = $this->consultaFiltrada($request);
        $ventas = $query->get();

        $columnas = ['Fecha', 'Cliente', 'Ítems', 'Total (Bs)', 'Tipo de pago', 'Estado de pago', 'Estado'];
        $filas = $ventas->map(fn (Venta $v) => [
            $v->fecha_venta?->format('d/m/Y'),
            $v->cliente?->name,
            $v->detalles_count,
            number_format((float) $v->monto_total, 2, '.', ''),
            ucfirst($v->tipo_pago),
            $v->estado_pago === Venta::PAGO_PAGADA ? 'Pagada' : 'Pendiente',
            $v->estado === Venta::ESTADO_ANULADA ? 'Anulada' : 'Registrada',
        ])->all();
        $sumaTotal = number_format((float) $ventas->sum('monto_total'), 2, '.', '');

        return Reporte::generar($request->string('formato')->toString(), [
            'titulo' => 'Reporte de Ventas',
            'subtitulo' => $this->descripcionFiltros($filtros),
            'columnas' => $columnas,
            'filas' => $filas,
            'filaTotal' => ['TOTAL', '', '', $sumaTotal, '', '', ''],
        ], 'ventas');
    }

    /**
     * Construye la consulta de ventas aplicando los filtros del request
     * (cliente/tipo_pago/estado_pago/estado/fechas). La comparten index() y reporte().
     *
     * @return array{0: array<string, mixed>, 1: Builder}
     */
    private function consultaFiltrada(Request $request): array
    {
        $filtros = [
            'q' => $request->string('q')->toString() ?: null,
            'tipo_pago' => $request->string('tipo_pago')->toString() ?: null,
            'estado_pago' => $request->string('estado_pago')->toString() ?: null,
            'estado' => $request->string('estado')->toString() ?: null,
            'desde' => $request->date('desde')?->toDateString(),
            'hasta' => $request->date('hasta')?->toDateString(),
        ];

        $query = Venta::query()
            ->with('cliente:id,name')
            ->withCount('detalles')
            ->when($filtros['q'], fn ($q, $t) => BusquedaTabla::aplicar($q, $t, [], ['cliente' => ['name']]))
            ->when($filtros['tipo_pago'], fn ($q, $t) => $q->where('tipo_pago', $t))
            ->when($filtros['estado_pago'], fn ($q, $e) => $q->where('estado_pago', $e))
            ->when($filtros['estado'], fn ($q, $e) => $q->where('estado', $e))
            ->when($filtros['desde'], fn ($q, $d) => $q->whereDate('fecha_venta', '>=', $d))
            ->when($filtros['hasta'], fn ($q, $h) => $q->whereDate('fecha_venta', '<=', $h))
            ->orderByDesc('fecha_venta')
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
        if ($f['tipo_pago']) {
            $partes[] = 'Tipo de pago: '.ucfirst($f['tipo_pago']);
        }
        if ($f['estado_pago']) {
            $partes[] = 'Estado de pago: '.ucfirst($f['estado_pago']);
        }
        if ($f['estado']) {
            $partes[] = 'Estado: '.ucfirst($f['estado']);
        }
        if ($f['desde']) {
            $partes[] = 'Desde: '.$f['desde'];
        }
        if ($f['hasta']) {
            $partes[] = 'Hasta: '.$f['hasta'];
        }
        $txt = $partes ? implode(' · ', $partes) : 'Sin filtros (todas las ventas)';

        return $txt.' — Generado: '.now()->format('d/m/Y H:i');
    }

    public function create(): Response
    {
        // Una sola promo vigente por producto (la regla de no solapamiento lo garantiza).
        $promosVigentes = Promocion::vigente()->get()->keyBy('producto_id');

        $productos = Producto::where('activo', true)->orderBy('nombre')->get()
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

    public function store(Request $request, RegistrarVenta $registrar): RedirectResponse
    {
        $datos = $this->validar($request);

        // Contado por QR: NO se registra la venta todavia. Se valida el stock/monto y se guarda la
        // intencion en sesion; la venta se registra recien al confirmar el pago del QR (con polling).
        if ($datos['tipo_pago'] === Venta::TIPO_CONTADO && ($datos['metodo'] ?? null) === Pago::METODO_QR) {
            $monto = $registrar->previsualizar($datos['lineas']); // valida stock; lanza ValidationException

            $request->session()->put('qr_venta', [
                'payment_number' => null,
                'transaction_id' => null,
                'payload' => [
                    'cliente_id' => $datos['cliente_id'],
                    'fecha_venta' => $datos['fecha_venta'],
                    'direccion_envio' => $datos['direccion_envio'] ?? null,
                    'tipo_pago' => Venta::TIPO_CONTADO,
                    'metodo' => Pago::METODO_QR,
                    'numero_cuotas' => null,
                    'lineas' => $datos['lineas'],
                ],
                'monto' => $monto,
                'origen' => 'admin',
                'retorno' => 'ventas.index',
                'titulo' => 'Cobro de venta al contado',
            ]);

            return redirect()->route('pagos.qr-venta');
        }

        // Resto (contado por otros medios o credito): toda la logica de negocio vive en el servicio,
        // compartido con la tienda autoservicio.
        $venta = $registrar->ejecutar($datos);

        // Contado no-QR: se pago al registrar -> comprobante. Credito: solo se registro el plan (toast).
        if ($datos['tipo_pago'] === Venta::TIPO_CONTADO) {
            $cuota = Pago::where('venta_id', $venta->id)->where('numero_cuota', 1)->first();
            if ($cuota) {
                return $this->comprobantePago($cuota, "Venta #{$venta->id}", 'ventas.index');
            }
        }

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
                    'fecha_pago' => $p->fecha_pago?->format('d/m/Y H:i'),
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
                Rule::exists('producto', 'id')->where('activo', true),
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
