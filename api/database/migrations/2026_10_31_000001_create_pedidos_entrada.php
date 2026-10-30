<?php

use App\Models\Igreja;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * D-15: o voluntário pede para entrar; o login só nasce quando o líder aprova.
     * A igreja ganha um código curto para o link público de cadastro.
     */
    public function up(): void
    {
        Schema::table('igrejas', function (Blueprint $table) {
            $table->string('codigo', 60)->nullable()->unique()->after('nome');
        });

        // Igrejas que já existem recebem o código a partir do nome.
        Igreja::query()->get()->each(function (Igreja $igreja) {
            $igreja->forceFill(['codigo' => Igreja::codigoPara($igreja->nome)])->save();
        });

        Schema::create('pedidos_entrada', function (Blueprint $table) {
            $table->id();
            $table->foreignId('igreja_id')->constrained('igrejas')->cascadeOnDelete();
            $table->foreignId('ministerio_id')->constrained('ministerios')->cascadeOnDelete();
            $table->string('nome', 150);
            $table->string('email');
            $table->string('telefone', 32)->nullable();
            // Só o hash: a senha escolhida vira o login quando o líder aprova.
            $table->string('senha_hash');
            $table->string('estado', 16)->default('pendente');
            $table->foreignId('resolvido_por')->nullable()->constrained('pessoas')->nullOnDelete();
            $table->timestamp('resolvido_em')->nullable();
            $table->timestamps();

            $table->index(['ministerio_id', 'estado']);
            $table->index(['igreja_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos_entrada');

        Schema::table('igrejas', function (Blueprint $table) {
            $table->dropUnique(['codigo']);
            $table->dropColumn('codigo');
        });
    }
};
