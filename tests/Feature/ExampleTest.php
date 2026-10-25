<?php

namespace Tests\Feature;

use App\Models\P1\Rol;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Unauthenticated user is redirected to login.
     */
    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    /**
     * Authenticated user can see the dashboard.
     */
    public function test_authenticated_user_can_access_dashboard(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $admin = User::where('email', 'admin@medicita.com')->first();

        $response = $this->actingAs($admin)->get('/');

        $response->assertStatus(200);
        $response->assertSee('Panel de Control Clínico');
        $response->assertSee('MediCita Pro');
    }
}
