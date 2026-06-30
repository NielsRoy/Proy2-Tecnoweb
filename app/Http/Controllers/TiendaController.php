<?php

namespace App\Http\Controllers;

use App\Models\Carrito;
use App\Models\Producto;
use App\Models\Promocion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tienda autoservicio en "/inicio": catalogo de productos para que el usuario logueado compre por su
 * cuenta (carrito + checkout en CarritoController). Es de LIBRE ACCESO para todo autenticado (solo
 * 'auth', fuera de la matriz). Muestra foto, precio y promo vigente de cada producto.
 */
class TiendaController extends Controller
{
    public function index(Request $request): Response
    {
        $promosVigentes = Promocion::vigente()->get()->keyBy('producto_id');

        $productos = Producto::where('est', true)->orderBy('nombre')->get()
            ->map(function (Producto $p) use ($promosVigentes) {
                $promo = $promosVigentes->get($p->id);

                return [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'descripcion' => $p->descripcion,
                    'foto_url' => $p->foto ? Storage::disk('public')->url($p->foto) : null,
                    'precio' => (float) $p->precio,
                    'precio_final' => $promo ? $promo->precioConDescuento((float) $p->precio) : (float) $p->precio,
                    'promocion_nombre' => $promo?->nombre,
                    'promocion_tipo' => $promo?->tipo_descuento,
                    'promocion_valor' => $promo ? (float) $promo->valor : null,
                    'stock' => $p->stock,
                ];
            });

        // Total de unidades en el carrito del usuario (para el badge del enlace al carrito).
        $carritoCount = (int) Carrito::where('cliente_id', $request->user()->id)->sum('cantidad');

        return Inertia::render('Inicio', [
            'productos' => $productos,
            'carritoCount' => $carritoCount,
        ]);
    }
}
