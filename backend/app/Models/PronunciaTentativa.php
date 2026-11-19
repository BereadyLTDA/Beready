<?php

namespace App\Models;

use App\Models\Concerns\SerializaDatasComoCake;
use Illuminate\Database\Eloquent\Model;

class PronunciaTentativa extends Model
{
    use SerializaDatasComoCake;

    protected $table = 'pronuncia_tentativas';

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'usuario_id',
        'frase',
        'reconhecido',
        'nota',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nota' => 'integer',
            'criado_em' => 'datetime',
        ];
    }
}
