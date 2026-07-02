<?php
declare(strict_types=1);

namespace App\Contracts;

interface PromptRepositoryInterface
{
    public function findAll(): array;
    public function findById(int $id): ?array;
    public function findByUsuarioId(int $usuarioId): array;
    public function create(array $data): array;
    public function update(int $id, array $data): array;
    public function delete(int $id): bool;
}