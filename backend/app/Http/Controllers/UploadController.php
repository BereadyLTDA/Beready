<?php

namespace App\Http\Controllers;

use App\Contracts\CloudinaryServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Exige access token (middleware jwt).
 */
class UploadController extends Controller
{
    private const MAX_SIZE = 5 * 1024 * 1024;

    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(private CloudinaryServiceInterface $cloudinary) {}

    // POST /upload/profile-photo  (multipart, campo "photo")
    public function profilePhoto(Request $request): JsonResponse
    {
        $this->currentUserId();

        $file = $request->file('photo');

        if (! $file) {
            return $this->jsonError('Nenhuma imagem enviada', 400);
        }

        if (in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $file->getSize() > self::MAX_SIZE) {
            return $this->jsonError('A imagem deve ter no máximo 5MB', 413);
        }

        if (! $file->isValid()) {
            return $this->jsonError('Erro no upload da imagem', 400);
        }

        // Tipo real pelo conteúdo do arquivo (nunca pelo Content-Type do cliente)
        $tmpPath = $file->getRealPath();
        $imageInfo = $tmpPath ? @getimagesize($tmpPath) : false;
        $mimeType = $imageInfo['mime'] ?? null;

        if (! in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            return $this->jsonError('Formato de imagem inválido. Use JPEG, PNG ou WEBP', 415);
        }

        try {
            $url = $this->cloudinary->uploadProfilePhoto([
                'tmp_name' => (string) $tmpPath,
                'name' => $file->getClientOriginalName(),
                'type' => $mimeType,
                'size' => $file->getSize(),
            ]);
        } catch (\Exception $e) {
            Log::error('Erro no upload da foto de perfil: '.$e->getMessage());

            return $this->jsonError('Erro ao enviar a imagem', 502);
        }

        // "url" na raiz mantém compatibilidade com o frontend (ProfileEdit)
        return response()->json(['success' => true, 'url' => $url]);
    }
}
