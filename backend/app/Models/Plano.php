<?php

namespace App\Models;

use App\Models\Concerns\SerializaDatasComoCake;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Plano extends Model
{
    use SerializaDatasComoCake;

    protected $table = 'planos';

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = 'updated_at';

    /**
     * Equivalente ao $_accessible da entidade do CakePHP.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nome',
        'descricao',
        'role_id',
        'preco_mensal',
        'preco_anual',
        'dias_trial',
        'recursos',
        'limites',
        'is_ativo',
        'ordem',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'recursos_list',
        'limites_array',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recursos' => 'array',
            'limites' => 'array',
            'is_ativo' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * @return array<mixed>
     */
    protected function recursosList(): Attribute
    {
        return Attribute::get(fn (): array => $this->recursos ?: []);
    }

    /**
     * @return array<string, int>
     */
    protected function limitesArray(): Attribute
    {
        return Attribute::get(fn (): array => $this->limites ?: []);
    }
}
