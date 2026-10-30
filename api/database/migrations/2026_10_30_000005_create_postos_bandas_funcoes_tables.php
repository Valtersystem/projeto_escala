<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RN-13: posto com vagas padrão (mínimo 1).
        Schema::create('postos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('igreja_id')->constrained('igrejas')->cascadeOnDelete();
            $table->foreignId('ministerio_id')->constrained('ministerios')->cascadeOnDelete();
            $table->string('nome');
            $table->string('descricao')->nullable();
            $table->unsignedSmallInteger('vagas')->default(1);
            $table->timestamps();
        });

        Schema::create('posto_pessoa', function (Blueprint $table) {
            $table->foreignId('posto_id')->constrained('postos')->cascadeOnDelete();
            $table->foreignId('pessoa_id')->constrained('pessoas')->cascadeOnDelete();
            $table->primary(['posto_id', 'pessoa_id']);
        });

        // RN-15 e RN-29: `ordem` define a sequência do rodízio.
        Schema::create('bandas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('igreja_id')->constrained('igrejas')->cascadeOnDelete();
            $table->foreignId('ministerio_id')->constrained('ministerios')->cascadeOnDelete();
            $table->string('nome');
            $table->unsignedSmallInteger('ordem')->default(1);
            $table->timestamps();
        });

        Schema::create('banda_pessoa', function (Blueprint $table) {
            $table->foreignId('banda_id')->constrained('bandas')->cascadeOnDelete();
            $table->foreignId('pessoa_id')->constrained('pessoas')->cascadeOnDelete();
            $table->primary(['banda_id', 'pessoa_id']);
        });

        // RN-14: função pertence ao ministério.
        Schema::create('funcoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('igreja_id')->constrained('igrejas')->cascadeOnDelete();
            $table->foreignId('ministerio_id')->constrained('ministerios')->cascadeOnDelete();
            $table->string('nome');
            $table->timestamps();
        });

        // Funções que a pessoa já faz.
        Schema::create('funcao_pessoa', function (Blueprint $table) {
            $table->foreignId('funcao_id')->constrained('funcoes')->cascadeOnDelete();
            $table->foreignId('pessoa_id')->constrained('pessoas')->cascadeOnDelete();
            $table->primary(['funcao_id', 'pessoa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funcao_pessoa');
        Schema::dropIfExists('funcoes');
        Schema::dropIfExists('banda_pessoa');
        Schema::dropIfExists('bandas');
        Schema::dropIfExists('posto_pessoa');
        Schema::dropIfExists('postos');
    }
};
