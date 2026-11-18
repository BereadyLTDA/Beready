<?php

namespace App\Models;

use App\Models\Concerns\SerializaDatasComoCake;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use SerializaDatasComoCake;

    protected $table = 'roles';

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
        'nivel',
        'is_sistema',
        'is_ativo',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'permissoes_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_sistema' => 'boolean',
            'is_ativo' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Permissao, $this>
     */
    public function permissoes(): BelongsToMany
    {
        // _joinData: mesmo nome do vínculo no JSON da versão em CakePHP
        return $this->belongsToMany(Permissao::class, 'role_permissoes', 'role_id', 'permissao_id')
            ->using(RolePermissao::class)
            ->as('_joinData')
            ->withPivot('created_at');
    }

    /**
     * @return HasMany<Plano, $this>
     */
    public function planos(): HasMany
    {
        return $this->hasMany(Plano::class, 'role_id');
    }

    /**
     * Quantidade de permissões, só quando elas foram carregadas (como no CakePHP).
     */
    protected function permissoesCount(): Attribute
    {
        return Attribute::get(fn (): int => $this->relationLoaded('permissoes') ? $this->permissoes->count() : 0);
    }
}
