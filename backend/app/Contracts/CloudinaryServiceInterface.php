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

    /**
     * Copia para o Cloudinary uma foto que está em outra URL (ex.: foto do login social, cujo
     * link pode expirar) e devolve a URL nova. Sem o Cloudinary configurado, devolve a original.
     *
     * @throws \RuntimeException se o upload falhar
     */
    public function copiarFotoRemota(string $url): string;
}
