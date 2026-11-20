<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LGPD: guarda qual versão dos termos e da política de privacidade o aluno aceitou, e quando.
 * Contas anteriores ficam sem registro (null) até aceitarem a versão atual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('termos_versao', 20)->nullable();
            $table->timestamp('termos_aceitos_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['termos_versao', 'termos_aceitos_em']));
    }
};
