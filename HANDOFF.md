# HANDOFF — Single Active Ficha Policy, Wizard Patient Form & Password Text Simplification

## Completed
- Enforced single active ficha rule in `CitaWorkflowService::validarCupo` preventing patients with an active appointment (`programada`, `confirmada`, `en_espera`, `en_atencion`) from booking another appointment, returning a 422 with a detailed message.
- Rescheduling, waitlist slot acceptance, and derived appointments correctly exempt the active ficha check.
- Added visual blocking banner and disabled submit button in `/citas/nueva` when the selected patient already has an active appointment.
- Updated searchable combobox in appointment wizard with live badges (`⛔ Ficha Activa`) and instant validation.
- Simplified password helper text to "Mayúscula, minúscula, número y símbolo." across all auth/user/doctor views.
- Added feature tests in `tests/Feature/Etapa4Test.php` covering active appointment prevention and post-attendance booking.
- Verified test suite (151 tests passed, 745 assertions) and Pint styling.

## Modified Files
- `app/Services/CitaWorkflowService.php`, `app/Http/Controllers/P4_Citas/CitaWizardController.php`
- `resources/views/p4_citas/citas/wizard.blade.php`, `resources/views/layouts/app.blade.php`
- `resources/views/p1_auth/usuarios/create.blade.php`, `resources/views/p1_auth/usuarios/edit.blade.php`, `resources/views/p2_medicos/medicos/create.blade.php`
- `tests/Feature/Etapa4Test.php`, `CONTEXT.md`, `HANDOFF.md`

## Next Step / Pending
- Commit and push to GitHub `main` for automated Render live deployment (`medicita-si1-defensa`).
- Verify the active ficha prevention live on the production site.
