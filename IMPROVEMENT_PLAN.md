## Call Scheduler – Improvement Plan

### 1) Security & Privacy (highest)
- Enforce authenticated REST writes: require `X-WP-Nonce` on `cs/v1/bookings` (or signed HMAC token) and reject missing/invalid tokens; keep honeypot as a secondary check.
- Tighten CORS scope: only send CORS headers for `cs/v1/*` routes and only to `CS_ALLOWED_ORIGINS`; handle `OPTIONS` early and avoid affecting other WP REST routes.
- Remove or anonymize IP logging in `RateLimiter`; wrap any diagnostics behind `WP_DEBUG` and hash IPs if needed.
- Rate-limit harder for POST bookings (e.g., 5/min per IP/user) and consider short-term IP+user burst limits; add CAPTCHA or second honeypot if abuse persists.
- Validate availability reads: reject `user_id` that is not a team member; cache responses briefly to reduce DB load.
- Sanitize outbound email data defensively (already sanitized on input) and ensure no sensitive data is logged in hooks.

### 2) Architecture & Maintainability
- Introduce a lightweight service container/provider map in `Plugin::boot` to wire shared dependencies (`Cache`, `Email`, `Config`, repositories) and inject into controllers/services instead of constructing inline.
- Extract shared REST concerns (nonce check, rate-limit wrapper, JSON error helpers) into a base REST helper; reuse across controllers.
- Centralize configuration (CORS origins, rate limits, slot/buffer defaults) in `Config` with clear overrides and validation; add a single `config()` helper.
- Separate read vs write controllers and keep DTO/response mappers for lean, testable endpoints.

### 3) Testing & Tooling
- Add REST integration tests for: nonce-required POST bookings, rate-limit headers, CORS scoping, and availability access control.
- Add unit tests for `RateLimiter` edge cases (lock contention) and `Config` validation (slot duration/buffer).
- Run `phpunit` in CI; add a GitHub Actions workflow or equivalent CI step.
- Add a security smoke test script hitting REST endpoints with/without nonce to prevent regressions.

### 4) Admin UX Hardening
- Show a warning in admin if `CS_ALLOWED_ORIGINS` is too broad or missing when external origin use is expected.
- Add an admin toggle to flush plugin caches (`Cache::flush`) and an indicator when DB tables are missing.
- Confirm bulk actions with nonce already present; ensure all admin POSTs bail early if nonce missing/invalid (already mostly covered).

### 5) Performance & Reliability
- Cache team members and availability per user with short TTL; invalidate on profile save and availability update (partial already exists—extend to REST reads).
- Consider DB indexes on `cs_bookings` for `(user_id, booking_date, booking_time, status)` to speed availability lookups; verify current schema covers this.
- Use prepared statements consistently (already using `$wpdb->prepare`); add LIMITs where appropriate for listing endpoints.

### 6) Rollout Plan
- Phase 1 (security hotfix): CORS scoping, nonce on POST bookings, rate-limiter log removal/anonymization, team-member check on availability.
- Phase 2 (architecture): service container, REST helpers, config centralization.
- Phase 3 (tests/tooling): add REST and unit tests, wire CI.
- Phase 4 (UX/perf): admin warnings, cache/DB tuning, optional CAPTCHA toggle.

