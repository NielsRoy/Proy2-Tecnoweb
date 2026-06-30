<?php

namespace Database\Seeders;

use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\DetalleVenta;
use App\Models\Inventario;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Promocion;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Datos de ejemplo del negocio (catalogo de minimarket). Reusa los users demo de
 * UsuarioRolSeeder (proveedor@/cliente@). Idempotente: los productos/promos usan
 * firstOrCreate; la compra y la venta demo solo se crean si aun no hay ninguna
 * (asi el stock no se descuadra al re-ejecutar el seeder).
 */
class NegocioSeeder extends Seeder
{
    public function run(): void
    {
        // Catalogo (foto NULL por ahora; la subida de imagenes se hara en el CU de Productos).
        $productos = [
            ['Aceite de Girasol 5L', 'Aceite comestible de girasol, botella de 5 litros', 49.99],
            ['Detergente en Polvo 3kg', 'Detergente para ropa, bolsa de 3 kg', 39.99],
            ['Arroz Grano de Oro 5kg', 'Arroz blanco de grano largo, bolsa de 5 kg', 29.99],
            ['Azucar Refinada 2kg', 'Azucar blanca refinada, bolsa de 2 kg', 15.99],
            ['Cafe Instantaneo 170g', 'Cafe instantaneo en frasco de 170 g', 24.99],
            ['Pack Gaseosa 2L x6', 'Pack de 6 gaseosas de 2 litros', 59.99],
            ['Fideo Spaghetti 1kg', 'Pack de fideos spaghetti, 1 kg', 12.99],
            ['Atun en Lata Pack x3', 'Atun en aceite, pack de 3 latas', 19.99],
            ['Papel Higienico x12', 'Papel higienico doble hoja, paquete de 12 rollos', 45.99],
            ['Galletas Surtidas', 'Paquete de galletas dulces surtidas', 9.99],
        ];
        foreach ($productos as [$nombre, $descripcion, $precio]) {
            Producto::firstOrCreate(
                ['nombre' => $nombre],
                ['descripcion' => $descripcion, 'precio' => $precio, 'stock' => 0, 'est' => true],
            );
        }

        // Promociones vigentes (2026) por producto.
        $promos = [
            ['Aceite de Girasol 5L', 'Oferta Aceite', 'Descuento del 10% en aceite', 'porcentaje', 10],
            ['Arroz Grano de Oro 5kg', 'Oferta Arroz', 'Descuento del 20% en arroz', 'porcentaje', 20],
            ['Pack Gaseosa 2L x6', 'Combo Bebidas', 'Bs 10 de descuento por pack', 'monto', 10],
        ];
        foreach ($promos as [$prod, $nombre, $desc, $tipo, $valor]) {
            $producto = Producto::where('nombre', $prod)->first();
            if ($producto) {
                Promocion::firstOrCreate(
                    ['producto_id' => $producto->id, 'nombre' => $nombre],
                    [
                        'descripcion' => $desc, 'tipo_descuento' => $tipo, 'valor' => $valor,
                        'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-12-31', 'est' => true,
                    ],
                );
            }
        }

        $proveedor = User::where('email', 'proveedor@tiendadyd.com')->first();
        $cliente = User::where('email', 'cliente@tiendadyd.com')->first();

        // Compra demo (ingreso de stock). Solo si no hay compras todavia.
        if ($proveedor && Compra::count() === 0) {
            DB::transaction(function () use ($proveedor) {
                $lineas = [
                    ['Arroz Grano de Oro 5kg', 60, 25.00],
                    ['Aceite de Girasol 5L', 50, 42.00],
                    ['Pack Gaseosa 2L x6', 30, 50.00],
                    ['Galletas Surtidas', 40, 8.00], // la venta demo vende 3 -> debe tener stock
                ];
                $compra = Compra::create([
                    'proveedor_id' => $proveedor->id,
                    'fecha_compra' => now()->toDateString(),
                    'monto_total' => 0,
                ]);
                $total = 0;
                foreach ($lineas as [$nombre, $cant, $costo]) {
                    $p = Producto::where('nombre', $nombre)->first();
                    $sub = round($cant * $costo, 2);
                    DetalleCompra::create([
                        'compra_id' => $compra->id, 'producto_id' => $p->id,
                        'cantidad' => $cant, 'precio_unitario' => $costo, 'subtotal' => $sub,
                    ]);
                    Inventario::create([
                        'producto_id' => $p->id, 'cantidad' => $cant, 'tipo_movimiento' => 'ingreso',
                        'fecha_movimiento' => now()->toDateString(), 'motivo' => 'compra',
                    ]);
                    $p->increment('stock', $cant);
                    $total += $sub;
                }
                $compra->update(['monto_total' => $total]);
            });
        }

        // Venta demo (contado, pagada; salida de stock). Solo si no hay ventas todavia.
        if ($cliente && Venta::count() === 0) {
            DB::transaction(function () use ($cliente) {
                // Linea con promo (arroz 20% -> 23.99) y linea sin promo (galletas).
                $arroz = Producto::where('nombre', 'Arroz Grano de Oro 5kg')->first();
                $promoArroz = Promocion::where('nombre', 'Oferta Arroz')->first();
                $galletas = Producto::firstOrCreate(
                    ['nombre' => 'Galletas Surtidas'],
                    ['descripcion' => 'Paquete de galletas dulces surtidas', 'precio' => 9.99, 'stock' => 0, 'est' => true],
                );

                $venta = Venta::create([
                    'cliente_id' => $cliente->id,
                    'fecha_venta' => now()->toDateString(),
                    'direccion_envio' => 'Av. Real 456',
                    'monto_total' => 0,
                    'tipo_pago' => 'contado',
                    'numero_cuotas' => 1,
                    'estado_pago' => 'pagada',
                    'estado' => 'registrada',
                ]);

                $lineas = [
                    [$arroz, 2, 23.99, $promoArroz?->id],  // arroz con 20% de descuento
                    [$galletas, 3, 9.99, null],            // galletas sin promo
                ];
                $total = 0;
                foreach ($lineas as [$p, $cant, $precio, $promoId]) {
                    if (! $p) {
                        continue;
                    }
                    $sub = round($cant * $precio, 2);
                    DetalleVenta::create([
                        'venta_id' => $venta->id, 'producto_id' => $p->id, 'cantidad' => $cant,
                        'precio_unitario' => $precio, 'subtotal' => $sub, 'promocion_id' => $promoId,
                    ]);
                    Inventario::create([
                        'producto_id' => $p->id, 'cantidad' => $cant, 'tipo_movimiento' => 'salida',
                        'fecha_movimiento' => now()->toDateString(), 'motivo' => 'venta',
                    ]);
                    $p->decrement('stock', $cant);
                    $total += $sub;
                }
                $venta->update(['monto_total' => $total]);

                // Cronograma: contado = 1 cuota, ya pagada.
                Pago::create([
                    'venta_id' => $venta->id, 'numero_cuota' => 1, 'monto' => $total,
                    'fecha_vencimiento' => now()->toDateString(), 'fecha_pago' => now()->toDateString(),
                    'metodo' => 'efectivo', 'estado' => 'pagado',
                ]);
            });
        }
    }
}
