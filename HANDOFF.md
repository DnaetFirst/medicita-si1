# HANDOFF — Auto-Inasistencia After 15m and Allow New Appointment After 30m

## Completed
- Implemented automatic inasistencia transition:
  - `CitaWorkflowService@procesarInasistenciasAutomaticas`: Transitions appointments in `programada` or `confirmada` to `inasistencia` when over 15 minutes past start time without attendance.
  - Scheduled in `routes/console.php` every minute.
  - Invoked dynamically in `CitaWorkflowService@validarCupo`, `CitaWizardController@create`, and `CitaController@index`.
- Updated active appointment blocking rule:
  - `CitaWorkflowService@validarCupo`: An appointment only blocks booking a new one if it is future or within 30 minutes of start time. Appointments older than 30 minutes or from previous days do not block.
  - `CitaWizardController@create`: Filtered `$paciente->citas` so past appointments (>30m) do not block or trigger the active appointment warning in wizard UI.
- Added feature tests in `tests/Feature/Etapa4Test.php`:
  - `test_unattended_appointment_automatically_transitions_to_inasistencia_after_15_minutes`
  - `test_past_appointment_older_than_30_minutes_does_not_block_booking_new_appointment`
- Ran Pint and running full test suite.

## Modified Files
- `app/Services/CitaWorkflowService.php`
- `app/Http/Controllers/P4_Citas/CitaWizardController.php`
- `app/Http/Controllers/P4_Citas/CitaController.php`
- `routes/console.php`
- `tests/Feature/Etapa4Test.php`
- `HANDOFF.md`

## Next Step / Pending
- Commit with Nov 7, 2026 timestamp, push to `origin main`, and verify deployment on Render.
