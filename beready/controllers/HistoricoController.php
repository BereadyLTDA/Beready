<?php
require_once '../core/Database.php';
require_once '../exceptions/AppException.php';
require_once './models/Historico.php';

class HistoricoController
{
    public function registrarBusca()
    {
        $dados = json_decode(file_get_contents("php://input"), true);
        echo json_encode(salvarHistorico($dados));
    }

    public function listarHistorico()
    {
        $usuario_id = $_GET['usuario_id'] ?? null;
        echo json_encode(obterHistorico($usuario_id));
    }
}
