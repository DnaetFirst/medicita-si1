<?php

namespace App\Mail;

use App\Models\P7\Notificacion;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AvisoCitaMail extends Mailable
{
    public function __construct(public Notificacion $aviso) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->aviso->titulo);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.citas.aviso');
    }
}
