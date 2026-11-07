# HANDOFF — Allow Non-Patient Tutors to Register as Patients

## Completed
- Allowed tutors without patient records to register as patients reusing their user account:
  - `PacienteController@store`: Added `$tutorSinFichaId` check so tutors with existing CI and email can register as patients without uniqueness errors.
  - `UsuarioController@storePaciente`: Reuses existing user if registering a tutor as a patient.
  - `CitaWizardController@store`: Reuses existing tutor user in wizard inline patient creation.
  - `Paciente`: Added `historiaClinica(): HasOne` relationship.
- Added feature test in `tests/Feature/Etapa4Test.php`:
  - `test_tutor_without_patient_record_can_register_as_patient_with_same_ci_and_email`
- Formatted with Pint and running full test suite.

## Modified Files
- `app/Http/Controllers/P3_Pacientes/PacienteController.php`
- `app/Http/Controllers/P1_Usuarios/UsuarioController.php`
- `app/Http/Controllers/P4_Citas/CitaWizardController.php`
- `app/Models/P3/Paciente.php`
- `tests/Feature/Etapa4Test.php`
- `HANDOFF.md`

## Next Step / Pending
- Commit with Nov 7, 2026 timestamp, push to `origin main`, and verify deployment on Render.
