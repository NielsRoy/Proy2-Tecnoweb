<?php

namespace App\Services;

use App\Models\Bitacora;
use App\Models\Inventario;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Promocion;
use App\Models\Venta;
use App\Support\PlanPago;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra una venta (cabecera + detalle + movimientos de inventario + cuotas). Es el UNICO punto
 * que crea ventas, reutilizado por el back-office (VentaController, vendedor elige al cliente) y por
 * la tienda autoservicio (TiendaController, el cliente se compra a si mismo).
 *
 * Hace la validacion de NEGOCIO compartida (stock, promo vigente por linea, reglas de credito y
 * bloqueo de "un solo plan a credito activo") y lanza ValidationException en espanol. La validacion
 * de la PETICION (campos, quien puede comprar) la hace cada controlador antes de llamar aqui.
 *
 *  - CONTADO: la venta nace 'pagada' (su unica cuota se salda con el metodo elegido).
 *  - CREDITO: genera el cronograma de cuotas 'pendiente' (se cobran en el modulo Pagos).
 */
class RegistrarVenta
{
    /**
     * @param  array{cliente_id:int, fecha_venta:string, direccion_envio:?string, tipo_pago:string, metodo:?string, numero_cuotas:?int, es_pedido?:bool, lineas:array<int, array{producto_id:int, cantidad:int}>}  $datos
     */
    public function ejecutar(array $datos): Venta
    {
        // 1) Calcular lineas en el server (precio con promo vigente) y validar stock.
        [$lineas, $base] = $this->resolverLineas($datos['lineas']);

        $esCredito = $datos['tipo_pago'] === Venta::TIPO_CREDITO;
        // Pedido = checkout del cliente al contado + efectivo: la venta nace 'pedido' (aun sin cobrar,
        // el admin la confirma). Solo aplica a contado; el credito nunca es pedido.
        $esPedido = ($datos['es_pedido'] ?? false) && ! $esCredito;
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
                    'tipo_pago' => 'El cliente ya tiene un plan a crédito activo (solo se permite uno a la vez).',
                ]);
            }

            $montoTotal = PlanPago::montoTotalCredito($base, $numeroCuotas);
        }

        // 3) Persistir todo en una transaccion.
        return DB::transaction(function () use ($datos, $lineas, $montoTotal, $esCredito, $esPedido, $numeroCuotas) {
            $venta = Venta::create([
                'cliente_id' => $datos['cliente_id'],
                'fecha_venta' => $datos['fecha_venta'],
                'direccion_envio' => $datos['direccion_envio'] ?? null,
                'monto_total' => $montoTotal,
                'tipo_pago' => $datos['tipo_pago'],
                'numero_cuotas' => $numeroCuotas,
                'estado_pago' => Venta::PAGO_PENDIENTE, // se vuelve 'pagada' al saldar (contado)
                'estado' => $esPedido ? Venta::ESTADO_PEDIDO : Venta::ESTADO_REGISTRADA,
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
            } elseif ($esPedido) {
                // Pedido (checkout efectivo): NO se crea cuota ni se salda nada; queda pendiente de que
                // el admin lo confirme (ahi se crea la cuota #1 y se salda en efectivo). El stock ya salio.
            } else {
                // Contado: una unica cuota que se salda ya con el metodo elegido (-> venta 'pagada').
                // El contado por QR NO llega aqui hasta que el pago esta confirmado: la venta se
                // registra recien tras confirmar el QR (ver PagoQrController::estadoVenta), asi que
                // cuando se llama a este servicio el pago ya es efectivo y se salda igual que el resto.
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
                $esPedido
                    ? "Registró el pedido #{$venta->id} (contado efectivo, Bs {$montoTotal})"
                    : "Registró la venta #{$venta->id} ({$venta->tipo_pago}, Bs {$montoTotal})",
                'ventas',
            );

            return $venta;
        });
    }

    /**
     * Previsualiza/valida las lineas SIN persistir nada: valida stock y devuelve el monto base (suma
     * de subtotales con la promo vigente aplicada). Lo usa el checkout contado+QR para validar y
     * conocer el importe ANTES de generar el QR (la venta se registra recien al confirmar el pago).
     * Lanza ValidationException en espanol si falta stock.
     *
     * @param  array<int, array{producto_id:int, cantidad:int}>  $lineasInput
     */
    public function previsualizar(array $lineasInput): float
    {
        [, $base] = $this->resolverLineas($lineasInput);

        return $base;
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
            $producto = Producto::where('activo', true)->findOrFail($entrada['producto_id']);

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
}
