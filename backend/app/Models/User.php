<?php

namespace App\Models;

use App\Models\Concerns\SerializaDatasComoCake;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class User extends Model
{
    use SerializaDatasComoCake;

    protected $table = 'users';

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = 'atualizado_em';

    /**
     * Equivalente ao $_accessible da entidade do CakePHP.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nome',
        'email',
        'telefone',
        'nivel_ingles',
        'idioma_preferido',
        'objetivos_aprendizado',
        'foto_perfil',
        'ultimo_login',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'senha_hash',
        'token',
        'token_expires',
        'reset_token',
        'reset_token_expires',
        'stripe_customer_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'criado_em' => 'datetime',
            'atualizado_em' => 'datetime',
            'ultimo_login' => 'datetime',
            'token_expires' => 'datetime',
            'reset_token_expires' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Assinatura, $this>
     */
    public function assinaturas(): HasMany
    {
        return $this->hasMany(Assinatura::class, 'usuario_id');
    }

    /**
     * @return HasMany<Flashcard, $this>
     */
    public function flashcards(): HasMany
    {
        return $this->hasMany(Flashcard::class, 'usuario_id');
    }

    /**
     * @return HasMany<Quiz, $this>
     */
    public function quizes(): HasMany
    {
        return $this->hasMany(Quiz::class, 'usuario_id');
    }

    /**
     * @return HasMany<Prompt, $this>
     */
    public function prompts(): HasMany
    {
        return $this->hasMany(Prompt::class, 'usuario_id');
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'usuario_roles', 'usuario_id', 'role_id');
    }

    /**
     * UUID público do usuário, gerado na criação (como o beforeSave do UsersTable no CakePHP).
     */
    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            $user->uuid ??= (string) Str::uuid();
        });
    }
}
