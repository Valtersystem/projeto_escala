<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Igreja é o tenant (D-11, D-17). Cada filial é uma igreja individual.
     */
    public function up(): void
    {
        Schema::create('igrejas', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('fuso', 64)->default('America/Sao_Paulo');
            // RN-44: máximo de turnos confirmados por pessoa no mês (só alerta).
            $table->unsignedSmallInteger('limite_turnos_mes')->default(3);
            // RN-17: dias antes do início do mês para sugerir o prazo da escala.
            $table->unsignedSmallInteger('prazo_padrao_dias')->default(5);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('igrejas');
    }
};
