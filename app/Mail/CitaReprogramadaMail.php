<?php

namespace App\Mail;

use App\Models\P4\Cita;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CitaReprogramadaMail extends Mailable
{
    use Queueable, SerializesModels;

    public Cita $citaNueva;
    public Cita $citaAnterior;

    public function __construct(Cita $citaNueva, Cita $citaAnterior)
    {
        $this->citaNueva = $citaNueva;
        $this->citaAnterior = $citaAnterior;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Reprogramación de Cita Médica {$this->citaNueva->codigo_ficha} — MediCita Pro",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.citas.reprogramada',
        );
    }
}
