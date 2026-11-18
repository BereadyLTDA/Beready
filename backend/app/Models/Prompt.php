<?php

namespace App\Models;

use App\Models\Concerns\SerializaDatasComoCake;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prompt extends Model
{
    use SerializaDatasComoCake;

    protected $table = 'prompts';

    // Datas preenchidas pelo banco (DEFAULT CURRENT_TIMESTAMP), não pelo ORM
    public $timestamps = false;

    /**
     * Equivalente ao $_accessible da entidade do CakePHP.
     *
     * @var list<string>
     */
    protected $fillable = [
        'usuario_id',
        'texto_original',
        'idioma_original',
        'contexto',
        'midia_origem_id',
        'sessao_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'criado_em' => 'datetime',
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
     * @return HasMany<Traducao, $this>
     */
    public function traducoes(): HasMany
    {
        return $this->hasMany(Traducao::class, 'prompt_id');
    }

    /**
     * @return HasMany<ImagemGerada, $this>
     */
    public function imagens(): HasMany
    {
        return $this->hasMany(ImagemGerada::class, 'prompt_id');
    }

    /**
     * @return HasMany<FraseSemelhante, $this>
     */
    public function frases(): HasMany
    {
        return $this->hasMany(FraseSemelhante::class, 'prompt_id');
    }
}
