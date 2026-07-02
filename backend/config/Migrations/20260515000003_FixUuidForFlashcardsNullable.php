<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class FixUuidForFlashcardsNullable extends BaseMigration
{
    public function up(): void
    {
        $table = $this->table('flashcards');
        
        // 1. Adiciona coluna como NULL primeiro
        if (!$table->hasColumn('uuid')) {
            $table->addColumn('uuid', 'string', ['limit' => 36, 'null' => true]);
            $table->update();
        }
        
        // 2. Preenche os registros existentes
        $rows = $this->fetchAll('SELECT id FROM flashcards WHERE uuid IS NULL');
        foreach ($rows as $row) {
            $uuid = \Ramsey\Uuid\Uuid::uuid4()->toString();
            $this->execute("UPDATE flashcards SET uuid = '{$uuid}' WHERE id = {$row['id']}");
        }
        
        // 3. Agora altera para NOT NULL
        $table->changeColumn('uuid', 'string', ['limit' => 36, 'null' => false]);
        $table->update();
        
        // 4. Adiciona índice único
        $table->addIndex(['uuid'], ['unique' => true]);
        $table->update();
    }
    
    public function down(): void
    {
        $this->table('flashcards')->removeIndex(['uuid'])->removeColumn('uuid')->update();
    }
}