<?php

namespace App\Models;

use App\Models\Concerns\SerializaDatasComoCake;
use Illuminate\Database\Eloquent\Model;

class PreferenciaUsuario extends Model
{
    use SerializaDatasComoCake;

    protected $table = 'preferencias_usuario';

    // Datas preenchidas pelo banco (DEFAULT CURRENT_TIMESTAMP), não pelo ORM
    public $timestamps = false;

    /**
     * Equivalente ao $_accessible da entidade do CakePHP.
     *
     * @var list<string>
     */
    protected $fillable = [
        'usuario_id',
        'tema',
        'modo_daltonico',
        'notificacoes_ativas',
        'som_ativo',
        'traducao_automatica',
        'preferencia_dificuldade',
        'meta_diaria_minutos',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'modo_daltonico' => 'boolean',
            'notificacoes_ativas' => 'boolean',
            'som_ativo' => 'boolean',
            'traducao_automatica' => 'boolean',
            'criado_em' => 'datetime',
            'atualizado_em' => 'datetime',
        ];
    }
}
