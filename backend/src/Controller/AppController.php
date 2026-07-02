<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Controller\Controller;
use Cake\Event\EventInterface;

class AppController extends Controller
{
    public function initialize(): void
    {
        parent::initialize();
        
        $this->response = $this->response->withType('application/json');
        $this->autoRender = false;
    }

    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);
        
        if ($this->request->is('options')) {
            $this->response = $this->response->withStatus(200);
            $this->response = $this->response->withStringBody('');
        }
    }
    
    protected function jsonResponse($data, $status = 200)
    {
        $this->response = $this->response->withStatus($status);
        $this->response = $this->response->withType('application/json');
        $this->response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $this->response;
    }

    protected function jsonSuccess($data = null, string $message = 'success', int $status = 200)
    {
        $this->response = $this->response->withStatus($status);
        $this->response = $this->response->withType('application/json');
        $this->response->getBody()->write(json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]));
        return $this->response;
    }

    protected function jsonError(string $message, int $status = 400)
    {
        $this->response = $this->response->withStatus($status);
        $this->response = $this->response->withType('application/json');
        $this->response->getBody()->write(json_encode([
            'success' => false,
            'message' => $message
        ]));
        return $this->response;
    }

}