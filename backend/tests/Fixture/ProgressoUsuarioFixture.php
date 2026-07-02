<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * ProgressoUsuarioFixture
 */
class ProgressoUsuarioFixture extends TestFixture
{
    /**
     * Table name
     *
     * @var string
     */
    public string $table = 'progresso_usuario';
    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => 1,
                'usuario_id' => 1,
                'vocabulario_aprendido' => 1,
                'flashcards_concluidos' => 1,
                'quizes_concluidos' => 1,
                'tempo_total_estudo' => 1,
                'sequencia_atual' => 1,
                'maior_sequencia' => 1,
                'ultima_atividade' => 1761682693,
                'progresso_nivel' => '',
                'atualizado_em' => 1761682693,
            ],
        ];
        parent::init();
    }
}
