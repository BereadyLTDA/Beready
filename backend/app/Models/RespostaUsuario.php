<?php

namespace App\Models;

use App\Models\Concerns\SerializaDatasComoCake;
use Illuminate\Database\Eloquent\Model;

class RespostaUsuario extends Model
{
    use SerializaDatasComoCake;

    protected $table = 'respostas_usuario';

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = null;

    /**
     * Equivalente ao $_accessible da entidade do CakePHP.
     *
     * @var list<string>
     */
    protected $fillable = [
        'usuario_id',
        'tipo',
        'referencia_id',
        'correto',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'correto' => 'boolean',
            'criado_em' => 'datetime',
        ];
    }
}
