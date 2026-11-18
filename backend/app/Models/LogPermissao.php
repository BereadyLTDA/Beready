<?php

namespace App\Models;

use App\Models\Concerns\SerializaDatasComoCake;
use Illuminate\Database\Eloquent\Model;

class LogPermissao extends Model
{
    use SerializaDatasComoCake;

    protected $table = 'logs_permissoes';

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = null;

    /**
     * Equivalente ao $_accessible da entidade do CakePHP.
     *
     * @var list<string>
     */
    protected $fillable = [
        'usuario_id',
        'acao',
        'entidade',
        'entidade_id',
        'dados_anteriores',
        'dados_novos',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dados_anteriores' => 'array',
            'dados_novos' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
