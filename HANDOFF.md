# HANDOFF — Automatic Derivation Preselection and Alert in Booking Wizard

## Completed
- Updated `CitaWizardController@create` to auto-detect pending medical derivations for patients (`estado = 'emitida'`) without requiring explicit URL parameters.
- Preselects the derived specialty automatically in Step 1 and loads its specialists.
- Added visual derivation alert banner in `resources/views/p4_citas/citas/wizard.blade.php` displaying attending physician, derived specialty, clinical reasons, and instructions.
- Integrated derivation badge and automatic specialty syncing for receptionists when searching/selecting patients in the combobox.
- Added test in `tests/Feature/CicloDosEtapaUnoTest.php`. All 25 stage 1 tests and suite pass.

## Modified Files
- `app/Http/Controllers/P4_Citas/CitaWizardController.php`
- `resources/views/p4_citas/citas/wizard.blade.php`
- `tests/Feature/CicloDosEtapaUnoTest.php`
- `HANDOFF.md`

## Next Step / Pending
- Ready for user review and live demo.
