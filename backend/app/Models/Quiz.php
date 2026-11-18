<?php

namespace App\Models;

use App\Models\Concerns\SerializaDatasComoCake;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    use SerializaDatasComoCake;

    protected $table = 'quizes';

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = 'atualizado_em';

    /**
     * Equivalente ao $_accessible da entidade do CakePHP.
     *
     * @var list<string>
     */
    protected $fillable = [
        'usuario_id',
        'titulo',
        'descricao',
        'tipo_criacao',
        'nivel_dificuldade',
        'total_questoes',
        'tempo_limite',
        'publico',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'publico' => 'boolean',
            'criado_em' => 'datetime',
            'atualizado_em' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * @return HasMany<QuizQuestao, $this>
     */
    public function questoes(): HasMany
    {
        return $this->hasMany(QuizQuestao::class, 'quiz_id')->orderBy('ordem')->orderBy('id');
    }
}
