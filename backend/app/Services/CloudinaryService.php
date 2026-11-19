<?php

namespace App\Services;

use App\Contracts\CloudinaryServiceInterface;
use Illuminate\Support\Facades\Http;

/**
 * Upload assinado na API do Cloudinary (sem SDK: um POST multipart).
 */
class CloudinaryService implements CloudinaryServiceInterface
{
    public function uploadProfilePhoto(array $file): string
    {
        if (! isset($file['tmp_name']) || ! is_file($file['tmp_name'])) {
            throw new \RuntimeException('Arquivo temporário não encontrado');
        }

        $config = config('beready.cloudinary');
        $params = [
            'timestamp' => time(),
            'folder' => $config['upload_folder'],
        ];

        $response = Http::asMultipart()
            ->attach('file', (string) file_get_contents($file['tmp_name']), $file['name'] ?? 'foto')
            ->post("{$config['api_url']}/{$config['cloud_name']}/image/upload", $params + [
                'api_key' => $config['api_key'],
                'signature' => $this->assinatura($params, (string) $config['api_secret']),
            ]);

        $url = $response->json('secure_url');
        if (! is_string($url)) {
            throw new \RuntimeException('Erro no upload: '.($response->json('error.message') ?? 'Erro desconhecido'));
        }

        return $url;
    }

    public function copiarFotoRemota(string $url): string
    {
        $config = config('beready.cloudinary');
        if (empty($config['cloud_name']) || empty($config['api_key']) || empty($config['api_secret'])) {
            return $url;
        }

        $params = [
            'timestamp' => time(),
            'folder' => $config['upload_folder'],
        ];

        // O Cloudinary aceita uma URL no campo "file" e baixa a imagem do lado dele.
        $response = Http::asForm()->post("{$config['api_url']}/{$config['cloud_name']}/image/upload", $params + [
            'file' => $url,
            'api_key' => $config['api_key'],
            'signature' => $this->assinatura($params, (string) $config['api_secret']),
        ]);

        $nova = $response->json('secure_url');
        if (! is_string($nova)) {
            throw new \RuntimeException('Erro ao copiar a foto: '.($response->json('error.message') ?? 'Erro desconhecido'));
        }

        return $nova;
    }

    /**
     * Assinatura do Cloudinary: parâmetros em ordem alfabética ("a=1&b=2") + o segredo, em SHA-1.
     *
     * @param  array<string, mixed>  $params
     */
    private function assinatura(array $params, string $segredo): string
    {
        ksort($params);

        return sha1(urldecode(http_build_query($params)).$segredo);
    }
}
