<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ministerios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('igreja_id')->constrained('igrejas')->cascadeOnDelete();
            $table->string('nome');
            // RN-07: no máximo um líder; pode ficar vazio.
            $table->foreignId('lider_id')->nullable()->constrained('pessoas')->nullOnDelete();
            $table->text('orientacoes')->nullable();
            $table->timestamps();

            $table->unique(['igreja_id', 'nome']);
        });

        // Equipe do ministério (RN-11).
        Schema::create('ministerio_pessoa', function (Blueprint $table) {
            $table->foreignId('ministerio_id')->constrained('ministerios')->cascadeOnDelete();
            $table->foreignId('pessoa_id')->constrained('pessoas')->cascadeOnDelete();
            $table->primary(['ministerio_id', 'pessoa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ministerio_pessoa');
        Schema::dropIfExists('ministerios');
    }
};
