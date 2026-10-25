<?php

namespace App\Mail;

use App\Models\P4\Cita;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CitaCanceladaMail extends Mailable
{
    use Queueable, SerializesModels;

    public Cita $cita;
    public string $motivo;

    public function __construct(Cita $cita, string $motivo)
    {
        $this->cita = $cita;
        $this->motivo = $motivo;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Aviso de Cancelación de Cita {$this->cita->codigo_ficha} — MediCita Pro",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.citas.cancelada',
        );
    }
}
