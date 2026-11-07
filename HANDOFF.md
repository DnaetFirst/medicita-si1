# HANDOFF — Hide CU18 (Waitlist) from Doctor Role

## Completed
- Hidden CU18 (Lista de Espera) navigation from the Doctor (`medico`) role in `resources/views/components/sidebar.blade.php`:
  - `Lista de Espera (CU18)` under `P4 · Citas Médicas` is now restricted to Admin and Recepcionista (`@if(auth()->user()->isAdmin() || auth()->user()->isRecepcionista())`).
  - Cleaned duplicate `Lista de Espera (CU18)` link in the Patient section.
  - Verified route-level security: routes remain restricted to `['role:admin,recepcionista,paciente']` (403 for doctors).
- Added regression test in `tests/Feature/CicloDosEtapaDosTest.php`:
  - `test_doctor_cannot_view_or_access_waitlist_cu18`: confirms HTTP 403 when accessing `/espera` and `/espera/inscribir`, and asserts absence of "Lista de Espera (CU18)" on physician navigation while visible for receptionists and patients.
- Cleaned accidental duplicate test method in `tests/Feature/Etapa4Test.php`.
- Full test suite passing: 162/162 tests green (831 assertions).
- Laravel Pint formatting checked and clean.

## Modified Files
- `resources/views/components/sidebar.blade.php`
- `tests/Feature/CicloDosEtapaDosTest.php`
- `tests/Feature/Etapa4Test.php`
- `HANDOFF.md`

## Next Step / Pending
- Ready for commit (with Nov 7, 2026 timestamp), push to `origin main`, and verify deployment on Render.
