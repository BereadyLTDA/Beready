<?php
declare(strict_types=1);

namespace App\Models\DTO;

use DateTime;

class UserDTO
{
    public ?int $id = null;
    public string $nome;
    public string $email;
    public ?string $senha_hash = null;
    public ?string $telefone = null;
    public string $nivel_ingles = 'iniciante';
    public string $idioma_preferido = 'pt-BR';
    public ?string $objetivos_aprendizado = null;
    public string $status = 'ativo';
    public ?DateTime $criado_em = null;
    public ?DateTime $atualizado_em = null;
    public ?DateTime $ultimo_login = null;
    
    public ?string $senha = null;
    public ?string $confirmar_senha = null;
    
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'email' => $this->email,
            'telefone' => $this->telefone,
            'nivel_ingles' => $this->nivel_ingles,
            'idioma_preferido' => $this->idioma_preferido,
            'objetivos_aprendizado' => $this->objetivos_aprendizado,
            'status' => $this->status,
            'criado_em' => $this->criado_em?->format('Y-m-d H:i:s'),
            'atualizado_em' => $this->atualizado_em?->format('Y-m-d H:i:s'),
            'ultimo_login' => $this->ultimo_login?->format('Y-m-d H:i:s'),
        ];
    }
    
    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->id = $data['id'] ?? null;
        $dto->nome = $data['nome'] ?? '';
        $dto->email = $data['email'] ?? '';
        $dto->senha_hash = $data['senha_hash'] ?? null;
        $dto->telefone = $data['telefone'] ?? null;
        $dto->nivel_ingles = $data['nivel_ingles'] ?? 'iniciante';
        $dto->idioma_preferido = $data['idioma_preferido'] ?? 'pt-BR';
        $dto->objetivos_aprendizado = $data['objetivos_aprendizado'] ?? null;
        $dto->status = $data['status'] ?? 'ativo';
        $dto->criado_em = isset($data['criado_em']) ? new DateTime($data['criado_em']) : null;
        $dto->atualizado_em = isset($data['atualizado_em']) ? new DateTime($data['atualizado_em']) : null;
        $dto->ultimo_login = isset($data['ultimo_login']) ? new DateTime($data['ultimo_login']) : null;
        return $dto;
    }
}