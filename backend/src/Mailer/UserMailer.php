<?php
declare(strict_types=1);

namespace App\Mailer;

use Cake\Mailer\Mailer;
use function Cake\Core\env;

class UserMailer extends Mailer
{
    public function resetPassword($user, string $token)
    {
        $frontendUrl = env('APP_BASE_URL');
        $resetLink = $frontendUrl . '/reset-password/' . $token;
        
        $this->setTransport('default')
            ->setFrom([env('EMAIL_FROM', 'noreply@beready.com') => 'BeReady'])
            ->setTo($user->email)
            ->setSubject('Recuperação de Senha - BeReady')
            ->setEmailFormat('html')
            ->setViewVars([
                'nome' => $user->nome,
                'resetLink' => $resetLink,
                'expires' => date('d/m/Y H:i', strtotime('+1 hour'))
            ])
            ->setTemplate('reset_password');
    }
}