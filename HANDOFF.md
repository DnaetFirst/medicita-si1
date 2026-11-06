# HANDOFF — Review, AI repair and new-account deployment

## Completed
- Replaced regex-only SQL checks with bounded recursive-descent validation and nine allowlisted views.
- Gemini 3.1 Flash-Lite verified with live synthetic questions and real local read-only report execution.
- Fixed PHP TLS using a private CA bundle; keys use request headers, errors are sanitized and audited.
- Added clarification context, one regeneration attempt and encrypted result snapshots with owner-only CSV/print exports.
- Replaced browser SpeechRecognition with MediaRecorder + Gemini transcription; recording/stop, permission errors, silence detection and text review covered.
- Fixed lab draft leakage via order pages, guardian result permissions, wrong-order exams, duplicate drafts/publication and private PDF persistence.
- Fixed ongoing/completed KPIs, reschedule counts, CSV formula injection, waitlist acceptance checks/retries, expiry races and withdrawal holds.
- Prepared `medicita-si1-defensa` on Render free, existing Neon, automatic HTTPS URL, supervised scheduler and upload limits.
- Validation: 144 PHPUnit tests / 707 assertions; 4 Node recording/rendering tests; Vite build; Pint.
- Backed up local SQLite in ignored private storage and applied additive migrations locally.

## Modified Files
- AI/reporting services, controller, models, routes, report JS/views and regression tests.
- Lab/access/waitlist services/controllers, private PDF model and additive migrations.
- Dockerfile, php-runtime.ini, render-start.sh, render.yaml, .gitattributes, .env.example, CONTEXT.md.

## Pending
- User explicitly requests a NEW Render account (currently open in Brave), retaining the EXISTING Neon database.
- Old inferred URL medicita-si1.onrender.com responds Service Suspended; do not resume/reuse it as the target.
- New-account Render control and the actual Neon DATABASE_URL are unavailable locally. Integrations are not connected; browser control fails.
- Obtain these through connected Render/Neon or private local env configuration; never put secrets in chat/Git.
- Preserve the original production APP_KEY; deploy main, inspect build/runtime logs and verify HTTPS, DB, reports, audio and scheduler.
- No cloud deployment has been completed or verified yet. Microphone hardware and responsive browser inspection remain unverified.
