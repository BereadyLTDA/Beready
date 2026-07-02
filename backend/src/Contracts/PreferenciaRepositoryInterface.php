<?php
declare(strict_types=1);

namespace App\Contracts;

interface PreferenciaRepositoryInterface
{
    public function findByUsuarioId(int $usuarioId): ?array;
    public function create(array $data): array;
    public function update(int $usuarioId, array $data): array;
    public function createOrUpdate(int $usuarioId, array $data): array;
}