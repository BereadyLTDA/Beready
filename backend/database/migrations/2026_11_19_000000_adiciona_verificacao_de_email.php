<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Verificação de e-mail no cadastro. Contas que já existiam são marcadas como verificadas, para
 * ninguém perder o acesso com a mudança.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('email_verificado_em')->nullable();
            // Só o hash SHA-256 do token vai para o banco, como no reset de senha
            $table->string('verificacao_token', 64)->nullable()->index();
            $table->timestamp('verificacao_token_expira')->nullable();
        });

        DB::table('users')->update(['email_verificado_em' => DB::raw('COALESCE(criado_em, NOW())')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_verificado_em', 'verificacao_token', 'verificacao_token_expira']);
        });
    }
};
