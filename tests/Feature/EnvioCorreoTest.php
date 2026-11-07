<?php

namespace Tests\Feature;

use App\Jobs\EnviarAviso;
use App\Models\P7\Notificacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class EnvioCorreoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['mail.default' => 'brevo', 'mail.mailers.brevo.key' => 'test-key', 'mail.from.address' => 'clinic@example.com']);
        Mail::purge('brevo');
    }

    public function test_notification_is_sent_to_patient_through_https_and_not_resent(): void
    {
        Http::fake(['https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'accepted-123'], 201)]);
        $aviso = $this->aviso();

        (new EnviarAviso($aviso->id))->handle();
        (new EnviarAviso($aviso->id))->handle();

        Http::assertSent(fn ($request): bool => $request->hasHeader('api-key', 'test-key') && $request['to'][0]['email'] === 'patient@example.com' && $request['sender']['email'] === 'clinic@example.com' && $request['subject'] === 'Cita programada' && str_contains($request['htmlContent'], 'Aviso de prueba'));
        Http::assertSentCount(1);
        $this->assertDatabaseHas('notificaciones', ['id' => $aviso->id, 'estado_envio' => 'enviado', 'intentos' => 1]);
        $this->assertNotNull($aviso->fresh()->enviado_at);
    }

    #[TestWith([401])]
    #[TestWith([400])]
    #[TestWith([429])]
    #[TestWith([500])]
    #[TestWith([201])]
    public function test_provider_rejection_or_missing_message_id_remains_retryable(int $status): void
    {
        Http::fake(['https://api.brevo.com/v3/smtp/email' => Http::response([], $status)]);
        $aviso = $this->aviso();

        (new EnviarAviso($aviso->id))->handle();

        $this->assertDatabaseHas('notificaciones', ['id' => $aviso->id, 'estado_envio' => 'fallido', 'enviado_at' => null, 'intentos' => 1]);
        Http::assertSentCount(1);
    }

    public function test_missing_api_key_does_not_send_or_mark_notification_sent(): void
    {
        config(['mail.mailers.brevo.key' => '']);
        $aviso = $this->aviso();

        (new EnviarAviso($aviso->id))->handle();

        Http::assertNothingSent();
        $this->assertDatabaseHas('notificaciones', ['id' => $aviso->id, 'estado_envio' => 'fallido', 'enviado_at' => null]);
    }

    public function test_production_log_mailer_is_not_treated_as_delivery(): void
    {
        $this->app->instance('env', 'production');
        config(['mail.default' => 'log']);
        $aviso = $this->aviso();

        (new EnviarAviso($aviso->id))->handle();

        $this->assertDatabaseHas('notificaciones', ['id' => $aviso->id, 'estado_envio' => 'fallido', 'enviado_at' => null]);
        Http::assertNothingSent();
    }

    public function test_password_reset_uses_brevo_and_reports_delivery_errors(): void
    {
        Http::fake(['https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'reset-123'], 201)]);
        $user = User::factory()->create(['estado' => 'activo']);

        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('success');

        Http::assertSent(fn ($request): bool => $request['to'][0]['email'] === $user->email && str_contains($request['htmlContent'], '/restablecer-password/'));
    }

    public function test_password_reset_does_not_claim_success_after_provider_failure(): void
    {
        Http::fake(['https://api.brevo.com/v3/smtp/email' => Http::response([], 401)]);
        $user = User::factory()->create(['estado' => 'activo']);

        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHasErrors('email')->assertSessionMissing('success');

        Http::assertSentCount(1);
    }

    private function aviso(): Notificacion
    {
        return Notificacion::create([
            'user_id' => User::factory()->create(['email' => 'patient@example.com', 'estado' => 'activo'])->id,
            'evento_id' => 'test:notification', 'tipo' => 'reserva', 'titulo' => 'Cita programada', 'mensaje' => 'Aviso de prueba', 'leido' => false,
        ]);
    }
}
