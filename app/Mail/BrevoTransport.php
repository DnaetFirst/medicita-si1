<?php

namespace App\Mail;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class BrevoTransport extends AbstractTransport
{
    public function __construct(private string $apiKey)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        if ($this->apiKey === '') {
            Log::error('Brevo configuration error', ['reason' => 'missing_api_key']);
            throw new TransportException('BREVO_API_KEY no está configurada.');
        }
        $email = $message->getOriginalMessage();
        if (! $email instanceof Email || $email->getAttachments() !== []) {
            throw new TransportException('Brevo requiere un correo sin adjuntos; consulta los documentos en el portal.');
        }
        $payload = [
            'sender' => $this->address($email->getFrom()[0]),
            'to' => array_map($this->address(...), $email->getTo()),
            'subject' => $email->getSubject(),
        ];
        foreach (['cc' => $email->getCc(), 'bcc' => $email->getBcc()] as $key => $addresses) {
            if ($addresses !== []) {
                $payload[$key] = array_map($this->address(...), $addresses);
            }
        }
        if ($email->getReplyTo() !== []) {
            $payload['replyTo'] = $this->address($email->getReplyTo()[0]);
        }
        if ($email->getHtmlBody() !== null) {
            $payload['htmlContent'] = $email->getHtmlBody();
        }
        if ($email->getTextBody() !== null) {
            $payload['textContent'] = $email->getTextBody();
        }
        try {
            $response = Http::acceptJson()->withHeaders(['api-key' => $this->apiKey])
                ->connectTimeout(5)->timeout(15)->post('https://api.brevo.com/v3/smtp/email', $payload);
        } catch (ConnectionException) {
            Log::error('Brevo connection failed');
            throw new TransportException('No se pudo conectar con Brevo por HTTPS.');
        }
        if (! $response->successful() || ! is_string($response->json('messageId')) || $response->json('messageId') === '') {
            $providerCode = $response->json('code');
            Log::error('Brevo rejected email', [
                'http_status' => $response->status(),
                'provider_code' => is_string($providerCode) && preg_match('/^[a-zA-Z_]{1,60}$/', $providerCode) ? $providerCode : 'unknown',
            ]);
            throw new TransportException('Brevo no aceptó el correo (HTTP '.$response->status().'). Revisa la clave, el remitente y la cuota.');
        }
        $message->setMessageId($response->json('messageId'));
    }

    /** @return array{email: string, name?: string} */
    private function address(Address $address): array
    {
        return array_filter(['email' => $address->getAddress(), 'name' => $address->getName()], fn (string $value): bool => $value !== '');
    }

    public function __toString(): string
    {
        return 'brevo+https';
    }
}
