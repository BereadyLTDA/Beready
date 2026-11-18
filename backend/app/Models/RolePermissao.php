<?php

namespace App\Models;

use App\Models\Concerns\SerializaDatasComoCake;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Vínculo role <-> permissão (tabela role_permissoes), exposto no JSON como `_joinData`.
 */
class RolePermissao extends Pivot
{
    use SerializaDatasComoCake;

    protected $table = 'role_permissoes';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
