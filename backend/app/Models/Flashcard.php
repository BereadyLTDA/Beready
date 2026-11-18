<?php

namespace App\Models;

use App\Models\Concerns\SerializaDatasComoCake;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Flashcard extends Model
{
    use SerializaDatasComoCake;

    protected $table = 'flashcards';

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = 'atualizado_em';

    /**
     * Equivalente ao $_accessible da entidade do CakePHP.
     *
     * @var list<string>
     */
    protected $fillable = [
        'usuario_id',
        'frente',
        'verso',
        'nivel_dificuldade',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'criado_em' => 'datetime',
            'atualizado_em' => 'datetime',
            'proxima_revisao' => 'datetime',
            'ultima_revisao' => 'datetime',
            'repeticoes' => 'integer',
            'intervalo_dias' => 'integer',
            // numeric(4,2) no banco; a API devolve número (como o FlashcardRepository do CakePHP)
            'fator_ease' => 'float',
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
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'flashcard_tags', 'flashcard_id', 'tag_id');
    }
}
