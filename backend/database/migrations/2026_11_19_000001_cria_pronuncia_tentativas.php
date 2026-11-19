<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tentativas do exercício "Repita a frase": a frase esperada, o que o reconhecimento de voz
 * entendeu e a nota calculada pelo servidor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pronuncia_tentativas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->text('frase');
            $table->text('reconhecido');
            $table->unsignedTinyInteger('nota');
            $table->timestamp('criado_em')->useCurrent();
            $table->index(['usuario_id', 'criado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pronuncia_tentativas');
    }
};
