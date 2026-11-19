<?php

namespace App\Exceptions;

/**
 * Login com a senha certa, mas o e-mail ainda não foi confirmado pelo link enviado no cadastro.
 */
class EmailNaoVerificadoException extends \RuntimeException
{
    public const CODIGO = 'email_nao_verificado';

    public function __construct(string $message = 'Confirme seu e-mail para entrar. Enviamos um link para a sua caixa de entrada.')
    {
        parent::__construct($message, 403);
    }
}
