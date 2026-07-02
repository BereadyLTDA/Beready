<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddUuidToUsersNullable extends BaseMigration
{
    public function up(): void
    {
        // 1. Adiciona coluna permitindo NULL
        $this->table('users')
            ->addColumn('uuid', 'string', ['limit' => 36, 'null' => true])
            ->update();
        
        // 2. Preenche os registros existentes com UUIDs
        $users = $this->fetchAll('SELECT id FROM users WHERE uuid IS NULL');
        foreach ($users as $user) {
            $uuid = \Ramsey\Uuid\Uuid::uuid4()->toString();
            $this->execute("UPDATE users SET uuid = '{$uuid}' WHERE id = {$user['id']}");
        }
        
        // 3. Altera para NOT NULL
        $this->table('users')
            ->changeColumn('uuid', 'string', ['limit' => 36, 'null' => false])
            ->update();
        
        // 4. Adiciona índice único
        $this->table('users')
            ->addIndex(['uuid'], ['unique' => true])
            ->update();
    }
    
    public function down(): void
    {
        $this->table('users')
            ->removeIndex(['uuid'])
            ->removeColumn('uuid')
            ->update();
    }
}