<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RN-51..58. A regra "um pedido aberto por pessoa e turno" (RN-52) é
        // validada na aplicação, porque o banco não tem índice único parcial portátil.
        Schema::create('trocas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('igreja_id')->constrained('igrejas')->cascadeOnDelete();
            $table->foreignId('turno_id')->constrained('turnos')->cascadeOnDelete();
            $table->foreignId('pessoa_id')->constrained('pessoas')->cascadeOnDelete();
            $table->string('motivo');
            $table->text('observacao')->nullable();
            $table->string('estado', 16)->default('aberto');     // aberto | resolvido | cancelado
            $table->string('resolucao', 16)->nullable();          // mantido | substituido | vaga_aberta
            $table->foreignId('substituto_id')->nullable()->constrained('pessoas')->nullOnDelete();
            $table->timestamps();

            $table->index(['turno_id', 'pessoa_id', 'estado']);
        });

        // RN-59/60: avisos internos.
        Schema::create('avisos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('igreja_id')->constrained('igrejas')->cascadeOnDelete();
            $table->foreignId('pessoa_id')->constrained('pessoas')->cascadeOnDelete();
            $table->text('texto');
            $table->timestamp('lido_em')->nullable();
            $table->timestamps();

            $table->index(['pessoa_id', 'lido_em']);
        });

        // D-12: trilha de quem mudou o quê. A gravação entra numa etapa seguinte.
        Schema::create('auditorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('igreja_id')->constrained('igrejas')->cascadeOnDelete();
            $table->foreignId('pessoa_id')->nullable()->constrained('pessoas')->nullOnDelete();
            $table->string('acao', 64);
            $table->string('entidade', 64);
            $table->unsignedBigInteger('entidade_id')->nullable();
            $table->json('antes')->nullable();
            $table->json('depois')->nullable();
            $table->timestamps();

            $table->index(['entidade', 'entidade_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias');
        Schema::dropIfExists('avisos');
        Schema::dropIfExists('trocas');
    }
};
