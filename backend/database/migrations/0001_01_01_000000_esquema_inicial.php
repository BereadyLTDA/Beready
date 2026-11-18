<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Esquema completo herdado da versão em CakePHP (database/schema/esquema-inicial.sql).
 *
 * Em um banco que já veio do CakePHP as tabelas existem: a migration não faz nada e só fica
 * registrada como aplicada, então dá para trocar o backend sem mexer nos dados.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            return;
        }

        DB::unprepared((string) file_get_contents(database_path('schema/esquema-inicial.sql')));
    }

    public function down(): void
    {
        // Sem volta: apagar o esquema inteiro não deve ser um rollback acidental
    }
};
