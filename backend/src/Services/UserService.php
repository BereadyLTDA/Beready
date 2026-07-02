<?php
declare(strict_types=1);

namespace App\Services;

use App\Contracts\UserUseCaseInterface;
use App\Contracts\UserRepositoryInterface;
use App\Exceptions\EmailAlreadyExistsException;
use App\Exceptions\WeakPasswordException;
use App\Exceptions\InvalidTokenException;
use App\Exceptions\UserNotFoundException;
use Cake\Mailer\MailerAwareTrait;
use Ramsey\Uuid\Uuid;

class UserService implements UserUseCaseInterface
{
    use MailerAwareTrait;
    
    private UserRepositoryInterface $userRepository;
    
    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }
    
    public function register(array $data): array
    {
        if (empty($data['nome']) || empty($data['email']) || empty($data['senha'])) {
            throw new \InvalidArgumentException('Nome, e-mail e senha são obrigatórios');
        }
        if (strlen($data['senha']) < 6) {
            throw new WeakPasswordException('A senha deve ter pelo menos 6 caracteres');
        }
        if ($this->userRepository->emailExists($data['email'])) {
            throw new EmailAlreadyExistsException();
        }
        $data['uuid'] = Uuid::uuid4()->toString();
        $data['senha_hash'] = password_hash($data['senha'], PASSWORD_DEFAULT);
        unset($data['senha']);
        $user = $this->userRepository->create($data);
        unset($user['senha_hash']);
        return $user;
    }
    
    public function login(string $email, string $password): array
    {
        error_log("=== LOGIN SERVICE ===");
        error_log("Email: " . $email);
        
        if (empty($email) || empty($password)) {
            throw new \InvalidArgumentException('E-mail e senha são obrigatórios');
        }
        
        $user = $this->userRepository->findByEmail($email);
        
        error_log("User found: " . ($user ? 'YES' : 'NO'));
        
        if (!$user) {
            throw new \RuntimeException('E-mail ou senha inválidos', 401);
        }
        
        error_log("senha_hash exists: " . (isset($user['senha_hash']) ? 'YES' : 'NO'));
        error_log("senha_hash value: " . ($user['senha_hash'] ?? 'NULL'));
        
        $passwordVerify = password_verify($password, $user['senha_hash'] ?? '');
        error_log("Password verify: " . ($passwordVerify ? 'TRUE' : 'FALSE'));
        
        if (!$passwordVerify) {
            throw new \RuntimeException('E-mail ou senha inválidos', 401);
        }
        
        $this->userRepository->update($user['id'], ['ultimo_login' => date('Y-m-d H:i:s')]);
        
        unset($user['senha_hash']);
        
        return $user;
    }
    
    public function getUserById(int $id): array
    {
        $user = $this->userRepository->findById($id);
        if (!$user) {
            throw new UserNotFoundException();
        }
        unset($user['senha_hash']);
        return $user;
    }
    
    public function getUserByUuid(string $uuid): array
    {
        $user = $this->userRepository->findByUuid($uuid);
        if (!$user) {
            throw new UserNotFoundException();
        }
        unset($user['senha_hash']);
        return $user;
    }
    
    public function updateUser(int $id, array $data): array
    {
        $user = $this->userRepository->findById($id);
        if (!$user) {
            throw new UserNotFoundException();
        }
        
        if (isset($data['email']) && $this->userRepository->emailExists($data['email'], $id)) {
            throw new EmailAlreadyExistsException('Este e-mail já está em uso');
        }
        
        if (isset($data['senha'])) {
            if (strlen($data['senha']) < 6) {
                throw new WeakPasswordException('A senha deve ter pelo menos 6 caracteres');
            }
            $data['senha_hash'] = password_hash($data['senha'], PASSWORD_DEFAULT);
            unset($data['senha']);
        }
        
        $updatedUser = $this->userRepository->update($id, $data);
        unset($updatedUser['senha_hash']);
        
        return $updatedUser;
    }
    
    public function deleteUser(int $id): bool
    {
        $user = $this->userRepository->findById($id);
        if (!$user) {
            throw new UserNotFoundException();
        }
        
        return $this->userRepository->delete($id);
    }
    
    public function forgotPassword(string $email): void
    {
        $user = $this->userRepository->findByEmail($email);
        if (!$user) {
            // Não revelamos se o e-mail existe ou não (segurança)
            return;
        }
        
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $this->userRepository->updateResetToken($user['id'], $token, $expires);
        
        // Envia e-mail (você precisa implementar o Mailer)
        // $this->getMailer('User')->resetPassword((object)$user, $token);
    }
    
    public function resetPassword(string $token, string $newPassword): void
    {
        $user = $this->userRepository->findByResetToken($token);
        if (!$user) {
            throw new InvalidTokenException('Token inválido ou expirado');
        }
        if (strlen($newPassword) < 6) {
            throw new WeakPasswordException('A senha deve ter pelo menos 6 caracteres');
        }
        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->userRepository->update($user['id'], ['senha_hash' => $hashed]);
        $this->userRepository->updateResetToken($user['id'], null, null);
    }
}