<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('visitas_pagina', function (Blueprint $table) {
            $table->id();
            // Identificador de la pagina: nombre de ruta (o path como fallback).
            $table->string('ruta')->unique();
            $table->unsignedBigInteger('visitas')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitas_pagina');
    }
};
