<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddUuidToFlashcards extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('flashcards');
        $table->addColumn('uuid', 'string', ['limit' => 36, 'null' => false, 'default' => null])
              ->addIndex(['uuid'], ['unique' => true])
              ->update();
    }
}