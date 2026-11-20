<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NivelamentoQuestao extends Model
{
    protected $table = 'nivelamento_questoes';

    public $timestamps = false;

    /**
     * A alternativa correta nunca vai para o cliente.
     *
     * @var list<string>
     */
    protected $hidden = ['correta'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'alternativas' => 'array',
            'correta' => 'integer',
        ];
    }
}
