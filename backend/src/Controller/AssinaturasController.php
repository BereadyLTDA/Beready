<?php

namespace App\Controller;

use App\Services\AssinaturaService;
use App\Services\PermissionService;
use Cake\Http\ServerRequest;

class AssinaturasController extends AppController
{
    public function __construct(
        ServerRequest $request,
        PermissionService $permissionService,
        private AssinaturaService $assinaturaService,
    ) {
        parent::__construct($request, $permissionService);
    }

    /**
     * GET /user/assinatura
     */
    public function current()
    {
        $this->request->allowMethod(['get']);

        $assinatura = $this->assinaturaService->getAtivaOuGratuita($this->currentUserId());

        if (!$assinatura) {
            return $this->jsonError('Nenhuma assinatura ativa encontrada', 404);
        }

        return $this->jsonSuccess($assinatura);
    }
}
