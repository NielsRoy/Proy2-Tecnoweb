<?php

use App\Http\Controllers\AccesoController;
use App\Http\Controllers\BitacoraController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\PromocionController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// Inicio: pagina de aterrizaje fija para TODO usuario autenticado. NO va en la matriz de acceso
// (solo 'auth', sin 'permiso:') -> no se puede desactivar por rol. Destino tras login/registro,
// del logo y del boton "Ingresar al sistema".
Route::inertia('inicio', 'Inicio')
    ->middleware('auth')
    ->name('inicio');

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
        Route::get('{producto}/editar', [ProductoController::class, 'edit'])
            ->middleware('permiso:productos,modificar')->name('edit');
        Route::put('{producto}', [ProductoController::class, 'update'])
            ->middleware('permiso:productos,modificar')->name('update');
        Route::delete('{producto}', [ProductoController::class, 'destroy'])
            ->middleware('permiso:productos,eliminar')->name('destroy');
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
    });

    // CU6 Promociones (modulo "promociones"): CRUD por producto. Eliminar = baja logica.
    Route::prefix('promociones')->name('promociones.')->group(function () {
        Route::get('/', [PromocionController::class, 'index'])
            ->middleware('permiso:promociones,listar')->name('index');
        Route::get('crear', [PromocionController::class, 'create'])
            ->middleware('permiso:promociones,registrar')->name('create');
        Route::post('/', [PromocionController::class, 'store'])
            ->middleware('permiso:promociones,registrar')->name('store');
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
        Route::get('{venta}', [VentaController::class, 'show'])
            ->middleware('permiso:ventas,listar')->name('show');
        Route::delete('{venta}', [VentaController::class, 'destroy'])
            ->middleware('permiso:ventas,eliminar')->name('destroy');
    });

    // CU7 Pagos (modulo "pagos"): cobro de la proxima cuota pendiente de ventas a credito.
    Route::get('pagos', [PagoController::class, 'index'])
        ->middleware('permiso:pagos,listar')->name('pagos.index');
    Route::put('pagos/{pago}', [PagoController::class, 'pagar'])
        ->middleware('permiso:pagos,registrar')->name('pagos.pagar');

    // Bitacora (auditoria): vista de solo-lectura del modulo "bitacora".
    Route::get('bitacora', [BitacoraController::class, 'index'])
        ->middleware('permiso:bitacora,listar')
        ->name('bitacora.index');

    // Matriz de Acceso (solo quien tenga el permiso del modulo "acceso").
    Route::get('acceso/matriz', [AccesoController::class, 'matriz'])
        ->middleware('permiso:acceso,listar')
        ->name('acceso.matriz');

    Route::put('acceso/matriz', [AccesoController::class, 'actualizarMatriz'])
        ->middleware('permiso:acceso,modificar')
        ->name('acceso.matriz.update');
});

require __DIR__.'/settings.php';
