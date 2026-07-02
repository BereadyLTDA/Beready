<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\ORM\TableRegistry;

class ApiController extends AppController
{
    private $UsersTable;
    
    public function initialize(): void
    {
        parent::initialize();
        
        // CakePHP 5.x: Use TableRegistry
        $this->UsersTable = TableRegistry::getTableLocator()->get('Users');
    }
    
    public function health()
    {
        $this->response = $this->response->withType('application/json');
        $this->response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'API funcionando!',
            'timestamp' => date('Y-m-d H:i:s')
        ]));
        return $this->response;
    }
    
    public function notFound()
    {
        $this->response = $this->response->withStatus(404)
            ->withType('application/json');
        $this->response->getBody()->write(json_encode([
            'success' => false,
            'error' => 'Not Found',
            'message' => 'Endpoint não encontrado',
            'timestamp' => date('Y-m-d H:i:s')
        ]));
        return $this->response;
    }
}