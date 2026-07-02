<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * FrasesSemelhantesFixture
 */
class FrasesSemelhantesFixture extends TestFixture
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
                'prompt_id' => 1,
                'frase_semelhante' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
                'pontuacao_semelhante' => 1.5,
                'tipo_frase' => 'Lorem ipsum dolor sit amet',
                'nivel_dificuldade' => 'Lorem ipsum dolor sit amet',
                'criado_em' => 1761682685,
            ],
        ];
        parent::init();
    }
}
