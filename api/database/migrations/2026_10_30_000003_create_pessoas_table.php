<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RN-09: pessoa pode existir antes de ter login (o líder cadastra), então
     * user_id é opcional. O vínculo com o usuário é feito pelo e-mail (D-15).
     */
    public function up(): void
    {
        Schema::create('pessoas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('igreja_id')->constrained('igrejas')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('nome');
            $table->string('email')->nullable();
            $table->string('telefone', 32)->nullable();
            $table->boolean('adm')->default(false);
            // RN-10: pessoa inativa não é chamada nem aparece como candidata.
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['igreja_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pessoas');
    }
};
