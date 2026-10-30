<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** D-12: a trilha ganha o ministério (para o líder ver só o dele) e um texto pronto para a tela. */
    public function up(): void
    {
        Schema::table('auditorias', function (Blueprint $table) {
            $table->foreignId('ministerio_id')->nullable()->after('pessoa_id')->constrained('ministerios')->cascadeOnDelete();
            $table->string('descricao')->nullable()->after('entidade_id');
            $table->index(['igreja_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('auditorias', function (Blueprint $table) {
            $table->dropIndex(['igreja_id', 'created_at']);
            $table->dropConstrainedForeignId('ministerio_id');
            $table->dropColumn('descricao');
        });
    }
};
