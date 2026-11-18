<?php

namespace App\Models;

use App\Models\Concerns\SerializaDatasComoCake;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FraseSemelhante extends Model
{
    use SerializaDatasComoCake;

    protected $table = 'frases_semelhantes';

    // Datas preenchidas pelo banco (DEFAULT CURRENT_TIMESTAMP), não pelo ORM
    public $timestamps = false;

    /**
     * Equivalente ao $_accessible da entidade do CakePHP.
     *
     * @var list<string>
     */
    protected $fillable = [
        'prompt_id',
        'frase_semelhante',
        'pontuacao_semelhante',
        'tipo_frase',
        'nivel_dificuldade',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'criado_em' => 'datetime',
            // numeric(3,2): a API devolve o decimal como texto ("0.80"), como no CakePHP
            'pontuacao_semelhante' => 'decimal:2',
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
