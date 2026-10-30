<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RN-16: uma escala por ministério e mês. RN-17/RN-19..22: estados e prazo.
        // `versao` é o controle de concorrência (D-14).
        Schema::create('escalas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('igreja_id')->constrained('igrejas')->cascadeOnDelete();
            $table->foreignId('ministerio_id')->constrained('ministerios')->cascadeOnDelete();
            $table->string('mes', 7); // AAAA-MM
            $table->string('status', 16)->default('rascunho'); // rascunho | aberta | publicada
            $table->date('prazo')->nullable();
            $table->unsignedInteger('versao')->default(1);
            $table->timestamps();

            $table->unique(['ministerio_id', 'mes']);
        });

        // RN-24: turno. Excluir posto apaga os turnos (RN-13); banda e culto só se soltam.
        Schema::create('turnos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('igreja_id')->constrained('igrejas')->cascadeOnDelete();
            $table->foreignId('escala_id')->constrained('escalas')->cascadeOnDelete();
            $table->foreignId('posto_id')->constrained('postos')->cascadeOnDelete();
            $table->foreignId('banda_id')->nullable()->constrained('bandas')->nullOnDelete();
            $table->foreignId('culto_id')->nullable()->constrained('cultos')->nullOnDelete();
            $table->string('culto_nome')->nullable(); // RN-06: o nome sobrevive à exclusão do culto
            $table->date('data');
            $table->time('inicio');
            $table->time('fim');
            $table->unsignedSmallInteger('vagas')->default(1);
            $table->unsignedInteger('versao')->default(1);
            $table->timestamps();

            $table->index(['escala_id', 'data']);
        });

        // RN-34..38: o voluntário marca em que turnos pode servir.
        Schema::create('disponibilidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turno_id')->constrained('turnos')->cascadeOnDelete();
            $table->foreignId('pessoa_id')->constrained('pessoas')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['turno_id', 'pessoa_id']);
        });

        // RN-39..43: o líder confirma quem fica em cada vaga.
        Schema::create('confirmacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turno_id')->constrained('turnos')->cascadeOnDelete();
            $table->foreignId('pessoa_id')->constrained('pessoas')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['turno_id', 'pessoa_id']);
        });

        // Funções da pessoa naquele turno (RN-43). Excluir a função a remove daqui (RN-14).
        Schema::create('confirmacao_funcao', function (Blueprint $table) {
            $table->foreignId('confirmacao_id')->constrained('confirmacoes')->cascadeOnDelete();
            $table->foreignId('funcao_id')->constrained('funcoes')->cascadeOnDelete();
            $table->primary(['confirmacao_id', 'funcao_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('confirmacao_funcao');
        Schema::dropIfExists('confirmacoes');
        Schema::dropIfExists('disponibilidades');
        Schema::dropIfExists('turnos');
        Schema::dropIfExists('escalas');
    }
};
