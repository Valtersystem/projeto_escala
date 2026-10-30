<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ministerios', function (Blueprint $table) {
            $table->json('modulos')->default('[]');
        });

        // Quem já tinha bandas continua com o módulo ligado.
        DB::table('ministerios')
            ->whereIn('id', DB::table('bandas')->select('ministerio_id'))
            ->update(['modulos' => json_encode(['bandas'])]);
    }

    public function down(): void
    {
        Schema::table('ministerios', function (Blueprint $table) {
            $table->dropColumn('modulos');
        });
    }
};
