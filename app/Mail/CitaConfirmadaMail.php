<?php

namespace App\Mail;

use App\Models\P4\Cita;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CitaConfirmadaMail extends Mailable
{
    use Queueable, SerializesModels;

    public Cita $cita;

    public function __construct(Cita $cita)
    {
        $this->cita = $cita;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Confirmación de Ficha Médica {$this->cita->codigo_ficha} — MediCita Pro",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.citas.confirmada',
        );
    }
}
