<?php

namespace App\Models;

use App\Models\Concerns\SerializaDatasComoCake;
use Illuminate\Database\Eloquent\Model;

class ConquistaUsuario extends Model
{
    use SerializaDatasComoCake;

    protected $table = 'conquistas_usuario';

    public const CREATED_AT = 'conquistada_em';

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['usuario_id', 'codigo', 'vista'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vista' => 'boolean',
            'conquistada_em' => 'datetime',
        ];
    }
}
