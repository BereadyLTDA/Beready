<?php
declare(strict_types=1);

namespace App\Contracts;

interface FlashcardTagRepositoryInterface
{
    public function findByFlashcardId(int $flashcardId): array;
    public function findByTagId(int $tagId): array;
    public function create(array $data): array;
    public function deleteByFlashcardAndTag(int $flashcardId, int $tagId): bool;
    public function exists(int $flashcardId, int $tagId): bool;
}