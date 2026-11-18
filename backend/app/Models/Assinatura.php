<?php

namespace App\Models;

use App\Models\Concerns\SerializaDatasComoCake;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assinatura extends Model
{
    use SerializaDatasComoCake;

    protected $table = 'assinaturas';

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = 'updated_at';

    /**
     * Equivalente ao $_accessible da entidade do CakePHP.
     *
     * @var list<string>
     */
    protected $fillable = [
        'usuario_id',
        'plano_id',
        'status',
        'data_inicio',
        'data_fim',
        'data_cancelamento',
        'payment_id',
        'payment_gateway',
        'is_ativo',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'stripe_subscription_id',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'recorrente',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data_inicio' => 'datetime',
            'data_fim' => 'datetime',
            'data_cancelamento' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'is_ativo' => 'boolean',
            'cancelar_no_fim_periodo' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Plano, $this>
     */
    public function plano(): BelongsTo
    {
        return $this->belongsTo(Plano::class, 'plano_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Assinatura renovada automaticamente pelo Stripe (o id da subscription fica oculto).
     */
    protected function recorrente(): Attribute
    {
        return Attribute::get(fn (): bool => ! empty($this->stripe_subscription_id));
    }
}
