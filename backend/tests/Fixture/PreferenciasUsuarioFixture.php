<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * PreferenciasUsuarioFixture
 */
class PreferenciasUsuarioFixture extends TestFixture
{
    /**
     * Table name
     *
     * @var string
     */
    public string $table = 'preferencias_usuario';
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
                'tema' => 'Lorem ipsum dolor sit amet',
                'modo_daltonico' => 1,
                'notificacoes_ativas' => 1,
                'som_ativo' => 1,
                'traducao_automatica' => 1,
                'preferencia_dificuldade' => 'Lorem ipsum dolor sit amet',
                'meta_diaria_minutos' => 1,
                'criado_em' => 1761682672,
                'atualizado_em' => 1761682672,
            ],
        ];
        parent::init();
    }
}
