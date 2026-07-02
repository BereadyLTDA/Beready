<?php
declare(strict_types=1);

namespace App\Models\DTO;

use DateTime;

class FlashcardDTO
{
    public ?int $id = null;
    public int $usuario_id;
    public string $frente;
    public string $verso;
    public string $nivel_dificuldade = 'iniciante';
    public ?DateTime $criado_em = null;
    public ?DateTime $atualizado_em = null;
    
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'usuario_id' => $this->usuario_id,
            'frente' => $this->frente,
            'verso' => $this->verso,
            'nivel_dificuldade' => $this->nivel_dificuldade,
            'criado_em' => $this->criado_em?->format('Y-m-d H:i:s'),
            'atualizado_em' => $this->atualizado_em?->format('Y-m-d H:i:s'),
        ];
    }
    
    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->id = $data['id'] ?? null;
        $dto->usuario_id = $data['usuario_id'] ?? 0;
        $dto->frente = $data['frente'] ?? '';
        $dto->verso = $data['verso'] ?? '';
        $dto->nivel_dificuldade = $data['nivel_dificuldade'] ?? 'iniciante';
        $dto->criado_em = isset($data['criado_em']) ? new DateTime($data['criado_em']) : null;
        $dto->atualizado_em = isset($data['atualizado_em']) ? new DateTime($data['atualizado_em']) : null;
        return $dto;
    }
}