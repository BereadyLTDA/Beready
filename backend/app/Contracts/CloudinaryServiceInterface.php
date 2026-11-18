<?php

namespace App\Contracts;

interface CloudinaryServiceInterface
{
    /**
     * Envia a foto e devolve a URL pública (https).
     *
     * @param  array{tmp_name: string, name?: string|null, type?: string|null, size?: int|null}  $file
     *
     * @throws \RuntimeException se o upload falhar
     */
    public function uploadProfilePhoto(array $file): string;
}
