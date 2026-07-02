<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * VocabularioFixture
 */
class VocabularioFixture extends TestFixture
{
    /**
     * Table name
     *
     * @var string
     */
    public string $table = 'vocabulario';
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
                'palavra_frase' => 'Lorem ipsum dolor sit amet',
                'criado_em' => 1761682705,
            ],
        ];
        parent::init();
    }
}
