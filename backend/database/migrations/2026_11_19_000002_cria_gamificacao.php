<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gamificação: meta diária de cards, conquistas do aluno e controle do lembrete por e-mail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preferencias_usuario', function (Blueprint $table) {
            $table->unsignedSmallInteger('meta_diaria_cards')->default(10);
        });

        Schema::create('conquistas_usuario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->string('codigo', 40);
            $table->timestamp('conquistada_em')->useCurrent();
            // false até o aluno ver o aviso da conquista
            $table->boolean('vista')->default(false);
            $table->unique(['usuario_id', 'codigo']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('ultimo_lembrete_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('ultimo_lembrete_em'));
        Schema::dropIfExists('conquistas_usuario');
        Schema::table('preferencias_usuario', fn (Blueprint $table) => $table->dropColumn('meta_diaria_cards'));
    }
};
