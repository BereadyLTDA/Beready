<?php
declare(strict_types=1);

namespace App\Contracts;

interface UserUseCaseInterface
{
    public function register(array $data): array;
    public function login(string $email, string $password): array;
    public function getUserById(int $id): array;
    public function getUserByUuid(string $uuid): array;
    public function updateUser(int $id, array $data): array;
    public function deleteUser(int $id): bool;
    public function forgotPassword(string $email): void;
    public function resetPassword(string $token, string $newPassword): void;
}