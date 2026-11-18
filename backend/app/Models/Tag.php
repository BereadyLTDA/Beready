<?php

namespace App\Models;

use App\Models\Concerns\SerializaDatasComoCake;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use SerializaDatasComoCake;

    protected $table = 'tags';

    // Datas preenchidas pelo banco (DEFAULT CURRENT_TIMESTAMP), não pelo ORM
    public $timestamps = false;

    /**
     * Equivalente ao $_accessible da entidade do CakePHP.
     *
     * @var list<string>
     */
    protected $fillable = [
        'criado_por',
        'nome',
        'cor',
        'descricao',
        'tag_sistema',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tag_sistema' => 'boolean',
            'criado_em' => 'datetime',
        ];
    }
}
