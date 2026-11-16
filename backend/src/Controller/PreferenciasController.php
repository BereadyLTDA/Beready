<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Traits\ResourceErrorTrait;
use App\Services\PermissionService;
use App\Services\PreferenciaService;
use Cake\Http\ServerRequest;

class PreferenciasController extends AppController
{
    use ResourceErrorTrait;

    public function __construct(
        ServerRequest $request,
        PermissionService $permissionService,
        private PreferenciaService $service,
    ) {
        parent::__construct($request, $permissionService);
    }

    // GET /preferencias/usuario/{usuarioId}
    public function getByUsuario($usuarioId = null)
    {
        $userId = $usuarioId ?? $this->request->getParam('usuarioId') ?? $this->request->getQuery('usuarioId');

        if (!$userId) {
            return $this->jsonError('ID do usuário não informado', 400);
        }

        try {
            if (!$this->canAccessUser((int)$userId)) {
                return $this->jsonError('Acesso negado', 403);
            }

            return $this->jsonSuccess($this->service->getByUsuarioId((int)$userId));
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao carregar preferências');
        }
    }

    // POST /preferencias — sempre do usuário autenticado (usuario_id do corpo é ignorado)
    public function save()
    {
        try {
            $preferencias = $this->service->save($this->currentUserId(), $this->getRequestData());
            return $this->jsonSuccess($preferencias, 'Preferências salvas com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao salvar preferências');
        }
    }
}
