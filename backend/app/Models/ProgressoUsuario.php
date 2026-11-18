<?php

namespace App\Models;

use App\Models\Concerns\SerializaDatasComoCake;
use Illuminate\Database\Eloquent\Model;

class ProgressoUsuario extends Model
{
    use SerializaDatasComoCake;

    protected $table = 'progresso_usuario';

    // Datas preenchidas pelo banco (DEFAULT CURRENT_TIMESTAMP), não pelo ORM
    public $timestamps = false;

    /**
     * Equivalente ao $_accessible da entidade do CakePHP.
     *
     * @var list<string>
     */
    protected $fillable = [
        'usuario_id',
        'vocabulario_aprendido',
        'flashcards_concluidos',
        'quizes_concluidos',
        'tempo_total_estudo',
        'sequencia_atual',
        'maior_sequencia',
        'ultima_atividade',
        'progresso_nivel',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ultima_atividade' => 'datetime',
            'atualizado_em' => 'datetime',
            'progresso_nivel' => 'array',
        ];
    }
}
