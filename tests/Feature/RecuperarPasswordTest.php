<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class RecuperarPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_links_to_recovery_and_both_forms_render(): void
    {
        $this->get(route('login'))->assertOk()->assertSee(route('password.request'));
        $this->get(route('password.request'))->assertOk()->assertSee('Enviar enlace de recuperación');
        $this->get(route('password.reset', ['token' => 'example', 'email' => 'patient@example.com']))
            ->assertOk()->assertSee('Confirmar contraseña');
    }

    public function test_active_user_receives_single_use_reset_link_on_configured_host(): void
    {
        Notification::fake();
        config(['app.url' => 'https://clinic.example.com']);
        $user = User::factory()->create(['estado' => 'activo']);

        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('success');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $mail = $notification->toMail($user);
            $this->assertStringStartsWith('https://clinic.example.com/restablecer-password/', $mail->actionUrl);

            return Password::tokenExists($user, $notification->token);
        });
    }

    public function test_unknown_and_inactive_accounts_do_not_receive_mail(): void
    {
        Notification::fake();
        $user = User::factory()->create(['estado' => 'inactivo']);

        $response = $this->post(route('password.email'), ['email' => $user->email]);
        $response->assertSessionHas('success');
        $message = session('success');
        $this->post(route('password.email'), ['email' => 'unknown@example.com'])->assertSessionHas('success', $message);

        Notification::assertNothingSent();
    }

    public function test_reset_changes_password_clears_lockout_and_revokes_sessions_and_token(): void
    {
        $user = User::factory()->create(['estado' => 'activo', 'intentos_fallidos' => 3, 'bloqueado_hasta' => now()->addMinutes(15), 'debe_cambiar_password' => true]);
        DB::table('sessions')->insert(['id' => 'old-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $oldRememberToken = $user->remember_token;
        $token = Password::createToken($user);
        $payload = ['email' => $user->email, 'token' => $token, 'password' => 'NuevaClave123!', 'password_confirmation' => 'NuevaClave123!'];

        $this->post(route('password.update'), $payload)->assertRedirect(route('login'))->assertSessionHas('success');

        $user->refresh();
        $this->assertTrue(Hash::check('NuevaClave123!', $user->password));
        $this->assertFalse($user->debe_cambiar_password);
        $this->assertNull($user->bloqueado_hasta);
        $this->assertSame(0, $user->intentos_fallidos);
        $this->assertNotSame($oldRememberToken, $user->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'old-session']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->post(route('password.update'), $payload)->assertSessionHasErrors('email');
    }

    #[TestWith(['invalid'])]
    #[TestWith(['expired'])]
    #[TestWith(['inactive'])]
    public function test_unusable_tokens_do_not_change_password(string $reason): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['estado' => $reason === 'inactive' ? 'inactivo' : 'activo']);
        $originalPassword = $user->password;
        $token = Password::createToken($user);
        if ($reason === 'expired') {
            $this->travel(61)->minutes();
        }

        $this->post(route('password.update'), ['email' => $user->email, 'token' => $reason === 'invalid' ? 'bad-token' : $token, 'password' => 'NuevaClave123!', 'password_confirmation' => 'NuevaClave123!'])
            ->assertSessionHasErrors('email');

        $this->assertSame($originalPassword, $user->fresh()->password);
    }

    public function test_password_confirmation_and_strength_are_required(): void
    {
        $user = User::factory()->create(['estado' => 'activo']);
        $originalPassword = $user->password;

        $this->post(route('password.update'), ['email' => $user->email, 'token' => Password::createToken($user), 'password' => 'weak', 'password_confirmation' => 'different'])
            ->assertSessionHasErrors('password');

        $this->assertSame($originalPassword, $user->fresh()->password);
    }

    public function test_repeated_requests_are_limited(): void
    {
        Notification::fake();
        $user = User::factory()->create(['estado' => 'activo']);
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.email'), ['email' => $user->email])->assertRedirect();
        }

        $this->post(route('password.email'), ['email' => $user->email])->assertTooManyRequests();

        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }
}
