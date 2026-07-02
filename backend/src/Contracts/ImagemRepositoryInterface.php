<?php
declare(strict_types=1);

namespace App\Contracts;

interface ImagemRepositoryInterface
{
public function findByPromptId(int $promptId): array;
public function findById(int $id): ?array;
public function create(array $data): array;
public function update(int $id, array $data): array;
public function delete(int $id): bool;
}