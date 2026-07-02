<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddResetTokenToUsers extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('users');
        $table->addColumn('reset_token', 'string', [
            'limit' => 100,
            'null' => true,
            'default' => null
        ]);
        $table->addColumn('reset_token_expires', 'datetime', [
            'null' => true,
            'default' => null
        ]);
        $table->update();
    }
}
