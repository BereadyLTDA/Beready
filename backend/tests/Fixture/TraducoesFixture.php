<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * TraducoesFixture
 */
class TraducoesFixture extends TestFixture
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
                'texto_traduzido' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
                'idioma_destino' => 'Lorem ip',
                'pontuacao_confianca' => 1.5,
                'servico_traducao' => 'Lorem ipsum dolor sit amet',
                'traducoes_alternativas' => '',
                'criado_em' => 1761682645,
            ],
        ];
        parent::init();
    }
}
