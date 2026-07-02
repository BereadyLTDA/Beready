<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddUuidToUsers extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('users');
        $table->addColumn('uuid', 'string', ['limit' => 36, 'null' => true, 'default' => null])
              ->addIndex(['uuid'], ['unique' => true])
              ->update();
        
        // Depois de adicionar, popular com UUIDs para os registros existentes
        $this->execute("UPDATE users SET uuid = gen_random_uuid() WHERE uuid IS NULL");
        
        // Agora alterar para NOT NULL
        $table->changeColumn('uuid', 'string', ['limit' => 36, 'null' => false]);
    }
}