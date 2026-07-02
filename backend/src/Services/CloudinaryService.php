<?php
declare(strict_types=1);

namespace App\Services;

use App\Contracts\CloudinaryServiceInterface;

class CloudinaryService implements CloudinaryServiceInterface
{
    private string $cloudName;
    private string $apiKey;
    private string $apiSecret;
    private string $uploadFolder;
    private bool $disableSSL;

    public function __construct()
    {
        $this->cloudName    = getenv('CLOUDINARY_CLOUD_NAME');
        $this->apiKey       = getenv('CLOUDINARY_API_KEY');
        $this->apiSecret    = getenv('CLOUDINARY_API_SECRET');
        $this->uploadFolder = getenv('CLOUDINARY_UPLOAD_FOLDER');
        $this->apiBaseUrl = getenv('CLOUDINARY_API_URL');
        $this->disableSSL   = getenv('CLOUDINARY_DISABLE_SSL');
    }

    public function uploadProfilePhoto(array $file): string
    {
        if (!isset($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            throw new \Exception('Arquivo temporário não encontrado');
        }

        $url = "{$this->apiBaseUrl}/{$this->cloudName}/image/upload";
        
        $curlFile = new \CURLFile($file['tmp_name']);
        
        $postFields = [
            'file' => $curlFile,
            'api_key' => $this->apiKey,
            'timestamp' => time(),
            'folder' => $this->uploadFolder,
        ];

        $signature = $this->generateSignature($postFields);
        $postFields['signature'] = $signature;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        // Desabilita SSL se configurado no .env
        if ($this->disableSSL) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception('Erro no upload: ' . $error);
        }

        $result = json_decode($response, true);
        
        if (!isset($result['secure_url'])) {
            throw new \Exception('Erro no upload: ' . ($result['error']['message'] ?? 'Erro desconhecido'));
        }

        return $result['secure_url'];
    }

    private function generateSignature(array $params): string
    {
        ksort($params);
        $toSign = '';
        foreach ($params as $key => $value) {
            if ($key === 'file' || $key === 'api_key' || $key === 'signature') {
                continue;
            }
            $toSign .= $key . '=' . $value . '&';
        }
        $toSign = rtrim($toSign, '&');
        $toSign .= $this->apiSecret;
        
        return sha1($toSign);
    }
}