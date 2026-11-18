<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class BoasVindas extends Mailable
{
    public function __construct(public string $nome) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Bem-vindo ao BeReady!');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.boas-vindas',
            with: [
                'nome' => $this->nome ?: 'Usuário',
                'loginLink' => config('beready.frontend_url').'login',
                'ano' => date('Y'),
            ],
        );
    }
}
