<?php
declare(strict_types=1);

namespace App\Controller;

class PingController extends AppController
{
    public function index()
    {
        $this->response = $this->response->withHeader('Content-Type', 'application/json');
        $this->response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'pong',
            'timestamp' => date('Y-m-d H:i:s')
        ]));
        return $this->response;
    }
}