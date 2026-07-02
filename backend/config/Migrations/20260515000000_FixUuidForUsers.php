<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class FixUuidForUsers extends BaseMigration
{
    public function up(): void
    {
        $table = $this->table('users');
        
        // 1. Adiciona coluna permitindo null
        if (!$table->hasColumn('uuid')) {
            $table->addColumn('uuid', 'string', ['limit' => 36, 'null' => true]);
            $table->update();
        }
        
        // 2. Preenche registros existentes
        $rows = $this->fetchAll('SELECT id FROM users WHERE uuid IS NULL');
        foreach ($rows as $row) {
            $uuid = \Ramsey\Uuid\Uuid::uuid4()->toString();
            $this->execute("UPDATE users SET uuid = '{$uuid}' WHERE id = {$row['id']}");
        }
        
        // 3. Altera para NOT NULL
        $table->changeColumn('uuid', 'string', ['limit' => 36, 'null' => false]);
        $table->update();
        
        // 4. Índice único
        $table->addIndex(['uuid'], ['unique' => true]);
        $table->update();
    }
    
    public function down(): void
    {
        $this->table('users')->removeIndex(['uuid'])->removeColumn('uuid')->update();
    }
}