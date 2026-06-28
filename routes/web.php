<?php

use App\Http\Controllers\AccesoController;
use App\Http\Controllers\BitacoraController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// "Ingresar al sistema": aplica la regla de inicio (User::urlInicio()) en un solo lugar.
Route::get('inicio', fn (Request $request) => redirect($request->user()->urlInicio()))
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
