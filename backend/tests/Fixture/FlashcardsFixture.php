<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * FlashcardsFixture
 */
class FlashcardsFixture extends TestFixture
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
                'prompt_id' => 1,
                'texto_frente' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
                'texto_verso' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
                'imagem_frente_id' => 1,
                'imagem_verso_id' => 1,
                'audio_frente_url' => 'Lorem ipsum dolor sit amet',
                'audio_verso_url' => 'Lorem ipsum dolor sit amet',
                'nivel_dificuldade' => 'Lorem ipsum dolor sit amet',
                'tipo_criacao' => 'Lorem ipsum dolor sit amet',
                'vezes_revisado' => 1,
                'vezes_acertado' => 1,
                'ultima_revisao' => 1761682636,
                'proxima_revisao' => 1761682636,
                'arquivado' => 1,
                'criado_em' => 1761682636,
            ],
        ];
        parent::init();
    }
}
