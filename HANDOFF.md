# HANDOFF — Prevent Creating or Assigning Superadmin Role in CU2

## Completed
- Excluded `superadmin` role from creation and promotion in `UsuarioController`:
  - `create()`: Filtered role dropdown to exclude `slug === 'superadmin'`.
  - `store()`: Added validation rule preventing assignment of `superadmin` and safeguard `abort(422)`.
  - `edit()`: Excluded `superadmin` from the role dropdown unless the user being edited is already a superadmin.
  - `update()`: Prevented promoting non-superadmin users to `superadmin`.
- Added test in `tests/Feature/Etapa2Test.php`:
  - `test_cannot_create_or_assign_superadmin_role`: Asserts superadmin is not in `/usuarios/create`, rejects POST, and rejects PUT promotion.
- Ran Laravel Pint (0 violations).
- Verified full test suite.

## Modified Files
- `app/Http/Controllers/P1_Usuarios/UsuarioController.php`
- `tests/Feature/Etapa2Test.php`
- `HANDOFF.md`

## Next Step / Pending
- Commit with Nov 7, 2026 timestamp, push to `origin main`, and verify deployment on Render.
