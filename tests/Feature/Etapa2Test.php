<?php

namespace Tests\Feature;

use App\Models\P1\Bitacora;
use App\Models\P1\Rol;
use App\Models\P2\Especialidad;
use App\Models\User;
use Database\Seeders\EspecialidadSeeder;
use Database\Seeders\RolSeeder;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Etapa2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);
        $this->seed(EspecialidadSeeder::class);
        $this->seed(UsuarioSeeder::class);
    }

    /**
     * CU1: Verificar que la pantalla de inicio de sesión se renderiza correctamente.
     */
    public function test_login_page_renders_correctly(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('MediCita Pro');
        $response->assertSee('Iniciar Sesión');
        $response->assertSee('admin@medicita.com');
    }

    /**
     * CU1 & CU19: Login exitoso con credenciales válidas y registro en bitácora.
     */
    public function test_user_can_login_with_valid_credentials_and_generates_audit_log(): void
    {
        $response = $this->post('/login', [
            'email' => 'admin@medicita.com',
            'password' => 'Admin123!',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        // Verificar que se registró en auditoría CU19
        $this->assertDatabaseHas('bitacoras', [
            'accion' => 'LOGIN_EXITOSO',
            'modulo' => 'P1_Seguridad',
        ]);
    }

    /**
     * CU1 & CU19: Bloqueo de cuenta tras 3 intentos fallidos consecutivos.
     */
    public function test_user_is_blocked_after_3_consecutive_failed_attempts(): void
    {
        $user = User::where('email', 'admin@medicita.com')->first();

        // Intento 1
        $this->post('/login', ['email' => 'admin@medicita.com', 'password' => 'wrongpass1']);
        $this->assertEquals(1, $user->fresh()->intentos_fallidos);

        // Intento 2
        $this->post('/login', ['email' => 'admin@medicita.com', 'password' => 'wrongpass2']);
        $this->assertEquals(2, $user->fresh()->intentos_fallidos);

        // Intento 3 -> Debe activar bloqueo
        $response = $this->post('/login', ['email' => 'admin@medicita.com', 'password' => 'wrongpass3']);
        $response->assertSessionHas('error');

        $userActualizado = $user->fresh();
        $this->assertEquals(3, $userActualizado->intentos_fallidos);
        $this->assertNotNull($userActualizado->bloqueado_hasta);
        $this->assertTrue($userActualizado->bloqueado_hasta->isFuture());

        // Verificar registro de auditoría CU19
        $this->assertDatabaseHas('bitacoras', [
            'accion' => 'CUENTA_BLOQUEADA',
            'modulo' => 'P1_Seguridad',
        ]);
    }

    /**
     * CU2: El Administrador puede ver el módulo de usuarios y roles.
     */
    public function test_admin_can_access_user_management(): void
    {
        $admin = User::where('email', 'admin@medicita.com')->first();

        $response = $this->actingAs($admin)->get('/usuarios');

        $response->assertStatus(200);
        $response->assertSee('Cuentas, Roles y Permisos (CU2)');
        $response->assertSee('Lic. María Elena Torres');
    }

    /**
     * CU2: Crear un nuevo usuario en el sistema.
     */
    public function test_admin_can_create_new_user(): void
    {
        $admin = User::where('email', 'admin@medicita.com')->first();
        $rolMedico = Rol::where('slug', 'medico')->first();

        $response = $this->actingAs($admin)->post('/usuarios', [
            'name' => 'Dra. Gabriela Vega',
            'ci' => '77889900',
            'email' => 'dra.vega@medicita.com',
            'rol_id' => $rolMedico->id,
            'telefono' => '79998888',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'estado' => 'activo',
        ]);

        $response->assertRedirect('/usuarios');
        $this->assertDatabaseHas('users', [
            'email' => 'dra.vega@medicita.com',
            'ci' => '77889900',
        ]);

        // Verificar auditoría
        $this->assertDatabaseHas('bitacoras', [
            'accion' => 'CREAR_USUARIO',
            'modulo' => 'P1_Seguridad',
        ]);
    }

    /**
     * CU3: Gestión y catálogo de especialidades médicas.
     */
    public function test_admin_can_create_and_manage_especialidades(): void
    {
        $admin = User::where('email', 'admin@medicita.com')->first();

        $response = $this->actingAs($admin)->post('/especialidades', [
            'nombre' => 'Dermatología',
            'codigo' => 'DERM-01',
            'descripcion' => 'Cuidado y diagnóstico clínico de la piel.',
            'color' => '#319795',
            'icono' => 'sparkles',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('especialidades', [
            'codigo' => 'DERM-01',
            'nombre' => 'Dermatología',
        ]);
    }

    /**
     * CU19: La bitácora registra eventos y se puede exportar a CSV.
     */
    public function test_bitacora_logs_and_exports_csv(): void
    {
        $admin = User::where('email', 'admin@medicita.com')->first();

        // 1. Acceder al índice de bitácora
        $response = $this->actingAs($admin)->get('/bitacora');
        $response->assertStatus(200);
        $response->assertSee('Bitácora de Auditoría (CU19)');

        // 2. Exportar a CSV
        $exportResponse = $this->actingAs($admin)->get('/bitacora/exportar');
        $exportResponse->assertStatus(200);
        $this->assertEquals('text/csv; charset=UTF-8', $exportResponse->headers->get('Content-Type'));
    }
}
