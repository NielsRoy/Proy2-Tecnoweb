<?php

use App\Http\Controllers\AccesoController;
use App\Http\Controllers\BitacoraController;
use App\Http\Controllers\ProductoController;
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
