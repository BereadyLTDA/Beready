<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class RecuperarSenha extends Mailable
{
    /**
     * @param  string  $token  token em texto puro (no banco fica só o hash)
     */
    public function __construct(public ?string $nome, public string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Recuperação de Senha - BeReady');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.recuperar-senha',
            with: [
                'nome' => $this->nome,
                'resetLink' => config('beready.frontend_url').'reset-password/'.$this->token,
                'expires' => now()->addHour()->format('d/m/Y H:i'),
            ],
        );
    }
}
