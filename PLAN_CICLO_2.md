# Cycle 2 Implementation Plan

Revision: November 6, 2026. Source: `SI1 - Citas Medicas -.pdf`, 164 pages. Page numbers refer to PDF viewer positions, counting the cover page as page 1 (document index contains minor offsets).

This plan contrasts the specification document with local codebase state, including uncommitted working tree changes. It performs a static architectural review supplemented by live route lists, without modifying data or running uninspected migrations.

---

## 1. Three-Stage Implementation

Cycle 2 comprises exactly **CU13**, **CU14**, **CU15**, **CU17**, and **CU18** (p. 28). CU16 (Notifications) and CU19 (Audit Log) are expanded to support Cycle 2 workflows; they do not become new standalone use cases.

Implementation is organized into exactly three sequential stages. Technical deliverables 0 through 7 (Section 5) serve as granular task breakdowns within these stages, with explicit acceptance criteria.

---

### Stage 1: System Foundation, Appointment History & Clinical Records

**Objective:** Correct appointment lifecycle semantics and complete CU13 and CU14 upon a cohesive permission and data foundation aligned with the PDF specification. Encompasses Technical Deliverables 0, 1, 2, and 3.

**Sequential Implementation:**
1. Resolve identified schema contradictions, finalize model relationships, and craft incremental migrations preserving legacy data.
2. Enforce fine-grained authorization per action and patient; enable `lab` role and authorized representation with explicit validity periods and separated permissions; decouple patient confirmation, reception arrival check-in, physician start of visit, and no-show marking.
3. Implement `HistorialCita`, same-appointment rescheduling, schedule blockades/postponements (`postergada`), centralized availability calculations, and resilient in-app notification dispatch with retry queues.
4. Complete **CU13**: Filterable appointment history, chronological change log, and role-scoped views.
5. Complete **CU14**: Single electronic medical record (`HistoriaClinica`), clinical consultation notes (`AtencionMedica`), versioned non-destructive corrections, and optional specialty derivations (`Derivacion`).
6. Update legacy test suites and build feature test coverage for access control, transactional integrity, and new state machine transitions.

**Stage 1 Responsive Design:** Adapt shared layouts and existing pages (login, dashboard, navigation, patients, physician agenda, and appointment views). Implement collapsible mobile sidebar with backdrop, single-column form layouts on small viewports, flexible filters, and readable clinical timelines. Preserve the Medical Blue visual identity, reusing native Blade/CSS components.

**Cloud Readiness:** Base cloud deployment on Render and PostgreSQL (Neon). Inventory actual cloud resources and billing tiers. Establish isolated staging environments, environment secret management, persistent private storage, and asynchronous background worker / cron scheduler execution. Prepare Docker build and frontend asset bundling. Do not assume versioned configuration represents active deployment.

**Verifiable Outcome:** Appointment history and clinical records functional across mobile, tablet, and desktop viewports; rescheduling preserves primary appointment identity (`id` and `codigo_ficha`); unauthorized actors are rejected with HTTP 403; migrations pass verification on backup databases.

---

### Stage 2: Laboratory & Slot Recovery / Waitlist

**Objective:** Complete **CU15** and **CU18**, integrated with Stage 1 clinical records, appointments, and notification pipelines. Encompasses Technical Deliverables 4 and 5.

**Sequential Implementation:**
1. Create laboratory test catalog (`ExamenLaboratorio`), laboratory orders (`OrdenLaboratorio`), and order items (`OrdenExamen`); link patient, physician, and consultation notes when applicable.
2. Implement laboratory result entry, private PDF document uploads, validation, versioned publications, partial vs. complete coverage tracking, and non-destructive corrections.
3. Secure private storage and authenticated streaming downloads; generate event-driven publication notices to authorized recipients without exposing confidential clinical text in external payloads.
4. Implement waitlist registrations (`ListaEspera`), scheduling preferences, FIFO candidate selection, exclusive 10-minute temporary offers (`OfertaCupo`), patient acceptance/rejection, withdrawal, and automated expiration.
5. Integrate slot recovery with appointment booking/rescheduling and physician agenda blocks: unaccepted or expired offers preserve previous appointments intact.
6. Activate background workers and cron scheduler for offer expiration; verify concurrency control, idempotency, and failure recovery on PostgreSQL.

**Stage 2 Responsive Design:** Laboratory and waitlist worklists with prioritized cards and touch-friendly actions ($\ge 44\text{ px}$); multi-parameter result entry forms without horizontal page blowout; mobile PDF preview/download fallback; legible offer cards displaying deadline timers. Distinct, tactile buttons for accept, reject, validate, and publish without hover reliance.

**Staging Environment:** Deploy Stages 1 and 2 to an isolated environment with synthetic clinical data, PostgreSQL, private storage, and queue/expiration background processes. Verify PDF persistence across container restarts, background offer expiration without open browser sessions, and reservation hold exclusivity.

**Verifiable Outcome:** Full order lifecycle (Order Issued $\rightarrow$ Draft Result $\rightarrow$ Validated $\rightarrow$ Published $\rightarrow$ Authorized Consultation); full recovery lifecycle (Slot Freed $\rightarrow$ Exclusive Offer $\rightarrow$ Acceptance $\rightarrow$ Atomic Assignment). Partial orders cannot be finalized prematurely; rejected/expired offers never cancel existing bookings.

---

### Stage 3: AI-Assisted Reports, Verification & Production Deployment

**Objective:** Complete **CU17** and bring the complete Cycle 2 into operational production. Encompasses Technical Deliverables 6 and 7.

**Sequential Implementation:**
1. Build catalog of traditional clinical and administrative indicators: visits, cancellations, no-shows, rescheduling, recovered slots, and postponements.
2. Implement reproducible, authorized PDF and CSV export generation.
3. Integrate conversational AI assistant supporting editable speech-to-text input, clarification prompts, and conversational filters. Implement strict AST/regex structural SQL validation restricted to a read-only database connection targeting authorized administrative views.
4. Benchmark AI-generated queries against reference SQL, verify permission boundaries, rate limits, provider fallback mechanisms, and traditional filter equivalents.
5. Execute end-to-end integration tests, PostgreSQL concurrency tests, RNF04 performance benchmarks, and cross-role responsive visual audits.
6. Execute production deployment and verification checklist.

**Stage 3 Responsive Design:** Adaptive layouts for conversation panes and analytical tables; responsive charts that scale without label truncation; accessible data tables and mobile exports. Review virtual keyboard behaviors and microphone permission UX. Exported PDFs must have dedicated print layouts rather than compressed viewport screenshots.

**Production Deployment Checklist:**
1. Verify target cloud infrastructure: web application service, PostgreSQL (Neon with TLS), persistent private object storage, background queue worker, and cron scheduler.
2. Refactor `Dockerfile`, `render.yaml`, and `render-start.sh` to support process roles (`web`, `release`, `worker`, `scheduler`). Decouple migrations and seeders so that production deployments never run destructive seeders or wipe live data.
3. Compile frontend assets with Vite and install production dependencies; ensure build artifacts are verified. Keep secrets outside Git and Docker images.
4. Set `APP_ENV=production`, `APP_DEBUG=false`, persistent `APP_KEY`, public HTTPS URL, TLS PostgreSQL connection, read-only analytical connection, private storage disk, and queue drivers. Standardize timezone on `America/La_Paz`.
5. Create verified database and storage backups; test restoration procedures; execute incremental migrations once per release.
6. Start worker and scheduler with monitored restart policies. Confirm periodic tasks run without duplicates and files persist across service restarts.
7. Verify HTTPS, `/up` health checks, role-based authorization, private downloads, email dispatch, waitlist offers, and analytical reports on the live environment.
8. Set up error monitoring without clinical data leakage, job failure alerts, daily database backups, and uptime tracking.
9. Establish rollback procedures: preserve previous Docker image/release and ensure schema backwards compatibility.
10. Deliver verified operational URL, release tags, audit verification logs, and pending items list.

---

### Mandatory Responsive Design Criteria Across All Stages

Responsive design is an acceptance criterion for every stage, not an afterthought:
- Mobile-first approach: Validate viewports at 360, 390, 768, 1024, and 1440 px, intermediate widths, and landscape orientation.
- Prevent horizontal page overflow: In dense tables, use responsive summary cards or scrollable containers preserving headers and action buttons.
- Accessible form controls: Visible labels, field-level error messages, minimum touch target height $\ge 44\text{ px}$, and 16 px input font size to prevent mobile browser auto-zoom.
- Viewport-bounded modals: Scrollable modal bodies constrained to `100dvh` so virtual keyboards do not obscure primary action buttons.
- State preservation: Maintain active filters and pagination across viewport changes. Form validation errors must preserve submitted inputs.

---

## 2. Diagram Review & Implementation Mapping

### Clinical Attention Process (p. 24)
Separates reception arrival check-in, physician consultation start, clinical note entry, optional derivation, optional lab test order, and visit completion. Laboratory proceeds asynchronously. Patient no-show (`inasistencia`) can only be marked after the 15-minute slot concludes, and only if clinic availability was maintained.
- *Implementation Rule:* Saving a clinical note must not complete the appointment. Issuing a derivation must not auto-book another visit. Issuing a lab order must not block consultation closure. A physician schedule suspension is never counted as patient absence.

### Use Cases & Cycle Scope (pp. 58–69)
- **CU13 (pp. 58–59):** Patient, receptionist, and physician query appointment history within their authorized scope. Independent use case; action links to reschedule, cancel, or view records re-verify permissions at target endpoints.
- **CU14 (pp. 60–61):** Physician records clinical care. Derivations are an optional extension under post-consultation conduct.
- **CU15 (pp. 62–63):** Physician issues order; laboratory records, validates, and publishes results; physician and patient access published results only.
- **CU17 (pp. 64–65):** Administrator and authorized receptionist query analytical reports. AI provider participates exclusively in the assisted query mode.
- **CU18 (pp. 66–67):** Patient or receptionist registers for waitlist. Exclusive offers and expirations run automatically. Acceptance updates previous appointment (if advancing) or books new slot.

### Communication Diagrams (pp. 93–97)
- **CU13 (p. 93):** `UI.Historial → HistorialController → Paciente/ResponsableAutorizado → Cita/HistorialCita → Bitacora`. Authorize before data retrieval; audit access without modifying agenda state.
- **CU14 (p. 94):** `UI.Historia → HistoriaController`. Separate scenarios for initial recording, corrections, and derivations. Coordinates `HistoriaClinica`, `AtencionMedica`, `Derivacion`, and `Especialidad`. Corrections create versioned append-only records.
- **CU15 (p. 95):** `UI.Laboratorio → LaboratorioController`. Five distinct scenarios: order emission, draft result capture, validation, publication, and authorized review.
- **CU17 (p. 96):** `UI.Reportes → ReporteController → AI Service`. AI proposes queries; backend validates AST and executes on read-only views; `ReporteIA` audits query metadata.
- **CU18 (p. 97):** `UI.Espera → EsperaController`. Atomic slot claim, offer consumption, and appointment history update in a single transaction.

### Domain Class Model (pp. 115–117)
1. **Clinical Records:** Exactly one `HistoriaClinica` per patient. Multiple `AtencionMedica` per history represent initial visits and versioned corrections. `Derivacion` belongs to a clinical note and targets a destination specialty.
2. **Laboratory:** An `OrdenLaboratorio` belongs to a patient and references physician and optional clinical note. Contains multiple `OrdenExamen`. `ResultadoLaboratorio` tracks authoring lab user, version, and publication status. `DetalleResultado` holds discrete parameters linked to tests.
3. **Waitlist & Reports:** `ListaEspera` belongs to a patient and optionally references an existing appointment. `OfertaCupo` links waitlist entry and slot with exact server expiration. `ReporteIA` records query execution and filters.

---

## 3. Verified Codebase Differences & Technical Gap Analysis

1. **Partial CU13 in Legacy Code:** Legacy `CitaController::misCitas()` only filtered by authenticated patient account. Lacked authorized guardian access, reception administrative queries, physician assisted-patient scope, and structured event history.
2. **Legacy Rescheduling Cancelled Appointments:** Legacy `CitaController::reprogramar()` created a new appointment row and marked previous row as cancelled. Cycle 2 requires preserving appointment identity (`id` and `codigo_ficha`) and logging slot changes to `HistorialCita`.
3. **Muddled Appointment States:** Legacy check-in jumped `programada` directly to `confirmada`, while visit start used `en_curso`. Corrected to explicit lifecycle: `programada` $\rightarrow$ `confirmada` (patient) $\rightarrow$ `en_espera` (reception arrival) $\rightarrow$ `en_atencion` (physician start) $\rightarrow$ `atendida` (physician finish).
4. **Incomplete Agenda Blocks:** Legacy `BloqueoController` deleted blocks upon lifting and lacked postponement logic. Refactored to transition affected visits to `postergada`, alert reception, and retain block records with `levantado_at`.
5. **Role & Clinical Segregation:** Legacy `CheckRole` allowed `superadmin` to bypass all gates. Refactored to restrict `superadmin` from private clinical notes. Added `lab` role. Added guardian account linking and split permissions (`puede_gestionar_citas`, `puede_ver_resultados`).
6. **Decoupled Resilient Notifications:** Legacy notification sent synchronous SMTP mail inside web requests. Refactored to transactional in-app notices (`Notificacion`) with unique `evento_id` and queued jobs (`EnviarAviso`) retried via scheduler every 5 minutes.
7. **Timezone Standardization:** Configured `America/La_Paz` in `config/app.php` with legacy UTC timestamp migration support.

---

## 4. Reconciliation of Document Contradictions

- **Publication Without Validation:** PDF example SQL published directly from Draft. Reconciled to strict 3-step state machine: `borrador` $\rightarrow$ `validado` $\rightarrow$ `publicado`.
- **Premature Order Completion:** PDF text completed orders upon any published result. Reconciled to requiring 100% test coverage before transitioning order to `finalizada`.
- **Notification Data Confidentiality:** External emails and notifications omit diagnoses, clinical notes, and sensitive reasons, directing users to the authenticated portal.
- **Clinical Note Corrections:** Added explicit `corrige_id`, `es_correccion`, and `motivo_correccion` to preserve original consultation notes while enabling versioned clinical corrections.

---

## 5. Technical Breakdown of the 3 Stages

### Deliverable 0: Data Contract & Foundation
- Establish unified vocabulary for states, permissions, and relationships.
- Map schema entities: `User`/`Rol`, `Medico`/`Especialidad`, `Slot`/`HorarioAtencion`, `HistorialCita`, and guardians.
- Incremental migrations protecting existing records against destructive rollbacks.

### Deliverable 1: Core Lifecycle & Workflow Engine
- Centralize state transitions in `CitaWorkflowService` with pessimistic locking (`lockForUpdate`), optimistic locking (`version`), and idempotency (`operacion_id`).
- Implement same-appointment rescheduling, preserving `id` and `codigo_ficha`.
- Postpone visits during agenda blocks and notify reception without cancelling.
- Decouple in-app notices and asynchronous queue jobs with scheduled retries.

### Deliverable 2: CU13 Appointment History
- `HistorialController` in P3 with multi-parameter filtering (patient, dates, specialty, doctor, status).
- Visual change log timeline showing state, slot, doctor changes, and author.
- Role-scoped authorization via `AccesoPacienteService`.

### Deliverable 3: CU14 Clinical Records & Derivations
- `HistoriaClinica` (single record per patient), `AtencionMedica` (append-only consultation notes and versioned corrections), and `Derivacion` (specialty referrals).
- Physician authorization enforcing active clinical relationship with patient.
- Integration with booking wizard to preselect specialty from active referrals.

### Deliverable 4: CU15 Laboratory
- Catalogs: `ExamenLaboratorio`, `OrdenLaboratorio`, `OrdenExamen`, `ResultadoLaboratorio`, `DetalleResultado`.
- Role `lab` worklist: Draft $\rightarrow$ Validated $\rightarrow$ Published.
- Complete coverage check before transitioning order to `finalizada`.
- Private storage for PDF reports with authenticated streaming routes.

### Deliverable 5: CU18 Waitlist & Slot Recovery
- Entities: `ListaEspera` and `OfertaCupo`.
- Recovery trigger: When a slot is freed, check waitlist before public release.
- Candidate selection: FIFO with ID tie-break, preference matching, and earlier-date verification for advance requests.
- Exclusive 10-minute offer hold; atomic acceptance; automatic expiration via scheduler.

### Deliverable 6: CU17 AI-Assisted Analytical Reports
- Catalog of clinical and operational indicators (occupancy, cancellations, no-shows, wait times).
- Export to structured CSV and styled PDF.
- AI assistant with editable speech-to-text; AST validation enforcing single `SELECT` on read-only views; zero clinical data exposure to LLM.

### Deliverable 7: Integration, Performance & Cloud Operation
- End-to-end user journeys for clinical, lab, recovery, and reporting workflows.
- RNF04 performance benchmark: 95% of availability and reservation queries under 3.0 seconds with 50 concurrent users.
- Production deployment on Render with separate `web`, `release`, `worker`, and `scheduler` roles, Neon PostgreSQL with TLS, and daily backups.

---

## 6. Migration & Testing Architecture

Schema rollout order:
1. Stage 1 Foundation: `roles`, `responsables_autorizados`, `citas` timestamps/version, `historial_citas`, `historias_clinicas`, `atenciones_medicas`, `derivaciones`, `notificaciones` fields, and conditional slot index.
2. Stage 2 Tables: `examenes_laboratorio`, `ordenes_laboratorio`, `ordenes_examenes`, `resultados_laboratorio`, `detalles_resultado`, `listas_espera`, `ofertas_cupo`, and `citas` recovery references.
3. Stage 3 Tables & Views: `reportes_ia` and analytical reporting views.

Test Suites:
- `CicloDosEtapaUnoTest`: Access control, appointment transitions, clinical notes, derivations, rescheduling identity, and notifications.
- `CicloDosMigracionTest`: Incremental migration and rollback safety tests.
- `CicloDosEtapaDosTest`: Laboratory order lifecycle, validation, publication, waitlist FIFO, 10-minute hold, atomic claim, and expiration.
- `CicloDosEtapaTresTest`: Indicator calculations, SQL AST validator, read-only permissions, and export reproducibility.
- PostgreSQL Integration Tests: Concurrency tests verifying row-level locks under simulated load.

---

## 7. Implementation Status & Delivery

All three sequential stages of **Cycle 2** have been completed, verified, and tested with **108 passing tests (550 assertions)**:
1. **Stage 1 (CU13, CU14):** Clinical history, versioned consultations (`AtencionMedica`), derivations, and audit trails.
2. **Stage 2 (CU15, CU18):** Laboratory order lifecycle (`emitida` → `en_proceso` → `finalizada`), versioned results (`borrador` → `validado` → `publicado`), private PDF streaming, and waitlist slot recovery with 10-minute hold.
3. **Stage 3 (CU17 & Cloud Ops):** Analytical KPIs, structured CSV and print PDF exports, conversational AI query assistant with strict AST SQL validator and voice recognition, read-only analytical views, and Render/Neon production deployment configuration.
