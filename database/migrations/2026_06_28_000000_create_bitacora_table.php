<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitacora (auditoria): log append-only de operaciones del sistema relacionadas con la
 * administracion del negocio — inicios de sesion (ok/fallidos), cierre de sesion y
 * operaciones de negocio (crear/modificar/eliminar). NO confundir con `visitas_pagina`
 * (contador agregado por ruta): aqui es UNA FILA POR EVENTO, con quien/cuando/ip/descripcion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacora', function (Blueprint $table) {
            $table->id();
            // Quien realizo la operacion. Opcional: un login fallido puede no tener usuario.
            // nullOnDelete: si se borra el usuario, sus registros de bitacora se conservan.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accion');             // login, login_fallido, logout, crear, modificar, eliminar
            $table->string('modulo')->nullable(); // area: auth, usuarios, acceso, productos, ...
            $table->string('descripcion');        // texto legible en espanol
            $table->string('ip', 45)->nullable(); // 45 = cabe una IPv6
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable(); // append-only: solo created_at (cuando ocurrio)

            $table->index('accion');
            $table->index('modulo');
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora');
    }
};
