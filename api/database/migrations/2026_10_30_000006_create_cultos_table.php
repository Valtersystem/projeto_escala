<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RN-03: grade de cultos da igreja. `ordem` vale só para o tipo mensal
     * (1 a 5, ou -1 para a última ocorrência do dia no mês).
     */
    public function up(): void
    {
        Schema::create('cultos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('igreja_id')->constrained('igrejas')->cascadeOnDelete();
            $table->string('nome');
            $table->string('tipo', 16)->default('semanal'); // semanal | mensal
            $table->unsignedTinyInteger('dia_semana');      // 0 = domingo ... 6 = sábado
            $table->smallInteger('ordem')->default(1);
            $table->time('inicio');
            $table->time('fim');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cultos');
    }
};
