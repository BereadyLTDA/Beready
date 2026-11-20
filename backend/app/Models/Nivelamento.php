<?php

namespace App\Models;

use App\Models\Concerns\SerializaDatasComoCake;
use Illuminate\Database\Eloquent\Model;

class Nivelamento extends Model
{
    use SerializaDatasComoCake;

    protected $table = 'nivelamentos';

    public const CREATED_AT = 'iniciado_em';

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['usuario_id', 'nivel_atual'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'respostas' => 'array',
            'acertos_seguidos' => 'integer',
            'erros_seguidos' => 'integer',
            'iniciado_em' => 'datetime',
            'concluido_em' => 'datetime',
        ];
    }
}
