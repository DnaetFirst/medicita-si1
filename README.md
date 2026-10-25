# MediCita Pro — Sistema de Gestión de Citas Médicas (Ciclo 1)

> **Materia:** Sistemas de Información 1 (SI1) — UAGRM  
> **Repositorio Oficial:** `https://github.com/DnaetFirst/medicita-si1`  
> **Despliegue Cloud:** Render (Backend + Web) + Neon Serverless PostgreSQL ($0 USD Presentation Tier)

---

## 🏥 Descripción del Proyecto

**MediCita Pro** es una plataforma integral de gestión clínica desarrollada bajo la arquitectura de **3 capas** y estructurada en **paquetes modulares (P1 a P7)** de acuerdo a las especificaciones formales del documento de requerimientos de la asignatura.

El sistema digitaliza todo el ciclo de atención ambulatoria: desde la autenticación con protección contra ataques de fuerza bruta, configuración de agendas médicas con turnos fijos de **15 minutos** y **1 hora obligatoria de descanso**, hasta la emisión asistida de fichas médicas en **4 pasos**, control de asistencia (check-in en recepción), reprogramación/cancelación de citas y auditoría total.

---

## 🏛️ Arquitectura del Sistema (3 Capas y Paquetes Modulares)

```
┌─────────────────────────────────────────────────────────────┐
│                 CAPA DE PRESENTACIÓN (UI/UX)                │
│   Blade Components · Vanilla CSS ("Medical Blue") · SVG    │
│   Diseño Responsivo · Flujos Asistidos · Vistas Imprimibles │
└──────────────────────────────┬──────────────────────────────┘
                               │ HTTP / JSON
┌──────────────────────────────▼──────────────────────────────┐
│             CAPA DE LÓGICA DE NEGOCIO (Laravel 11)          │
│   ├── P1_Usuarios       (CU1 Autenticación, CU2 Cuentas)   │
│   ├── P2_MedicosAgendas (CU3 Especialidades, CU4 Médicos,   │
│   │                      CU5 Horarios/Slots, CU6 Bloqueos,  │
│   │                      CU12 Consulta de Agenda)           │
│   ├── P3_Pacientes      (CU7 Pacientes y Tutores Legales)   │
│   ├── P4_Citas          (CU8 Wizard Ficha, CU9 Comprobante, │
│   │                      CU10 Control Check-in, CU11 Cancel)│
│   └── P7_Notificaciones (CU16 Email Mails & Alertas, CU19)  │
└──────────────────────────────┬──────────────────────────────┘
                               │ Eloquent ORM / SQL
┌──────────────────────────────▼──────────────────────────────┐
│                    CAPA DE DATOS (PostgreSQL)               │
│   Neon Serverless PostgreSQL (SSL Obligatorio / Pooler)     │
│   10 Tablas Normalizadas · Restricciones de Integridad      │
└─────────────────────────────────────────────────────────────┘
```

---

## 📋 Casos de Uso Implementados (Ciclo 1 — 14 Casos de Uso)

| Paquete | Caso de Uso | Descripción Técnica |
| :--- | :--- | :--- |
| **P1** | **CU1: Autenticación y Autorización** | Login con control de fuerza bruta (**bloqueo de 15 min tras 3 intentos fallidos consecutivos**), hashing Bcrypt y middleware RBAC (`CheckRole`). |
| **P1** | **CU2: Cuentas, Roles y Permisos** | CRUD de usuarios, asignación de roles (`admin`, `recepcionista`, `medico`, `paciente`) y desactivación lógica. |
| **P2** | **CU3: Especialidades Médicas** | Catálogo con código de color distintivo, descripción clínica y bloqueo de eliminación si tiene médicos activos asignados. |
| **P2** | **CU4: Gestión de Médicos** | Registro de médicos vinculados a cuentas de usuario, especialidad clínica, matrícula profesional y biografía. |
| **P2** | **CU5: Horarios de Atención y Slots** | Matriz semanal individualizada. Generación de **turnos de 15 minutos** con **reserva estricta de 1 hora de descanso** (`estado: descanso`). |
| **P2** | **CU6: Bloqueos de Agenda** | Registro de bloqueos por feriados, permisos o emergencias. Inhabilita de inmediato turnos a `bloqueado` y los libera al cancelarse. |
| **P3** | **CU7: Pacientes y Responsables** | Ficha clínica de paciente con datos de contacto, grupo sanguíneo y acreditación de **tutores/responsables legales con fecha límite de vigencia**. |
| **P4** | **CU8: Solicitud de Ficha Médica** | **Wizard guiado en 4 pasos** (Especialidad ➔ Médico y Calendario ➔ Selección de Turno 15m ➔ Paciente y Confirmación) con reserva atómica pesimista. |
| **P4** | **CU9: Comprobante de Cita** | Ficha médica digital e imprimible con código único (`FCH-YYYYMMDD-XXXX`), QR/token simulado e instrucciones de atención. |
| **P4** | **CU10: Control de Asistencia** | Tablero de citas con transiciones de estado: `programada` ➔ `confirmada` (Check-in en recepción) ➔ `en_curso` (en consultorio) ➔ `atendida` o `no_asistio`. |
| **P4** | **CU11: Cancelación y Reprogramación** | Cancelación con motivo obligatorio y liberación inmediata del cupo a `disponible`. Reprogramación atómica hacia un nuevo turno. |
| **P2** | **CU12: Consulta de Agenda Médica** | Visualizador de jornada diaria por médico con conteo de turnos en tiempo real y endpoint API JSON reactivo (`/api/agenda/slots`). |
| **P7** | **CU16: Notificaciones por Correo** | Mails transaccionales (`CitaConfirmadaMail`, `CitaReprogramadaMail`, `CitaCanceladaMail`) con diseño *Medical Blue* y registro de alertas en BD. |
| **P1** | **CU19: Bitácora de Auditoría** | Registro inmutable de operaciones críticas (usuario, IP, módulo, acción, detalle JSON y timestamp) con exportación en CSV. |

---

## 🔑 Credenciales de Acceso para Demostración

| Rol | Correo Electrónico | Contraseña | Acceso y Permisos |
| :--- | :--- | :--- | :--- |
| **⚡ Superusuario** | `superadmin@medicita.com` | `SuperAdmin123!` | **Acceso Maestro Irrestricto a TODO el sistema**, módulos, auditoría, médicos, citas y configuración. |
| **Administrador** | `admin@medicita.com` | `Admin123!` | Acceso administrativo general, bitácora, usuarios y roles. |
| **Recepcionista** | `recepcion@medicita.com` | `Recepcion123!` | Emisión de fichas (CU8), check-in (CU10), agenda, pacientes y citas. |
| **Médico** | `dr.perez@medicita.com` | `Medico123!` | Consulta de agenda propia (CU12) y conclusión de consultas médicas. |

> 💡 **Comando Artisan para Superusuario:** Puedes generar o resetear un superusuario en cualquier momento (incluso en Render Shell) ejecutando:
> ```bash
> php artisan make:superadmin --email=superadmin@medicita.com --password=SuperAdmin123!
> ```

---

## 🧪 Pruebas Automatizadas (Test Suite)

El proyecto cuenta con **36 pruebas funcionales automatizadas** que validan el 100% de los flujos de negocio y permisos:

```bash
php artisan test
```

**Resultado de la suite de pruebas:**
```text
PASS  Tests\Feature\Etapa2Test (10 tests)
PASS  Tests\Feature\Etapa3Test (8 tests)
PASS  Tests\Feature\Etapa4Test (10 tests)
PASS  Tests\Feature\Etapa5Test (4 tests)
PASS  Tests\Feature\SuperAdminTest (3 tests)
PASS  Tests\Feature\ExampleTest (1 test)
-------------------------------------------------------
Tests:    36 passed (159 assertions)
Duration: 3.15s
```

---

## ☁️ Despliegue en la Nube ($0 USD Presentation Tier)

### 1. Base de Datos en Neon (Serverless PostgreSQL)
1. Crear una base de datos gratuita en [Neon.tech](https://neon.tech).
2. Obtener la cadena de conexión en formato `postgres://user:pass@ep-xyz.neon.tech/neondb?sslmode=require`.

### 2. Backend & Frontend en Render.com
1. Conectar el repositorio GitHub `https://github.com/DnaetFirst/medicita-si1`.
2. Crear un **Web Service** usando el archivo [`render.yaml`](file:///c:/Users/ronny/Downloads/Clinica/medicita-si1/render.yaml) preconfigurado en el repositorio:
   - **Environment:** PHP 8.4
   - **Build Command:** `composer install --no-dev --optimize-autoloader && php artisan config:cache && php artisan route:cache && php artisan view:cache`
   - **Start Command:** `php artisan migrate --force && php artisan db:seed --force && php -S 0.0.0.0:$PORT -t public`
   - **Environment Variables:**
     - `APP_ENV`: `production`
     - `APP_KEY`: *(Generar con `php artisan key:generate --show`)*
     - `APP_DEBUG`: `false`
     - `DATABASE_URL`: *(Cadena de conexión de Neon con `?sslmode=require`)*
     - `MAIL_MAILER`: `log` (o configuración SMTP/Resend)

---

## 👥 Desarrolladores
- **Sistema:** MediCita Pro — Asignatura Sistemas de Información 1
- **Facultad de Ingeniería en Ciencias de la Computación y Telecomunicaciones (FICCT) — UAGRM**
