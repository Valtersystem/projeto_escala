<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convite para o primeiro acesso (ou para trocar a senha, já que a v1 não
     * recupera senha por e-mail). Só o hash do token é guardado.
     */
    public function up(): void
    {
        Schema::create('convites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pessoa_id')->constrained('pessoas')->cascadeOnDelete();
            $table->foreignId('criado_por')->nullable()->constrained('pessoas')->nullOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expira_em');
            $table->timestamp('usado_em')->nullable();
            $table->timestamps();

            $table->index(['pessoa_id', 'usado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convites');
    }
};
