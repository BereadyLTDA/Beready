<?php

namespace App\Models;

use App\Models\Concerns\SerializaDatasComoCake;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Traducao extends Model
{
    use SerializaDatasComoCake;

    protected $table = 'traducoes';

    // Datas preenchidas pelo banco (DEFAULT CURRENT_TIMESTAMP), não pelo ORM
    public $timestamps = false;

    /**
     * Equivalente ao $_accessible da entidade do CakePHP.
     *
     * @var list<string>
     */
    protected $fillable = [
        'prompt_id',
        'texto_traduzido',
        'idioma_destino',
        'pontuacao_confianca',
        'servico_traducao',
        'traducoes_alternativas',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Herança do CakePHP: a lista é gravada como texto JSON dentro do jsonb e a API devolve
            // esse texto (ver TraducoesController::prepararDados)
            'traducoes_alternativas' => 'json',
            // numeric(3,2): a API devolve o decimal como texto ("0.97"), como no CakePHP
            'pontuacao_confianca' => 'decimal:2',
            'criado_em' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Prompt, $this>
     */
    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class, 'prompt_id');
    }
}
