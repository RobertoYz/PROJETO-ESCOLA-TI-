<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CodigoVerificacaoCadastroMail extends Mailable
{
    public function __construct(public string $codigo, public ?string $nome = null)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirme seu e-mail no Anjo Nexus');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.codigo-verificacao-cadastro');
    }
}
