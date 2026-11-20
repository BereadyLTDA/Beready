<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class LembreteEstudo extends Mailable
{
    public function __construct(public ?string $nome, public int $cardsParaRevisar) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Seus cards estão esperando - BeReady');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lembrete-estudo',
            with: [
                'nome' => $this->nome,
                'cards' => $this->cardsParaRevisar,
                'link' => config('beready.frontend_url').'flashcards/revisao',
                'preferencias' => config('beready.frontend_url').'preferencias',
            ],
        );
    }
}
