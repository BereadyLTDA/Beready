<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateRespostasUsuario extends BaseMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/4/en/migrations.html#the-change-method
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('respostas_usuario');
        $table
            ->addColumn('usuario_id', 'integer', [
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('tipo', 'string', [
                'limit' => 20,
                'null' => false,
            ])
            ->addColumn('referencia_id', 'integer', [
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('correto', 'boolean', [
                'null' => false,
                'default' => false,
            ])
            ->addColumn('criado_em', 'datetime', [
                'null' => false,
            ])
            ->addIndex(['usuario_id'])
            ->addIndex(['tipo'])
            ->addForeignKey('usuario_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->create();
    }
}