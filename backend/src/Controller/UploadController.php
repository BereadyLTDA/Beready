<?php
declare(strict_types=1);

namespace App\Controller;

use App\Services\CloudinaryService;

class UploadController extends AppController
{
    public function profilePhoto()
    {
        $this->request->allowMethod(['post']);

        try {
            // Verifica o arquivo
            $file = $this->request->getUploadedFile('photo');

            if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
                $this->response = $this->response->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Nenhuma imagem enviada ou erro no upload.'
                ]));
                $this->response = $this->response->withType('json');
                return $this->response;
            }

            // Faz o upload para o Cloudinary
            $service = new CloudinaryService();
            
            $url = $service->uploadProfilePhoto([
                'tmp_name' => $file->getStream()->getMetadata('uri'),
                'name' => $file->getClientFilename(),
                'type' => $file->getClientMediaType(),
                'size' => $file->getSize(),
            ]);


            // Retorna JSON diretamente
            $this->response = $this->response->withStringBody(json_encode([
                'success' => true,
                'url' => $url
            ]));
            $this->response = $this->response->withType('json');
            return $this->response;

        } catch (\Exception $e) {
            
            $this->response = $this->response->withStringBody(json_encode([
                'success' => false,
                'message' => 'Erro: ' . $e->getMessage()
            ]));
            $this->response = $this->response->withType('json');
            return $this->response;
        }
    }
}