# MediCita Pro — Sistema de Gestión de Citas Médicas (Ciclo 1 & Ciclo 2)

> **Materia:** Sistemas de Información 1 (SI1) — UAGRM  
> **Repositorio Oficial:** `https://github.com/DnaetFirst/medicita-si1`  
> **Despliegue Cloud:** Render (Backend + Web Docker) + Neon Serverless PostgreSQL ($0 USD Presentation Tier)

---

## 🏥 Descripción del Proyecto

**MediCita Pro** es una plataforma integral de gestión clínica desarrollada bajo la arquitectura de **3 capas** y estructurada en **paquetes modulares (P1 a P7)** de acuerdo a las especificaciones formales del documento de requerimientos de la asignatura.

El sistema digitaliza todo el ciclo de atención ambulatoria:
- **Ciclo 1:** Autenticación con control de fuerza bruta (bloqueo tras 3 fallos), configuración de agendas médicas con turnos fijos de **15 minutos** y **1 hora obligatoria de descanso**, emisión asistida de fichas médicas en **4 pasos**, control de asistencia (check-in en recepción), reprogramación/cancelación de citas y bitácora de auditoría.
- **Ciclo 2:** Historial del paciente y cronología médica (CU13), historia clínica electrónica única con atenciones inmutables y derivaciones (CU14), módulo de análisis clínicos y laboratorio con resultados versionados (CU15), reportes analíticos con asistente de IA conversacional seguro y reconocimiento de voz (CU17), y lista de espera automatizada con recuperación de cupos mediante retención exclusiva de 10 minutos (CU18).

---

## 🏛️ Arquitectura del Sistema (3 Capas y Paquetes Modulares)

```
┌─────────────────────────────────────────────────────────────┐
│                 CAPA DE PRESENTACIÓN (UI/UX)                │
│   Blade Components · Vanilla CSS ("Medical Blue") · SVG    │
│   Diseño Responsivo · Web Speech API (Voz) · Vistas PDF     │
└──────────────────────────────┬──────────────────────────────┘
                               │ HTTP / JSON / AJAX
┌──────────────────────────────▼──────────────────────────────┐
│             CAPA DE LÓGICA DE NEGOCIO (Laravel PHP 8.4)     │
│   ├── P1_Usuarios       (CU1 Autenticación, CU2 Cuentas)   │
│   ├── P2_MedicosAgendas (CU3 Especialidades, CU4 Médicos,   │
│   │                      CU5 Horarios/Slots, CU6 Bloqueos,  │
│   │                      CU12 Consulta de Agenda)           │
│   ├── P3_Pacientes      (CU7 Pacientes, CU13 Historial,    │
│   │                      CU14 Historia Clínica Electrónica) │
│   ├── P4_Citas          (CU8 Wizard Ficha, CU9 Comprobante, │
│   │                      CU10 Check-in, CU11 Cancelación,   │
│   │                      CU18 Lista de Espera & Cupos)      │
│   ├── P5_Laboratorio    (CU15 Órdenes, Resultados y PDF)   │
│   ├── P6_ReportesIA     (CU17 KPIs, Validador AST SQL, Voz) │
│   └── P7_Notificaciones (CU16 Email Mails & Alertas, CU19)  │
└──────────────────────────────┬──────────────────────────────┘
                               │ Eloquent ORM / SQL Transaccional
┌──────────────────────────────▼──────────────────────────────┐
│                    CAPA DE DATOS (PostgreSQL / SQLite)      │
│   Neon Serverless PostgreSQL (Producción con TLS / Pooler)  │
│   18 Tablas Normalizadas · 4 Vistas Analíticas de Solo Lectura │
│   Bloqueo Pesimista (lockForUpdate) y Optimista (version)   │
└─────────────────────────────────────────────────────────────┘
```

---

## 📋 Casos de Uso Implementados (19 Casos de Uso Totales)

| Paquete | Caso de Uso | Ciclo | Descripción Técnica |
| :--- | :--- | :---: | :--- |
| **P1** | **CU1: Autenticación y Autorización** | Ciclo 1 | Login con protección contra fuerza bruta (**bloqueo de 15 min tras 3 intentos fallidos consecutivos**), hashing Bcrypt y middleware RBAC (`CheckRole`). |
| **P1** | **CU2: Cuentas, Roles y Permisos** | Ciclo 1 | CRUD de usuarios, asignación de roles (`admin`, `recepcionista`, `medico`, `paciente`, `lab`) y desactivación lógica. |
| **P2** | **CU3: Especialidades Médicas** | Ciclo 1 | Catálogo con código de color distintivo, descripción clínica y bloqueo de eliminación si tiene médicos activos asignados. |
| **P2** | **CU4: Gestión de Médicos** | Ciclo 1 | Registro de médicos vinculados a cuentas de usuario, especialidad clínica, matrícula profesional y biografía. |
| **P2** | **CU5: Horarios de Atención y Slots** | Ciclo 1 | Matriz semanal individualizada. Generación de **turnos de 15 minutos** con **reserva estricta de 1 hora de descanso** (`estado: descanso`). |
| **P2** | **CU6: Bloqueos de Agenda** | Ciclo 1 | Registro de bloqueos por feriados o emergencias. Inhabilita turnos a `bloqueado` y reubica citas a estado `postergada`. |
| **P3** | **CU7: Pacientes y Responsables** | Ciclo 1 | Ficha clínica de paciente y acreditación de **tutores legales con fecha de vigencia** y permisos de gestión médica. |
| **P4** | **CU8: Solicitud de Ficha Médica** | Ciclo 1 | **Wizard guiado en 4 pasos** (Especialidad ➔ Médico ➔ Turno 15m ➔ Confirmación) con bloqueo pesimista `lockForUpdate`. |
| **P4** | **CU9: Comprobante de Cita** | Ciclo 1 | Ficha médica digital e imprimible con código único (`FCH-YYYYMMDD-XXXX`), QR/token simulado e instrucciones de atención. |
| **P4** | **CU10: Control de Asistencia** | Ciclo 1 | Tablero de recepción con transiciones de estado: `programada` ➔ `confirmada` (Check-in) ➔ `en_atencion` ➔ `atendida` o `inasistencia`. |
| **P4** | **CU11: Cancelación y Reprogramación** | Ciclo 1 | Cancelación con motivo obligatorio y liberación inmediata del turno. Reprogramación atómica preservando la identidad de la cita. |
| **P2** | **CU12: Consulta de Agenda Médica** | Ciclo 1 | Visualizador de jornada diaria por médico con conteo de turnos en tiempo real y endpoint API JSON reactivo (`/api/agenda/slots`). |
| **P3** | **CU13: Historial y Cronología del Paciente** | Ciclo 2 | Línea de tiempo integral de citas del paciente o tutor legal autorizado, con filtros por especialidad y auditoría sin alterar agenda. |
| **P3** | **CU14: Historia Clínica Electrónica** | Ciclo 2 | Expediente clínico único por paciente, atenciones médicas inmutables y versionadas (append-only) y derivaciones inter-especialidades. |
| **P5** | **CU15: Laboratorio Clínico** | Ciclo 2 | Catálogo de exámenes, emisión de órdenes médicas, resultados versionados (`borrador` ➔ `validado` ➔ `publicado`) y descarga segura de PDFs privados. |
| **P7** | **CU16: Notificaciones por Correo** | Ciclo 1/2 | Emails y avisos en app (`CitaConfirmadaMail`, `AvisoCitaMail`, alertas de laboratorio) procesados mediante cola de trabajos. |
| **P6** | **CU17: Reportes Analíticos e IA** | Ciclo 2 | Indicadores operacionales (ocupación, cancelaciones, no-shows, cupos recuperados), exportación CSV/PDF y asistente conversacional con **Validador de Seguridad AST SQL** y **dictado por voz**. |
| **P4** | **CU18: Lista de Espera y Recuperación de Cupos** | Ciclo 2 | Selección FIFO preferente de pacientes ante cupos liberados, retención exclusiva de 10 minutos y reprogramación atómica de citas previas. |
| **P1** | **CU19: Bitácora de Auditoría** | Ciclo 1/2 | Registro inmutable de operaciones críticas (usuario, IP, módulo, acción, detalle JSON y timestamp) con exportación en CSV. |

---

## 🔑 Credenciales de Acceso para Demostración

| Rol | Correo Electrónico | Contraseña | Acceso y Permisos |
| :--- | :--- | :--- | :--- |
| **⚡ Superusuario** | `superadmin@medicita.com` | `SuperAdmin123!` | **Acceso Maestro Irrestricto a TODO el sistema**, módulos, auditoría, médicos, citas y configuración. |
| **Administrador** | `admin@medicita.com` | `Admin123!` | Gestión administrativa, usuarios, roles, reportes analíticos e indicadores con IA. |
| **Recepcionista** | `recepcion@medicita.com` | `Recepcion123!` | Emisión de fichas (CU8), check-in en recepción (CU10), lista de espera (CU18) y reportes. |
| **Médico** | `dr.perez@medicita.com` | `Medico123!` | Consulta de agenda propia (CU12), registro de consultas clínicas y derivaciones (CU14) y órdenes de laboratorio (CU15). |
| **Laboratorio** | `lab@medicita.com` | `Lab123!` | Captura de resultados analíticos, validación, publicación y carga de informes PDF (CU15). |
| **Paciente** | `paciente@medicita.com` | `Paciente123!` | Consulta de citas propias (CU13), resultados de laboratorio (CU15) e inscripción en lista de espera (CU18). |

---

## 🧪 Pruebas Automatizadas (Test Suite)

El proyecto cuenta con **109 pruebas funcionales automatizadas** pasando al **100%** (567 aserciones):

```bash
php artisan test
```

**Resultado de la suite de pruebas:**
```text
PASS  Tests\Feature\CicloDosEtapaUnoTest (10 tests)
PASS  Tests\Feature\CicloDosEtapaDosTest (10 tests)
PASS  Tests\Feature\CicloDosEtapaTresTest (10 tests)
PASS  Tests\Feature\CicloDosMigracionTest (2 tests)
PASS  Tests\Feature\Etapa2Test (10 tests)
PASS  Tests\Feature\Etapa3Test (8 tests)
PASS  Tests\Feature\Etapa4Test (10 tests)
PASS  Tests\Feature\Etapa5Test (4 tests)
PASS  Tests\Feature\SuperAdminTest (3 tests)
PASS  Tests\Feature\WorkflowRegressionTest (29 tests)
PASS  Tests\Feature\ExampleTest (1 test)
-------------------------------------------------------
Tests:    109 passed (567 assertions)
Duration: 15.74s
Result:   100% PASS
```

---

## ☁️ Despliegue en la Nube ($0 USD Presentation Tier)

### 1. Base de Datos en Neon (Serverless PostgreSQL)
1. Crear una base de datos gratuita en [Neon.tech](https://neon.tech).
2. Obtener la cadena de conexión en formato `postgres://user:pass@ep-xyz.neon.tech/neondb?sslmode=require`.

### 2. Backend & Frontend en Render.com
1. Conectar el repositorio GitHub `https://github.com/DnaetFirst/medicita-si1`.
2. Crear un **Web Service** usando el archivo [`render.yaml`](./render.yaml) preconfigurado en el repositorio:
   - **Environment:** Docker
   - **Start Command:** Gestionado automáticamente por `render-start.sh` (con soporte para roles `web`, `release`, `worker` y `scheduler`).
   - **Environment Variables:**
     - `APP_ENV`: `production`
     - `APP_KEY`: *(Generar con `php artisan key:generate --show`)*
     - `APP_DEBUG`: `false`
     - `APP_TIMEZONE`: `America/La_Paz`
     - `DATABASE_URL`: *(Cadena de conexión de Neon con `?sslmode=require`)*
     - `DB_SSLMODE`: `require`
     - `MAIL_MAILER`: `log` (o configuración SMTP/Resend)

*(Nota: El comando `php artisan db:seed --force` debe ejecutarse solo una vez en el despliegue inicial mediante la consola/shell de Render para garantizar idempotencia y evitar duplicidad de registros)*.

---

## 👥 Desarrolladores
- **Sistema:** MediCita Pro — Asignatura Sistemas de Información 1
- **Facultad de Ingeniería en Ciencias de la Computación y Telecomunicaciones (FICCT) — UAGRM**
