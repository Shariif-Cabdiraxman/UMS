# Campus Manager — Code Review & Issues

*Originally generated from commit `651476e`; status notes added after the
security hardening pass.*

> **Status:** The demo-credential and default-demo-mode findings (item 2) and
> the web-root exposure of `.git`, `database.sql`, `config/`, `includes/`, and
> `tools/` are now fixed. Item 1 (session-only login throttling) remains open;
> item 3 (HSTS/COOP/CORP) is deferred. Items 5 and 6 were reviewed and are not
> vulnerabilities as written.

---

## 🔴 Critical Security Issues

### 1. **No Server-Side Rate Limiting on Login** — OPEN (`includes/auth.php:237-313`)
**Severity:** High (deferred)
- Login brute-force protection only uses session-based tracking (`$_SESSION['login_failures']`)
- Attacker can clear cookies or use incognito to bypass
- **Fix:** Implement IP-based rate limiting with Redis/memory store, or at minimum log failed attempts to DB with IP

### 2. **Demo Credentials in Production Database** — FIXED
**Original severity:** Critical
- `database.sql` now seeds unusable password hashes; the first password is set
  with `php tools/set_password.php <username> <password>`.
- `APP_DEMO_MODE` now defaults to `false`; the login page shows demo accounts
  only when demo mode is explicitly enabled.
- The `"0"`/`"false"` environment values now reliably disable demo mode.
- Demo passwords are restored only by the explicit
  `php tools/set_password.php --demo` command.

### 3. **Missing HTTP Security Headers in Production** — DEFERRED (`includes/init.php:32-48`)
**Severity:** Medium (HTTPS/HSTS only matters on a public deployment)
- CSP allows `'unsafe-inline'` for styles (needed for chart bars)
- No `Strict-Transport-Security` (HSTS) header
- No `Cross-Origin-Opener-Policy` or `Cross-Origin-Resource-Policy`
- **Fix:** Add HSTS when HTTPS detected; consider splitting CSP for chart endpoints

---

## 🟠 High Priority Issues

### 4. **No CI/CD Pipeline** (`.github/workflows/ci.yml` — newly added but untested)
**Severity:** High
- GitHub Actions workflow added but MySQL service may not start reliably in CI
- Smoke tests are PowerShell-based and won't run on Ubuntu runners
- **Fix:** Port smoke tests to bash/Node.js; use `act` for local testing; add `composer` if dependencies grow

### 5. **Session Fixation Risk on Login** — NOT A VULNERABILITY
**Original severity:** High
- `session_regenerate_id(true)` is called on login and deletes the old session
  file; the `true` argument is the protection.
- Calling `session_destroy()` first would remove the current data and is not
  required here.
- **Action:** No change.

### 6. **SQL Identifier Sanitization Incomplete** — ACCEPTED DESIGN
**Original severity:** High
- Table and column names in `unique:`/`exists:` rules are written in code, not
  taken from a request, and `safe_identifier()` is a second line of defence.
- The app never needs `db.table` syntax, so rejecting `.` is intentional.
- **Action:** No change.

### 7. **No Audit Logging for Data Changes** (All CRUD operations)
**Severity:** High
- Grades, enrollments, students can be modified with no audit trail
- Only `created_at`/`updated_at` timestamps exist
- **Fix:** Add `audit_log` table; log user, action, table, pk, before/after JSON

---

## 🟡 Medium Priority Issues

### 8. **Password Reset / Email Verification Missing** (`auth/`)
**Severity:** Medium
- No "forgot password" flow
- No email verification on registration (though registration is admin-only)
- **Fix:** Add password reset tokens table + email sending abstraction

### 9. **Soft Deletes Not Implemented** (`database.sql`, `includes/crud.php`)
**Severity:** Medium
- `DELETE` removes rows permanently
- No "trash" / restore for accidental deletes
- **Fix:** Add `deleted_at` column to all tables; filter with `WHERE deleted_at IS NULL`

### 10. **No API Layer** (Entire application is server-rendered PHP)
**Severity:** Medium
- Mobile PWA shell loads same HTML — no JSON API for offline sync
- Cannot build native mobile apps or third-party integrations
- **Fix:** Add REST API endpoints returning JSON; use same auth (session or token)

### 11. **File Upload / Document Storage Missing** (All modules)
**Severity:** Medium
- No way to attach transcripts, ID scans, course syllabi
- **Fix:** Add `documents` table + S3/local storage abstraction

### 12. **Database Connection Not Pooled** (`includes/db.php`)
**Severity:** Medium
- New mysqli connection per request (lazy connect)
- No persistent connections or pooling
- **Fix:** Use `mysqli::persistent` or PHP-FPM connection pooling

---

## 🟢 Low Priority / Enhancements

### 13. **TypeScript/Static Analysis for PHP** (Entire codebase)
- Add `phpstan`/`psalm` to CI for type safety
- Add `@psalm-suppress` for intentional dynamic patterns

### 14. **Docker Support** (Missing)
- No `Dockerfile` or `docker-compose.yml`
- Hard to deploy consistently
- **Fix:** Add multi-stage Dockerfile (PHP-FPM + Nginx + MySQL)

### 15. **Accessibility Audit Needed** (All HTML output)
- ARIA labels present but not audited
- Color contrast in grade badges relies on CSS variables
- **Fix:** Run `axe-core` in CI; test with screen readers

### 16. **Internationalization (i18n) Not Started** (All user-facing strings)
- Hardcoded English in PHP and SQL `ENUM` values
- **Fix:** Extract strings to `.po`/`.json` files; use `gettext` or custom i18n

---

## 📋 Suggested Issues to Create (copy-paste to GitHub)

```markdown
# Issue 1: Implement IP-based login rate limiting
# Issue 2: Remove demo credentials from production seed
# Issue 3: Add HSTS and strengthen CSP headers
# Issue 4: Port smoke tests to cross-platform (bash/Node)
# Issue 5: Add audit logging for all data mutations
# Issue 6: Implement soft deletes with restore
# Issue 7: Add REST API layer for mobile/offline sync
# Issue 8: Add Docker support for deployment
# Issue 9: Set up PHPStan/Psalm in CI
# Issue 10: Add password reset flow
```

---

## ✅ Strengths Noted

- **Excellent SQL injection prevention** — all queries use prepared statements with bound params
- **CSRF protection on every state-changing form** — `verify_csrf()` called consistently
- **Timing-attack resistant login** — `password_verify` runs even for non-existent users
- **Role-based authorization** — server-side `require_permission()` on every write
- **Guarded deletes with human-readable errors** — FK violations caught and explained
- **Comprehensive test suite** — 218 smoke tests (86 desktop + 132 mobile)
- **Mobile-first responsive design** with PWA support
- **Zero dependencies** — no Composer, no npm, just PHP + MySQL
- **Clean architecture** — modules follow consistent 3-file pattern