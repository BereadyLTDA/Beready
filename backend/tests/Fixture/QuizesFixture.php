<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * QuizesFixture
 */
class QuizesFixture extends TestFixture
{
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
                'titulo' => 'Lorem ipsum dolor sit amet',
                'descricao' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
                'tipo_criacao' => 'Lorem ipsum dolor sit amet',
                'nivel_dificuldade' => 'Lorem ipsum dolor sit amet',
                'total_questoes' => 1,
                'tempo_limite' => 1,
                'publico' => 1,
                'criado_em' => 1761682655,
                'atualizado_em' => 1761682655,
            ],
        ];
        parent::init();
    }
}
