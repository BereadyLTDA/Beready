<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class VerificarEmail extends Mailable
{
    /**
     * @param  string  $token  token em texto puro (no banco fica só o hash)
     */
    public function __construct(public ?string $nome, public string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirme seu e-mail - BeReady');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verificar-email',
            with: [
                'nome' => $this->nome,
                'link' => config('beready.frontend_url').'verificar-email/'.$this->token,
                'expira' => now()->addDay()->format('d/m/Y H:i'),
            ],
        );
    }
}
