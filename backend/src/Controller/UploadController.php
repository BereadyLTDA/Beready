<?php

declare(strict_types=1);

namespace App\Controller;

use App\Contracts\CloudinaryServiceInterface;
use App\Services\PermissionService;
use Cake\Http\ServerRequest;
use Cake\Log\Log;

/**
 * Exige access token (JwtAuthMiddleware).
 */
class UploadController extends AppController
{
    private const MAX_SIZE = 5 * 1024 * 1024;

    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        ServerRequest $request,
        PermissionService $permissionService,
        private CloudinaryServiceInterface $cloudinary,
    ) {
        parent::__construct($request, $permissionService);
    }

    public function profilePhoto()
    {
        $this->request->allowMethod(['post']);
        $this->currentUserId();

        $file = $this->request->getUploadedFile('photo');

        if (!$file || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return $this->jsonError('Nenhuma imagem enviada', 400);
        }

        if (in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $file->getSize() > self::MAX_SIZE) {
            return $this->jsonError('A imagem deve ter no máximo 5MB', 413);
        }

        if ($file->getError() !== UPLOAD_ERR_OK) {
            return $this->jsonError('Erro no upload da imagem', 400);
        }

        // Tipo real pelo conteúdo do arquivo (nunca pelo Content-Type do cliente).
        // getimagesize() em vez de finfo: a extensão fileinfo não está habilitada no PHP do projeto.
        $tmpPath = $file->getStream()->getMetadata('uri');
        $imageInfo = @getimagesize($tmpPath);
        $mimeType = $imageInfo['mime'] ?? null;

        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            return $this->jsonError('Formato de imagem inválido. Use JPEG, PNG ou WEBP', 415);
        }

        try {
            $url = $this->cloudinary->uploadProfilePhoto([
                'tmp_name' => $tmpPath,
                'name' => $file->getClientFilename(),
                'type' => $mimeType,
                'size' => $file->getSize(),
            ]);
        } catch (\Exception $e) {
            Log::error('Erro no upload da foto de perfil: ' . $e->getMessage());
            return $this->jsonError('Erro ao enviar a imagem', 502);
        }

        // "url" na raiz mantém compatibilidade com o frontend (ProfileEdit)
        return $this->jsonResponse(['success' => true, 'url' => $url]);
    }
}
