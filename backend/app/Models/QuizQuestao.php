<?php

namespace App\Models;

use App\Models\Concerns\SerializaDatasComoCake;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizQuestao extends Model
{
    use SerializaDatasComoCake;

    protected $table = 'quiz_questoes';

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = 'atualizado_em';

    /**
     * Equivalente ao $_accessible da entidade do CakePHP.
     *
     * @var list<string>
     */
    protected $fillable = [
        'quiz_id',
        'tipo',
        'enunciado',
        'resposta_esperada',
        'explicacao',
        'ordem',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'criado_em' => 'datetime',
            'atualizado_em' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Quiz, $this>
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'quiz_id');
    }

    /**
     * @return HasMany<QuizAlternativa, $this>
     */
    public function alternativas(): HasMany
    {
        return $this->hasMany(QuizAlternativa::class, 'questao_id')->orderBy('ordem')->orderBy('id');
    }
}
