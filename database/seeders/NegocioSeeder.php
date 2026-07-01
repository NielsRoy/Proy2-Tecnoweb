<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\DetalleVenta;
use App\Models\Inventario;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Promocion;
use App\Models\User;
use App\Models\Venta;
use App\Services\RegistrarVenta;
use App\Support\PlanPago;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Datos de ejemplo del negocio (catalogo de minimarket). Reusa los users demo de UsuarioRolSeeder
 * (varios clientes y proveedores). Genera un volumen razonable de compras y ventas repartidas en el
 * ultimo año, con distintos clientes/proveedores/metodos/tipos de pago y promos aplicadas, para que
 * los GRAFICOS del Dashboard (CU8) se vean poblados. Idempotente por conteos: las compras/ventas solo
 * se crean si aun no hay ninguna (para re-poblar de cero: `php artisan migrate:fresh --seed`).
 */
class NegocioSeeder extends Seeder
{
    public function run(): void
    {
        // Categorias (foto NULL por ahora; la subida del banner se hace desde el CRUD de Categorias).
        $categorias = [
            ['Abarrotes', 'Productos basicos de despensa', 1],
            ['Bebidas', 'Gaseosas, cafe e infusiones', 2],
            ['Limpieza', 'Articulos de limpieza y aseo', 3],
            ['Snacks', 'Galletas y picoteos', 4],
        ];
        foreach ($categorias as [$nombre, $descripcion, $orden]) {
            Categoria::firstOrCreate(
                ['nombre' => $nombre],
                ['descripcion' => $descripcion, 'orden' => $orden, 'activo' => true],
            );
        }
        $catId = fn (string $nombre) => Categoria::where('nombre', $nombre)->value('id');

        // Catalogo (foto NULL por ahora; la subida de imagenes se hara en el CU de Productos).
        $productos = [
            ['Aceite de Girasol 5L', 'Aceite comestible de girasol, botella de 5 litros', 49.99, 'Abarrotes'],
            ['Detergente en Polvo 3kg', 'Detergente para ropa, bolsa de 3 kg', 39.99, 'Limpieza'],
            ['Arroz Grano de Oro 5kg', 'Arroz blanco de grano largo, bolsa de 5 kg', 29.99, 'Abarrotes'],
            ['Azucar Refinada 2kg', 'Azucar blanca refinada, bolsa de 2 kg', 15.99, 'Abarrotes'],
            ['Cafe Instantaneo 170g', 'Cafe instantaneo en frasco de 170 g', 24.99, 'Bebidas'],
            ['Pack Gaseosa 2L x6', 'Pack de 6 gaseosas de 2 litros', 59.99, 'Bebidas'],
            ['Fideo Spaghetti 1kg', 'Pack de fideos spaghetti, 1 kg', 12.99, 'Abarrotes'],
            ['Atun en Lata Pack x3', 'Atun en aceite, pack de 3 latas', 19.99, 'Abarrotes'],
            ['Papel Higienico x12', 'Papel higienico doble hoja, paquete de 12 rollos', 45.99, 'Limpieza'],
            ['Galletas Surtidas', 'Paquete de galletas dulces surtidas', 9.99, 'Snacks'],
            ['Leche en Polvo 1kg', 'Leche entera en polvo, bolsa de 1 kg', 38.99, 'Abarrotes'],
            ['Mantequilla 200g', 'Mantequilla con sal, barra de 200 g', 12.50, 'Abarrotes'],
            ['Jabon de Tocador x4', 'Jabon de tocador, pack de 4 unidades', 14.99, 'Limpieza'],
            ['Lavavajilla 500ml', 'Lavavajilla liquido, botella de 500 ml', 11.99, 'Limpieza'],
            ['Gaseosa 3L', 'Gaseosa sabor cola, botella de 3 litros', 13.99, 'Bebidas'],
            ['Jugo de Naranja 1L', 'Jugo de naranja, caja de 1 litro', 9.50, 'Bebidas'],
            ['Chocolate en Barra', 'Chocolate con leche, barra de 100 g', 6.99, 'Snacks'],
            ['Papas Fritas 150g', 'Papas fritas saladas, bolsa de 150 g', 7.50, 'Snacks'],
            ['Sardina en Lata', 'Sardina en salsa de tomate, lata individual', 8.99, 'Abarrotes'],
            ['Harina 1kg', 'Harina de trigo, bolsa de 1 kg', 7.99, 'Abarrotes'],
        ];
        foreach ($productos as [$nombre, $descripcion, $precio, $categoria]) {
            Producto::firstOrCreate(
                ['nombre' => $nombre],
                ['descripcion' => $descripcion, 'precio' => $precio, 'stock' => 0,
                    'categoria_id' => $catId($categoria), 'activo' => true],
            );
        }

        // Promociones vigentes (2026) por producto.
        $promos = [
            ['Aceite de Girasol 5L', 'Oferta Aceite', 'Descuento del 10% en aceite', 'porcentaje', 10],
            ['Arroz Grano de Oro 5kg', 'Oferta Arroz', 'Descuento del 20% en arroz', 'porcentaje', 20],
            ['Pack Gaseosa 2L x6', 'Combo Bebidas', 'Bs 10 de descuento por pack', 'monto', 10],
            ['Cafe Instantaneo 170g', 'Oferta Cafe', 'Descuento del 15% en cafe', 'porcentaje', 15],
            ['Papel Higienico x12', 'Oferta Papel', 'Bs 5 de descuento', 'monto', 5],
        ];
        foreach ($promos as [$prod, $nombre, $desc, $tipo, $valor]) {
            $producto = Producto::where('nombre', $prod)->first();
            if ($producto) {
                Promocion::firstOrCreate(
                    ['producto_id' => $producto->id, 'nombre' => $nombre],
                    [
                        'descripcion' => $desc, 'tipo_descuento' => $tipo, 'valor' => $valor,
                        'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-12-31', 'activo' => true,
                    ],
                );
            }
        }

        $this->generarTransacciones();
    }

    /**
     * Genera compras (stock inicial, varios proveedores) y ventas (varios clientes, meses, metodos,
     * contado/credito, promos). Deterministico (mt_srand) y con stock controlado para no vender de mas.
     */
    private function generarTransacciones(): void
    {
        mt_srand(2025);

        $productos = Producto::orderBy('id')->get();
        $proveedores = User::conRolVigente('Proveedor')->orderBy('id')->get();
        $clientes = User::conRolVigente('Cliente')->orderBy('id')->get();
        $clienteDemo = $clientes->firstWhere('email', 'cliente@tiendadyd.com');

        // ── COMPRAS: cada proveedor abastece una parte del catalogo (stock inicial). ──
        if ($proveedores->isNotEmpty() && Compra::count() === 0) {
            $grupos = $productos->chunk((int) ceil($productos->count() / $proveedores->count()))->values();
            foreach ($proveedores as $idx => $prov) {
                $grupo = $grupos[$idx] ?? collect();
                if ($grupo->isEmpty()) {
                    continue;
                }
                // Compra principal (lote grande) + una compra menor de reposicion en otro mes.
                $lineasGrandes = $grupo->map(fn (Producto $p) => [$p, mt_rand(60, 140), round((float) $p->precio * 0.75, 2)])->all();
                $this->crearCompra($prov, now()->subMonths(10)->toDateString(), $lineasGrandes);

                $lineasChicas = $grupo->random(min(3, $grupo->count()))
                    ->map(fn (Producto $p) => [$p, mt_rand(15, 40), round((float) $p->precio * 0.78, 2)])->all();
                $this->crearCompra($prov, now()->subMonths(mt_rand(3, 6))->toDateString(), $lineasChicas);
            }
        }

        // ── VENTAS: ~34 intentos repartidos en el ultimo año. ──
        if ($clientes->isEmpty() || Venta::count() > 0) {
            return;
        }

        $promosVigentes = Promocion::vigente()->get()->keyBy('producto_id');
        $disponible = Producto::pluck('stock', 'id')->toArray();
        $metodos = [Pago::METODO_EFECTIVO, Pago::METODO_TRANSFERENCIA, Pago::METODO_TARJETA, Pago::METODO_QR];
        $creditoAsignado = []; // cliente_id => true (max 1 credito por cliente en la demo)

        for ($v = 0; $v < 34; $v++) {
            $cli = $clientes[mt_rand(0, $clientes->count() - 1)];
            $fecha = now()->subMonths(mt_rand(0, 10))->subDays(mt_rand(0, 27))->toDateString();

            // 1-3 lineas de productos distintos con stock disponible.
            $lineas = [];
            $usados = [];
            $numLineas = mt_rand(1, 3);
            for ($l = 0; $l < $numLineas; $l++) {
                $p = $productos[mt_rand(0, $productos->count() - 1)];
                if (in_array($p->id, $usados, true)) {
                    continue;
                }
                $cant = mt_rand(1, 5);
                if (($disponible[$p->id] ?? 0) < $cant) {
                    continue;
                }
                $usados[] = $p->id;
                $disponible[$p->id] -= $cant;

                $promo = $promosVigentes->get($p->id);
                $usarPromo = $promo && mt_rand(0, 1) === 1;
                $precio = $usarPromo ? $promo->precioConDescuento((float) $p->precio) : (float) $p->precio;
                $lineas[] = [$p, $cant, $usarPromo ? $promo->id : null, round($precio, 2)];
            }
            if (empty($lineas)) {
                continue;
            }

            // Tipo de pago: ~30% credito si el monto base alcanza el minimo y el cliente no tiene credito
            // ya. El cliente demo (cliente@) se reserva para el credito controlado de abajo (Mis pagos).
            $base = round(array_sum(array_map(fn ($x) => $x[1] * $x[3], $lineas)), 2);
            $puedeCredito = $base >= PlanPago::MONTO_MINIMO_CREDITO
                && empty($creditoAsignado[$cli->id])
                && $cli->id !== $clienteDemo?->id;
            $esCredito = $puedeCredito && mt_rand(0, 2) === 0;

            $metodo = $metodos[mt_rand(0, count($metodos) - 1)];
            if ($esCredito) {
                $creditoAsignado[$cli->id] = true;
                $this->crearVenta($cli, $fecha, $lineas, Venta::TIPO_CREDITO, $metodo, 3);
            } else {
                $this->crearVenta($cli, $fecha, $lineas, Venta::TIPO_CONTADO, $metodo, 1);
            }
        }

        // ── Venta A CREDITO controlada para el cliente demo (para observar "Mis pagos"): plan activo +
        // una cuota pagada en el historial. Reusa RegistrarVenta (misma logica que la tienda/venta admin).
        $aceite = Producto::where('nombre', 'Aceite de Girasol 5L')->first();
        $sinCredito = $clienteDemo && Venta::where('cliente_id', $clienteDemo->id)
            ->where('tipo_pago', Venta::TIPO_CREDITO)->doesntExist();

        if ($sinCredito && $aceite && $aceite->stock >= 3) {
            $ventaCredito = app(RegistrarVenta::class)->ejecutar([
                'cliente_id' => $clienteDemo->id,
                'fecha_venta' => now()->toDateString(),
                'direccion_envio' => 'Av. Cliente 123',
                'tipo_pago' => Venta::TIPO_CREDITO,
                'metodo' => null, // credito: el metodo lo define cada cuota al cobrarse
                'numero_cuotas' => 3, // monto base ~135 (aceite x3) -> tramo 100-299.99 admite hasta 3
                'lineas' => [['producto_id' => $aceite->id, 'cantidad' => 3]],
            ]);

            $primera = Pago::where('venta_id', $ventaCredito->id)->orderBy('numero_cuota')->first();
            if ($primera) {
                DB::transaction(fn () => Pago::saldar($primera, Pago::METODO_EFECTIVO));
            }
        }
    }

    /**
     * Crea una compra (cabecera + detalle + ingreso de inventario + stock) de un proveedor.
     *
     * @param  array<int, array{0: Producto, 1: int, 2: float}>  $lineas  [producto, cantidad, costo]
     */
    private function crearCompra(User $proveedor, string $fecha, array $lineas): void
    {
        if (empty($lineas)) {
            return;
        }

        DB::transaction(function () use ($proveedor, $fecha, $lineas) {
            $compra = Compra::create([
                'proveedor_id' => $proveedor->id,
                'fecha_compra' => $fecha,
                'monto_total' => 0,
                'estado' => Compra::ESTADO_REGISTRADA,
            ]);

            $total = 0;
            foreach ($lineas as [$p, $cant, $costo]) {
                $sub = round($cant * $costo, 2);
                DetalleCompra::create([
                    'compra_id' => $compra->id, 'producto_id' => $p->id,
                    'cantidad' => $cant, 'precio_unitario' => $costo, 'subtotal' => $sub,
                ]);
                Inventario::create([
                    'producto_id' => $p->id, 'cantidad' => $cant, 'tipo_movimiento' => 'ingreso',
                    'fecha_movimiento' => $fecha, 'motivo' => 'compra',
                ]);
                $p->increment('stock', $cant);
                $total += $sub;
            }
            $compra->update(['monto_total' => round($total, 2)]);
        });
    }

    /**
     * Crea una venta (cabecera + detalle + salida de inventario + stock + cuotas). Contado = 1 cuota
     * pagada; credito = cronograma (PlanPago) con la 1ª cuota pagada y el resto pendiente.
     *
     * @param  array<int, array{0: Producto, 1: int, 2: int|null, 3: float}>  $lineas  [producto, cantidad, promoId, precioUnit]
     */
    private function crearVenta(User $cliente, string $fecha, array $lineas, string $tipo, string $metodo, int $cuotas): void
    {
        DB::transaction(function () use ($cliente, $fecha, $lineas, $tipo, $metodo, $cuotas) {
            $base = round(array_sum(array_map(fn ($x) => $x[1] * $x[3], $lineas)), 2);
            $esCredito = $tipo === Venta::TIPO_CREDITO;
            $montoTotal = $esCredito ? PlanPago::montoTotalCredito($base, $cuotas) : $base;

            $venta = Venta::create([
                'cliente_id' => $cliente->id,
                'fecha_venta' => $fecha,
                'direccion_envio' => 'Av. Demo '.mt_rand(100, 999),
                'monto_total' => $montoTotal,
                'tipo_pago' => $tipo,
                'numero_cuotas' => $esCredito ? $cuotas : 1,
                'estado_pago' => $esCredito ? Venta::PAGO_PENDIENTE : Venta::PAGO_PAGADA,
                'estado' => Venta::ESTADO_REGISTRADA,
            ]);

            foreach ($lineas as [$p, $cant, $promoId, $precio]) {
                DetalleVenta::create([
                    'venta_id' => $venta->id, 'producto_id' => $p->id, 'cantidad' => $cant,
                    'precio_unitario' => $precio, 'subtotal' => round($cant * $precio, 2), 'promocion_id' => $promoId,
                ]);
                Inventario::create([
                    'producto_id' => $p->id, 'cantidad' => $cant, 'tipo_movimiento' => 'salida',
                    'fecha_movimiento' => $fecha, 'motivo' => 'venta',
                ]);
                $p->decrement('stock', $cant);
            }

            if ($esCredito) {
                // Cronograma: la 1ª cuota queda pagada (historial) y el resto pendiente (plan activo).
                foreach (PlanPago::cronograma($montoTotal, $cuotas, $fecha) as $c) {
                    $pagada = $c['numero_cuota'] === 1;
                    Pago::create([
                        'venta_id' => $venta->id, 'numero_cuota' => $c['numero_cuota'], 'monto' => $c['monto'],
                        'fecha_vencimiento' => $c['fecha_vencimiento'],
                        'fecha_pago' => $pagada ? $fecha : null,
                        'metodo' => $pagada ? $metodo : null,
                        'estado' => $pagada ? Pago::ESTADO_PAGADO : Pago::ESTADO_PENDIENTE,
                    ]);
                }
            } else {
                Pago::create([
                    'venta_id' => $venta->id, 'numero_cuota' => 1, 'monto' => $montoTotal,
                    'fecha_vencimiento' => $fecha, 'fecha_pago' => $fecha,
                    'metodo' => $metodo, 'estado' => Pago::ESTADO_PAGADO,
                ]);
            }
        });
    }
}
