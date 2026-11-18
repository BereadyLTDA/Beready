<?php

namespace App\Http\Controllers;

use App\Models\RespostaUsuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RespostasController extends Controller
{
    private const TIPOS = ['flashcard', 'quiz'];

    // POST /respostas  { tipo: 'flashcard'|'quiz', referencia_id, correto: true|false }
    // A resposta é sempre registrada para o usuário autenticado.
    public function save(Request $request): JsonResponse
    {
        $data = $this->requestData($request);

        if (empty($data['tipo']) || empty($data['referencia_id']) || ! isset($data['correto'])) {
            return $this->jsonError('Dados incompletos para registrar resposta', 400);
        }

        // Mesmas regras do RespostasUsuarioTable do CakePHP
        $erros = [];
        if (! in_array($data['tipo'], self::TIPOS, true)) {
            $erros['tipo']['inList'] = 'The provided value must be one of: `flashcard`, `quiz`';
        }
        if (filter_var($data['referencia_id'], FILTER_VALIDATE_INT) === false) {
            $erros['referencia_id']['integer'] = 'The provided value must be an integer';
        }
        if (filter_var($data['correto'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === null) {
            $erros['correto']['boolean'] = 'The provided value must be a boolean';
        }
        if ($erros) {
            return $this->jsonError('Erro ao registrar resposta', 422, $erros);
        }

        try {
            $resposta = RespostaUsuario::create([
                'usuario_id' => $this->currentUserId(),
                'tipo' => $data['tipo'],
                'referencia_id' => (int) $data['referencia_id'],
                'correto' => filter_var($data['correto'], FILTER_VALIDATE_BOOLEAN),
            ]);

            return $this->jsonSuccess($resposta, 'Resposta registrada com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao registrar resposta');
        }
    }
}
