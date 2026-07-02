<?php
declare(strict_types=1);

namespace App\Contracts;

interface CloudinaryServiceInterface
{
    public function uploadProfilePhoto(array $file): string;
}