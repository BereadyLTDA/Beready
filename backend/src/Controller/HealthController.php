<?php
declare(strict_types=1);

namespace App\Controller;

class HealthController extends AppController
{
    public function check()
    {
        $this->response = $this->response->withType('application/json');
        $this->response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'API funcionando!',
            'timestamp' => date('Y-m-d H:i:s')
        ]));
        return $this->response;
    }
    
    public function ping()
    {
        return $this->check();
    }
}