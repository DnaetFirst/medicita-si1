# CONTEXT.md — MediCita Pro Architecture & System Context

## 1. System Summary
MediCita Pro is a clinic management platform facilitating medical appointment booking, doctor agendas, electronic clinical records, laboratory diagnostic workflows, and AI analytical reporting.
It orchestrates the visit lifecycle: 15-minute slot reservations, patient arrival check-in, physician consultation, versioned notes, derivations, waitlist recovery, lab results, and operational KPIs.
It enforces strict data isolation across patients, legal guardians, receptionists, physicians, laboratory staff, and system administrators.

## 2. Core Modules
- **P1 Security & Audit (CU1, CU2, CU19):** Session auth (email or CI), initial password CI with `debe_cambiar_password` prompt, lockout after 3 failed attempts, custom RBAC, and immutable audit logs (`bitacoras`).
- **P2 Doctors & Agendas (CU3, CU4, CU5, CU6, CU12):** Medical specialties, physician profiles, weekly schedules with 1h breaks, 15-min slots, and agenda blocks (`postergada` state).
- **P3 Patients & Clinical History (CU7, CU13, CU14):** Patient & guardian management, appointment change log (`HistorialCita`), single clinical record (`HistoriaClinica`), append-only consultations (`AtencionMedica`), and medical derivations. Every patient has an active `User` account with role `paciente`. Revocation of legal guardianship preserves patient history and transitions ownership seamlessly.
- **P4 Appointments, Slips & Waitlist (CU8, CU9, CU10, CU11, CU18):** 4-step booking wizard with searchable patient combobox, printable slips (`codigo_ficha`), single active appointment policy per patient, reception dashboard, same-appointment rescheduling, and waitlist slot recovery (`ListaEspera`, `OfertaCupo` with 10-min hold).
- **P5 Clinical Laboratory (CU15):** Lab test catalog, medical orders (`OrdenLaboratorio`), order test items (`OrdenExamen`), and versioned results (`ResultadoLaboratorio`, `DetalleResultado`) with encrypted PDF copies in PostgreSQL (survive ephemeral Render disk loss), publication-only patient/guardian access and atomic lab transitions.
- **P6 Business Intelligence & AI Reports (CU17):** Clinical & operational KPIs (attendance, cancellations, no-shows, waitlist recovered slots, slot occupancy), structured CSV/PDF exports, and Gemini 3.1 Flash-Lite SQL generation with recursive-descent validation over nine allowlisted analytical views, bounded results, clarification context, encrypted snapshots and owner-only CSV/print exports. MediaRecorder audio is transcribed server-side by Gemini; raw audio is not persisted.
- **P7 Notifications (CU16):** Queued emails and in-app notices (`Notificacion`) without sensitive medical text, retried via scheduler every 5 minutes.

## 3. Database Schema Summary
- **User:** `belongsTo(Rol)`, `hasOne(Medico)`, `hasOne(Paciente)`, `hasMany(Notificacion)`, `hasMany(ReporteIa)`.
- **Rol:** `hasMany(User)`.
- **Especialidad:** `hasMany(Medico)`, `hasMany(Derivacion)`.
- **Medico:** `belongsTo(User)`, `belongsTo(Especialidad)`, `hasMany(HorarioAtencion)`, `hasMany(Slot)`, `hasMany(BloqueoAgenda)`, `hasMany(Cita)`.
- **HorarioAtencion:** `belongsTo(Medico)`, `hasMany(Slot)`.
- **Slot:** `belongsTo(Medico)`, `belongsTo(HorarioAtencion)`, `hasOne(Cita)`.
- **BloqueoAgenda:** `belongsTo(Medico)`, `belongsTo(registradoPor: User)`.
- **Paciente:** `belongsTo(User)`, `hasMany(ResponsableAutorizado)`, `hasOne(HistoriaClinica)`, `hasMany(Cita)`, `hasMany(OrdenLaboratorio)`, `hasMany(ListaEspera)`.
- **ResponsableAutorizado:** `belongsTo(Paciente)`, `belongsTo(User)`.
- **Cita:** `belongsTo(Paciente)`, `belongsTo(Medico)`, `belongsTo(Slot)`, `belongsTo(Derivacion)`, `belongsTo(OfertaCupo)`, `hasMany(HistorialCita)`, `hasMany(Notificacion)`.
- **HistorialCita:** `belongsTo(Cita)`, `belongsTo(autor: User)`.
- **HistoriaClinica:** `belongsTo(Paciente)`, `hasMany(AtencionMedica)`.
- **AtencionMedica:** `belongsTo(HistoriaClinica)`, `belongsTo(Cita)`, `belongsTo(autor: User)`, `belongsTo(original: AtencionMedica)`, `hasMany(Derivacion)`, `hasMany(OrdenLaboratorio)`.
- **Derivacion:** `belongsTo(AtencionMedica)`, `belongsTo(Especialidad)`, `belongsTo(autor: User)`, `hasOne(Cita)`.
- **ExamenLaboratorio:** `hasMany(OrdenExamen)`, `hasMany(DetalleResultado)`.
- **OrdenLaboratorio:** `belongsTo(Paciente)`, `belongsTo(Medico)`, `belongsTo(AtencionMedica)`, `hasMany(OrdenExamen)`, `hasMany(ResultadoLaboratorio)`.
- **OrdenExamen:** `belongsTo(OrdenLaboratorio)`, `belongsTo(ExamenLaboratorio)`.
- **ResultadoLaboratorio:** `belongsTo(OrdenLaboratorio)`, `belongsTo(bioquimico: User)`, `belongsTo(corrige: ResultadoLaboratorio)`, `hasMany(DetalleResultado)`.
- **DetalleResultado:** `belongsTo(ResultadoLaboratorio)`, `belongsTo(ExamenLaboratorio)`.
- **ListaEspera:** `belongsTo(Paciente)`, `belongsTo(Medico)`, `belongsTo(Especialidad)`, `belongsTo(citaAvanzar: Cita)`, `hasMany(OfertaCupo)`.
- **OfertaCupo:** `belongsTo(ListaEspera)`, `belongsTo(Slot)`, `hasOne(Cita)`.
- **ReporteIa:** `belongsTo(User)`.
- **Notificacion:** `belongsTo(User)`, `belongsTo(Cita)`.
- **Bitacora:** `belongsTo(User)`.

## 4. Key Routes & Auth
- **Authentication:** Custom session auth (`/login`, `/logout`). Passwords hashed with bcrypt; lockout after 3 failed login attempts.
- **RBAC Middleware:** `CheckRole` validates slugs (`admin`, `recepcionista`, `medico`, `paciente`, `lab`).
- **Route Prefixes & Endpoints:**
  - Administrative (`role:admin`): `/usuarios`, `/bitacora` (CSV export).
  - Clinic Management (`role:admin,recepcionista`): `/especialidades`, `/medicos`, `/horarios`, `/pacientes`.
  - Agendas & Blocks (`role:admin,medico`): `/bloqueos`, `/agenda`.
  - Clinical Operations (`role:admin,recepcionista,medico`): `/citas`, `/citas/{cita}/checkin`, `/citas/{cita}/iniciar`, `/citas/{cita}/finalizar`, `/citas/{cita}/inasistencia`.
  - Patient Booking & Waitlist (`role:admin,recepcionista,paciente`): `/citas/nueva`, `POST /citas`, `/citas/{cita}`, `/citas/{cita}/reprogramar`, `/citas/{cita}/cancelar`, `/citas/{cita}/confirmar`, `/espera`, `POST /espera`, `/espera/ofertas/{oferta}`, `POST /espera/ofertas/{oferta}/aceptar`.
  - Clinical History (`AccesoPacienteService` restricted): `/historial`, `/historia/{paciente}`, `POST /historia/{paciente}/atenciones`, `POST /atenciones/{atencion}/derivaciones`.
  - Laboratory (`role:admin,lab,medico,paciente`): `/laboratorio/ordenes`, `POST /laboratorio/ordenes`, `/laboratorio/ordenes/{orden}`, `/laboratorio/ordenes/{orden}/resultados/crear`, `POST /laboratorio/resultados`, `/laboratorio/resultados/{resultado}`, `/laboratorio/resultados/{resultado}/pdf`, `/laboratorio/mis-resultados`.
  - Business Intelligence & AI (`role:admin,recepcionista`): `/reportes`, `/reportes/exportar/csv`, `/reportes/exportar/pdf`, `POST /reportes/ia/consultar`.
  - Notifications: `/avisos`, `POST /avisos/{aviso}/leer`.
- **Key Domain Services:** `CitaWorkflowService`, `AccesoPacienteService` (enforces clinical privacy), `SlotGeneratorService`, `RecuperacionCupoService`, `ReporteIndicadoresService`, `AiReporteService`, `NotificacionService`, `BitacoraService`.
- **Testing Suite:** Feature tests organized by stages (`Etapa2Test` to `Etapa5Test`, `SuperAdminTest`, `CicloDosEtapaUnoTest`, `CicloDosEtapaDosTest`, `CicloDosEtapaTresTest`, `CicloDosMigracionTest`).

## Runtime and Deployment Review (2026-11-07)
- New Render target: `medicita-si1-defensa`, `main`, Docker, free web instance; reuse the existing Neon DATABASE_URL and persistent APP_KEY.
- `APP_URL` falls back to Render's RENDER_EXTERNAL_URL; PostgreSQL uses TLS. No automatic demo seeding.
- The web entrypoint supervises the minute scheduler. QUEUE_CONNECTION=sync avoids a paid worker for the defense demo.
- Free Render instances can sleep; background tasks run while the instance is awake. Acceptance always rechecks expiry.
- A partial unique index enforces one pending offer per slot; acceptance revalidates shared availability and retries return the same visit.
- Reports include movements, waitlists, offers, patient registrations and lab order states; clinical observations/results remain excluded.
- Local PHP CA trust is configured through ignored `.env` GEMINI_CA_BUNDLE; production uses its normal CA trust.
- Browser UI automation currently fails while reading/controlling the open Render tab. Render/Neon integrations are not connected.
- The real Neon connection URI is absent locally; README contains a placeholder. Cloud provisioning requires that secret and new-account access.
