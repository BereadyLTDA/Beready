<?php

namespace App\Models;

use App\Models\Concerns\SerializaDatasComoCake;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permissao extends Model
{
    use SerializaDatasComoCake;

    protected $table = 'permissoes';

    // Mantém `_joinData` (o vínculo com a role) sem virar `_join_data` no JSON
    public static $snakeAttributes = false;

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = null;

    /**
     * Equivalente ao $_accessible da entidade do CakePHP.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nome',
        'descricao',
        'recurso',
        'acao',
        'is_ativo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_ativo' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissoes', 'permissao_id', 'role_id');
    }
}
