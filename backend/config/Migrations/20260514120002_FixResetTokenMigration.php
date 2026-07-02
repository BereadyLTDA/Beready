<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class FixResetTokenMigration extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('users');
        if (!$table->hasColumn('reset_token')) {
            $table->addColumn('reset_token', 'string', ['limit' => 100, 'null' => true]);
        }
        if (!$table->hasColumn('reset_token_expires')) {
            $table->addColumn('reset_token_expires', 'datetime', ['null' => true]);
        }
        $table->update();
    }
}