<?php

namespace Tests\Feature;

use App\Models\P1\Rol;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->superadmin = User::where('email', 'superadmin@medicita.com')->firstOrFail();
    }

    /**
     * Verificar que el superusuario tiene rol 'superadmin' y sus helpers responden true.
     */
    public function test_superadmin_has_correct_role_and_helpers(): void
    {
        $this->assertEquals('superadmin', $this->superadmin->rol->slug);
        $this->assertTrue($this->superadmin->isSuperAdmin());
        $this->assertTrue($this->superadmin->isAdmin());
    }

    /**
     * Verificar que el superusuario puede iniciar sesión exitosamente.
     */
    public function test_superadmin_can_login(): void
    {
        $response = $this->post('/login', [
            'email' => 'superadmin@medicita.com',
            'password' => 'SuperAdmin123!',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->superadmin);
    }

    /**
     * Verificar que el superusuario tiene acceso a TODOS los módulos del sistema (CU1 a CU19).
     */
    public function test_superadmin_has_unrestricted_access_to_all_modules(): void
    {
        $rutas = [
            '/' => 'Panel de Control Clínico',
            '/usuarios' => 'Gestión de Usuarios',
            '/bitacora' => 'Bitácora de Auditoría',
            '/especialidades' => 'Especialidades Médicas',
            '/medicos' => 'Gestión de Médicos',
            '/horarios' => 'Configuración de Horarios de Atención',
            '/bloqueos' => 'Gestión de Bloqueos de Agenda',
            '/pacientes' => 'Fichas de Pacientes',
            '/citas' => 'Control de Citas Médicas y Recepción',
            '/citas/nueva' => 'Solicitud de Ficha y Reserva de Citas',
            '/agenda' => 'Agenda Médica y Turnos',
        ];

        foreach ($rutas as $ruta => $contenidoEsperado) {
            $response = $this->actingAs($this->superadmin)->get($ruta);
            $response->assertStatus(200, "El superusuario debería tener acceso directo a {$ruta}");
            $response->assertSee($contenidoEsperado);
        }
    }
}
