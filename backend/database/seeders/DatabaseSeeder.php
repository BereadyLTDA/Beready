<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Roles, permissões e planos iniciais. Só roda em banco vazio: em um banco que já tem esses
     * dados (inclusive um vindo do CakePHP) não faz nada.
     */
    public function run(): void
    {
        if (DB::table('roles')->exists()) {
            return;
        }

        DB::unprepared((string) file_get_contents(database_path('seeders/sql/dados-iniciais.sql')));
    }
}
