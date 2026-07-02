<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * ImagensGeradasFixture
 */
class ImagensGeradasFixture extends TestFixture
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
                'traducao_id' => 1,
                'url_imagem' => 'Lorem ipsum dolor sit amet',
                'prompt_imagem' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
                'servico_geracao' => 'Lorem ipsum dolor sit amet',
                'qualidade_imagem' => 'Lorem ipsum dolor sit amet',
                'tamanho_arquivo' => 1,
                'dimensoes' => 'Lorem ipsum dolor ',
                'criado_em' => 1761682679,
            ],
        ];
        parent::init();
    }
}
