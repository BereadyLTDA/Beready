<?php

namespace App\Models;

use App\Models\Concerns\SerializaDatasComoCake;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImagemGerada extends Model
{
    use SerializaDatasComoCake;

    protected $table = 'imagens_geradas';

    // Datas preenchidas pelo banco (DEFAULT CURRENT_TIMESTAMP), não pelo ORM
    public $timestamps = false;

    /**
     * Equivalente ao $_accessible da entidade do CakePHP.
     *
     * @var list<string>
     */
    protected $fillable = [
        'prompt_id',
        'traducao_id',
        'url_imagem',
        'prompt_imagem',
        'servico_geracao',
        'qualidade_imagem',
        'tamanho_arquivo',
        'dimensoes',
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
     * @return BelongsTo<Prompt, $this>
     */
    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class, 'prompt_id');
    }
}
