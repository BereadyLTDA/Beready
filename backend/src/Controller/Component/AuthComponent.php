<?php
declare(strict_types=1);

namespace App\Controller\Component;

use Cake\Controller\Component;
use Cake\Controller\ComponentRegistry;
use Cake\ORM\TableRegistry;

class AuthComponent extends Component
{
    /**
     * Configurações padrão
     */
    protected array $_defaultConfig = [
        'authenticate' => [
            'Form' => [
                'fields' => ['username' => 'email', 'password' => 'password']
            ]
        ],
        'loginAction' => [
            'controller' => 'Usuarios',
            'action' => 'login'
        ],
        'loginRedirect' => [
            'controller' => 'Pages',
            'action' => 'display',
            'home'
        ],
        'logoutRedirect' => [
            'controller' => 'Usuarios',
            'action' => 'login'
        ],
        'authError' => 'Acesso não autorizado',
        'storage' => 'Session'
    ];

    protected $session;

    public function __construct(ComponentRegistry $registry, array $config = [])
    {
        parent::__construct($registry, $config);
        $this->session = $registry->getController()->getRequest()->getSession();
    }

    /**
     * Verifica se o usuário está logado
     */
    public function user($key = null)
    {
        $user = $this->session->read('Auth.User');
        if ($key === null) {
            return $user;
        }
        return $user[$key] ?? null;
    }

    /**
     * Faz login do usuário
     */
    public function identify()
    {
        $controller = $this->getController();
        $request = $controller->getRequest();

        if (!$request->is('post')) {
            return false;
        }

        $data = $request->getData();
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        // Busca o usuário no banco
        $usersTable = TableRegistry::getTableLocator()->get('Users');
        $user = $usersTable->find()
            ->where(['email' => $email])
            ->first();

        if ($user && password_verify($password, $user->senha_hash)) {
            // Salva o usuário na sessão
            $this->session->write('Auth.User', [
                'id' => $user->id,
                'email' => $user->email,
                'nome' => $user->nome,
            ]);
            return $user;
        }

        return false;
    }

    /**
     * Define o usuário na sessão (para login manual)
     */
    public function setUser($user)
    {
        $userData = [
            'id' => $user->id,
            'email' => $user->email,
        ];
        $this->session->write('Auth.User', $userData);
    }

    /**
     * Faz logout do usuário
     */
    public function logout()
    {
        $this->session->delete('Auth.User');
        return $this->getConfig('logoutRedirect');
    }

    /**
     * Verifica se o usuário está autenticado
     */
    public function isLoggedIn()
    {
        return $this->session->check('Auth.User');
    }

    /**
     * Retorna a URL de redirecionamento pós-login
     */
    public function redirectUrl()
    {
        return $this->session->read('Auth.redirect') ?: $this->getConfig('loginRedirect');
    }

    /**
     * Permite ações sem autenticação
     */
    public function allow($actions = null)
    {
        if ($actions === null) {
            return;
        }

        $controller = $this->getController();
        $allowedActions = $this->session->read('Auth.allowedActions') ?: [];

        if (is_array($actions)) {
            $allowedActions = array_merge($allowedActions, $actions);
        } else {
            $allowedActions[] = $actions;
        }

        $this->session->write('Auth.allowedActions', array_unique($allowedActions));
    }

    /**
     * Verifica se a ação atual é permitida
     */
    public function isActionAllowed($action)
    {
        $allowedActions = $this->session->read('Auth.allowedActions') ?: [];
        return in_array($action, $allowedActions);
    }

    /**
     * Inicialização do componente
     */
    public function startup(): void
    {
        $controller = $this->getController();
        $action = $controller->getRequest()->getParam('action');

        // Se não está logado e a ação não é permitida, redireciona para login
        if (!$this->isLoggedIn() && !$this->isActionAllowed($action)) {
            $controller->redirect($this->getConfig('loginAction'));
            return;
        }
    }
}