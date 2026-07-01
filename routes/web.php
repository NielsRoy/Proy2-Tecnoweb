<?php

use App\Http\Controllers\AccesoController;
use App\Http\Controllers\BitacoraController;
use App\Http\Controllers\CarritoController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\MisComprasController;
use App\Http\Controllers\MisPagosController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\PagoQrController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\PromocionController;
use App\Http\Controllers\TiendaController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// Inicio = TIENDA autoservicio: catalogo + carrito + checkout. De LIBRE ACCESO para TODO usuario
// autenticado (solo 'auth', sin 'permiso:') -> no va en la matriz ni se desactiva por rol. Es el
// destino tras login/registro, del logo y del boton "Ingresar al sistema".
Route::middleware('auth')->group(function () {
    Route::get('inicio', [TiendaController::class, 'index'])->name('inicio');

    Route::get('carrito', [CarritoController::class, 'index'])->name('carrito.index');
    Route::post('carrito', [CarritoController::class, 'agregar'])->name('carrito.agregar');
    Route::put('carrito/{producto}', [CarritoController::class, 'actualizar'])->name('carrito.actualizar');
    Route::delete('carrito/{producto}', [CarritoController::class, 'quitar'])->name('carrito.quitar');
    Route::post('carrito/checkout', [CarritoController::class, 'checkout'])->name('carrito.checkout');

    // Pago por QR de PagoFacil (requisito #10): flujo asincrono comun a los 4 puntos de pago. Solo
    // 'auth' (la autorizacion fina -dueño de la venta o admin de pagos- la hace PagoQrController).
    // generar/estado devuelven JSON (los consume el polling del navegador), no Inertia.
    // Variante A: cobro de una cuota YA registrada (credito, o contado por otros medios).
    Route::get('qr/{pago}', [PagoQrController::class, 'mostrar'])->name('pagos.qr');
    Route::post('qr/{pago}/generar', [PagoQrController::class, 'generar'])->name('pagos.qr.generar');
    Route::get('qr/{pago}/estado', [PagoQrController::class, 'estado'])->name('pagos.qr.estado');
    // Variante B: venta al contado por QR que NO se registra hasta confirmar el pago (intencion en sesion).
    Route::get('qr-venta', [PagoQrController::class, 'mostrarVenta'])->name('pagos.qr-venta');
    Route::post('qr-venta/generar', [PagoQrController::class, 'generarVenta'])->name('pagos.qr-venta.generar');
    Route::get('qr-venta/estado', [PagoQrController::class, 'estadoVenta'])->name('pagos.qr-venta.estado');
    // Comprobante ("Pago confirmado") tras un pago NO-QR (datos por flash de sesion).
    Route::get('comprobante', [PagoQrController::class, 'comprobante'])->name('pagos.comprobante');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')
        ->middleware('permiso:dashboard,ver')
        ->name('dashboard');

    // CRUD de Usuarios (modulo "usuarios"). Cada ruta exige su permiso correspondiente.
    Route::prefix('usuarios')->name('usuarios.')->group(function () {
        Route::get('/', [UsuarioController::class, 'index'])
            ->middleware('permiso:usuarios,listar')->name('index');
        Route::get('crear', [UsuarioController::class, 'create'])
            ->middleware('permiso:usuarios,registrar')->name('create');
        Route::post('/', [UsuarioController::class, 'store'])
            ->middleware('permiso:usuarios,registrar')->name('store');
        Route::get('reporte', [UsuarioController::class, 'reporte'])
            ->middleware('permiso:usuarios,reportar')->name('reporte');
        Route::get('{usuario}/editar', [UsuarioController::class, 'edit'])
            ->middleware('permiso:usuarios,modificar')->name('edit');
        Route::put('{usuario}', [UsuarioController::class, 'update'])
            ->middleware('permiso:usuarios,modificar')->name('update');
        Route::delete('{usuario}', [UsuarioController::class, 'destroy'])
            ->middleware('permiso:usuarios,eliminar')->name('destroy');
    });

    // CRUD de Productos (CU2, modulo "productos").
    Route::prefix('productos')->name('productos.')->group(function () {
        Route::get('/', [ProductoController::class, 'index'])
            ->middleware('permiso:productos,listar')->name('index');
        Route::get('crear', [ProductoController::class, 'create'])
            ->middleware('permiso:productos,registrar')->name('create');
        Route::post('/', [ProductoController::class, 'store'])
            ->middleware('permiso:productos,registrar')->name('store');
        Route::get('reporte', [ProductoController::class, 'reporte'])
            ->middleware('permiso:productos,reportar')->name('reporte');
        Route::get('{producto}/editar', [ProductoController::class, 'edit'])
            ->middleware('permiso:productos,modificar')->name('edit');
        Route::put('{producto}', [ProductoController::class, 'update'])
            ->middleware('permiso:productos,modificar')->name('update');
        Route::delete('{producto}', [ProductoController::class, 'destroy'])
            ->middleware('permiso:productos,eliminar')->name('destroy');
    });

    // CRUD de Categorias (modulo "categorias"). Agrupan productos y dan banner a la tienda.
    Route::prefix('categorias')->name('categorias.')->group(function () {
        Route::get('/', [CategoriaController::class, 'index'])
            ->middleware('permiso:categorias,listar')->name('index');
        Route::get('crear', [CategoriaController::class, 'create'])
            ->middleware('permiso:categorias,registrar')->name('create');
        Route::post('/', [CategoriaController::class, 'store'])
            ->middleware('permiso:categorias,registrar')->name('store');
        Route::get('{categoria}/editar', [CategoriaController::class, 'edit'])
            ->middleware('permiso:categorias,modificar')->name('edit');
        Route::put('{categoria}', [CategoriaController::class, 'update'])
            ->middleware('permiso:categorias,modificar')->name('update');
        Route::delete('{categoria}', [CategoriaController::class, 'destroy'])
            ->middleware('permiso:categorias,eliminar')->name('destroy');
    });

    // CU3 Compras (modulo "compras"): compra multi-producto. Sin edit/update (anular y re-registrar).
    // destroy = ANULAR (revierte stock + marca anulada), mapeado al permiso "eliminar".
    Route::prefix('compras')->name('compras.')->group(function () {
        Route::get('/', [CompraController::class, 'index'])
            ->middleware('permiso:compras,listar')->name('index');
        Route::get('crear', [CompraController::class, 'create'])
            ->middleware('permiso:compras,registrar')->name('create');
        Route::post('/', [CompraController::class, 'store'])
            ->middleware('permiso:compras,registrar')->name('store');
        // Reporte (PDF/CSV) de la lista filtrada. ANTES del comodin {compra} para que no lo capture.
        Route::get('reporte', [CompraController::class, 'reporte'])
            ->middleware('permiso:compras,reportar')->name('reporte');
        Route::get('{compra}', [CompraController::class, 'show'])
            ->middleware('permiso:compras,listar')->name('show');
        Route::delete('{compra}', [CompraController::class, 'destroy'])
            ->middleware('permiso:compras,eliminar')->name('destroy');
    });

    // CU5 Inventario (modulo "inventarios"): libro append-only. Solo listar y registrar ajuste
    // (sin edit/update/delete: para corregir se registra otro ajuste en sentido contrario).
    Route::prefix('inventarios')->name('inventarios.')->group(function () {
        Route::get('/', [InventarioController::class, 'index'])
            ->middleware('permiso:inventarios,listar')->name('index');
        Route::get('crear', [InventarioController::class, 'create'])
            ->middleware('permiso:inventarios,registrar')->name('create');
        Route::post('/', [InventarioController::class, 'store'])
            ->middleware('permiso:inventarios,registrar')->name('store');
        Route::get('reporte', [InventarioController::class, 'reporte'])
            ->middleware('permiso:inventarios,reportar')->name('reporte');
    });

    // CU6 Promociones (modulo "promociones"): CRUD por producto. Eliminar = baja logica.
    Route::prefix('promociones')->name('promociones.')->group(function () {
        Route::get('/', [PromocionController::class, 'index'])
            ->middleware('permiso:promociones,listar')->name('index');
        Route::get('crear', [PromocionController::class, 'create'])
            ->middleware('permiso:promociones,registrar')->name('create');
        Route::post('/', [PromocionController::class, 'store'])
            ->middleware('permiso:promociones,registrar')->name('store');
        Route::get('reporte', [PromocionController::class, 'reporte'])
            ->middleware('permiso:promociones,reportar')->name('reporte');
        Route::get('{promocion}/editar', [PromocionController::class, 'edit'])
            ->middleware('permiso:promociones,modificar')->name('edit');
        Route::put('{promocion}', [PromocionController::class, 'update'])
            ->middleware('permiso:promociones,modificar')->name('update');
        Route::delete('{promocion}', [PromocionController::class, 'destroy'])
            ->middleware('permiso:promociones,eliminar')->name('destroy');
    });

    // CU4 Ventas (modulo "ventas"): venta multi-producto (vista admin). Contado paga al registrar;
    // credito genera cronograma. Sin edit/update (anular y re-registrar). destroy = ANULAR.
    Route::prefix('ventas')->name('ventas.')->group(function () {
        Route::get('/', [VentaController::class, 'index'])
            ->middleware('permiso:ventas,listar')->name('index');
        Route::get('crear', [VentaController::class, 'create'])
            ->middleware('permiso:ventas,registrar')->name('create');
        Route::post('/', [VentaController::class, 'store'])
            ->middleware('permiso:ventas,registrar')->name('store');
        // Reporte (PDF/CSV) de la lista filtrada. ANTES del comodin {venta} para que no lo capture.
        Route::get('reporte', [VentaController::class, 'reporte'])
            ->middleware('permiso:ventas,reportar')->name('reporte');
        Route::get('{venta}', [VentaController::class, 'show'])
            ->middleware('permiso:ventas,listar')->name('show');
        Route::delete('{venta}', [VentaController::class, 'destroy'])
            ->middleware('permiso:ventas,eliminar')->name('destroy');
    });

    // CU7 Pagos (modulo "pagos"): listado de TODOS los pagos (filtrable) + cobro de la proxima cuota
    // pendiente de ventas a credito + reporte (PDF/Excel/CSV) de la lista filtrada.
    Route::get('pagos', [PagoController::class, 'index'])
        ->middleware('permiso:pagos,listar')->name('pagos.index');
    Route::get('pagos/reporte', [PagoController::class, 'reporte'])
        ->middleware('permiso:pagos,reportar')->name('pagos.reporte');
    Route::put('pagos/{pago}', [PagoController::class, 'pagar'])
        ->middleware('permiso:pagos,registrar')->name('pagos.pagar');

    // Perspectiva CLIENTE (autoservicio): historial de compras y plan/historial de pagos del propio
    // usuario. Gestionables por la matriz (modulos mis_compras/mis_pagos); el detalle/pago refuerza
    // con guardia de propiedad (cliente_id = user id).
    Route::prefix('mis-compras')->name('mis-compras.')->group(function () {
        Route::get('/', [MisComprasController::class, 'index'])
            ->middleware('permiso:mis_compras,ver')->name('index');
        Route::get('{venta}', [MisComprasController::class, 'show'])
            ->middleware('permiso:mis_compras,ver')->name('show');
    });
    Route::prefix('mis-pagos')->name('mis-pagos.')->group(function () {
        Route::get('/', [MisPagosController::class, 'index'])
            ->middleware('permiso:mis_pagos,ver')->name('index');
        Route::put('{pago}', [MisPagosController::class, 'pagar'])
            ->middleware('permiso:mis_pagos,pagar')->name('pagar');
    });

    // Bitacora (auditoria): vista de solo-lectura del modulo "bitacora".
    Route::get('bitacora', [BitacoraController::class, 'index'])
        ->middleware('permiso:bitacora,listar')
        ->name('bitacora.index');
    Route::get('bitacora/reporte', [BitacoraController::class, 'reporte'])
        ->middleware('permiso:bitacora,reportar')
        ->name('bitacora.reporte');

    // Matriz de Acceso (solo quien tenga el permiso del modulo "acceso").
    Route::get('acceso/matriz', [AccesoController::class, 'matriz'])
        ->middleware('permiso:acceso,listar')
        ->name('acceso.matriz');

    Route::put('acceso/matriz', [AccesoController::class, 'actualizarMatriz'])
        ->middleware('permiso:acceso,modificar')
        ->name('acceso.matriz.update');
});

require __DIR__.'/settings.php';
