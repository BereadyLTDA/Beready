<?php
declare(strict_types=1);

namespace App\Models\DTO;

use DateTime;

class QuizDTO
{
    public ?int $id = null;
    public int $usuario_id;
    public string $titulo;
    public ?string $descricao = null;
    public string $tipo_criacao = 'manual';
    public string $nivel_dificuldade = 'iniciante';
    public int $total_questoes = 0;
    public ?int $tempo_limite = null;
    public bool $publico = false;
    public ?DateTime $criado_em = null;
    public ?DateTime $atualizado_em = null;
    
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'usuario_id' => $this->usuario_id,
            'titulo' => $this->titulo,
            'descricao' => $this->descricao,
            'tipo_criacao' => $this->tipo_criacao,
            'nivel_dificuldade' => $this->nivel_dificuldade,
            'total_questoes' => $this->total_questoes,
            'tempo_limite' => $this->tempo_limite,
            'publico' => $this->publico,
            'criado_em' => $this->criado_em?->format('Y-m-d H:i:s'),
            'atualizado_em' => $this->atualizado_em?->format('Y-m-d H:i:s'),
        ];
    }
    
    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->id = $data['id'] ?? null;
        $dto->usuario_id = $data['usuario_id'] ?? 0;
        $dto->titulo = $data['titulo'] ?? '';
        $dto->descricao = $data['descricao'] ?? null;
        $dto->tipo_criacao = $data['tipo_criacao'] ?? 'manual';
        $dto->nivel_dificuldade = $data['nivel_dificuldade'] ?? 'iniciante';
        $dto->total_questoes = (int)($data['total_questoes'] ?? 0);
        $dto->tempo_limite = isset($data['tempo_limite']) ? (int)$data['tempo_limite'] : null;
        $dto->publico = (bool)($data['publico'] ?? false);
        $dto->criado_em = isset($data['criado_em']) ? new DateTime($data['criado_em']) : null;
        $dto->atualizado_em = isset($data['atualizado_em']) ? new DateTime($data['atualizado_em']) : null;
        return $dto;
    }
}