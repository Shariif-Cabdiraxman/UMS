# Campus Manager

[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)](https://php.net)
[![Database](https://img.shields.io/badge/Database-MySQL%20%7C%20MariaDB-4479A1?logo=mysql&logoColor=white)](https://mariadb.org)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Tests](https://img.shields.io/badge/Tests-218%20passing-brightgreen)](tools/)
[![Architecture](https://img.shields.io/badge/Architecture-Modular%20MVC-blueviolet)](#architecture)
[![Mobile](https://img.shields.io/badge/Mobile-Responsive%20%2B%20PWA-orange)](#on-a-phone)
[![Security](https://img.shields.io/badge/Security-Hardened%20baseline-red)](#security)

---

A **student records system for a small university**, written in **plain PHP** with **no framework** and **no build step**.

It covers the academic structure of the institution (faculties, departments, staff, courses), the people in it (lecturers and students), and what they do (enrolments, grades, announcements) behind a **role-based sign-in**.

> **Nine modules** — each with a searchable, sortable, paginated list, a create/edit form, a detail page, and a **guarded delete**. Foreign keys are enforced in the database, and a delete that would orphan records is refused with a sentence naming what is in the way rather than a cryptic constraint-violation error.

---

## Quick Start

```bash
# 1. Clone
git clone https://github.com/Shariif-Cabdiraxman/Campus-Manager.git
cd Campus-Manager

# 2. Import schema + seed data
mysql -u root < database.sql

# 3. Configure (copy template, edit credentials)
cp config/database.example.php config/database.php

# 4. Set the first administrator password (no usable password is seeded)
php tools/set_password.php admin 'choose-a-strong-password'

# 5. Open in browser
# http://localhost/Campus-Manager/
```

For a local demonstration only, run `php tools/set_password.php --demo` after
the import, then enable demo mode as described below. The demo passwords are
public and must never be used on a real installation.

---

## Features at a Glance

| Capability | Details |
|------------|---------|
| **Academic Structure** | Faculties → Departments → Courses → Lecturers |
| **People Management** | Students (department-scoped), Lecturers, Users |
| **Academic Operations** | Enrolments (term/year), Grades (auto-computed letters), Announcements |
| **Role-Based Access** | `admin` (full) / `registrar` (academic ops only) |
| **Data Integrity** | FK enforcement, guarded deletes with human-readable errors |
| **Mobile-First** | Responsive desktop + dedicated phone shell + PWA installable |
| **Zero-Dependency** | No Composer, no npm, no build — just PHP + MySQL |
| **Tested** | 218 automated smoke checks (86 desktop + 132 mobile) |

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      Browser                                 │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐  │
│  │  Desktop    │  │   Mobile    │  │  PWA (sw.js)        │  │
│  │  Shell      │  │  Shell      │  │  Offline page only  │  │
│  └──────┬──────┘  └──────┬──────┘  └──────────┬──────────┘  │
└─────────┼────────────────┼────────────────────┼─────────────┘
          │                │                    │
          ▼                ▼                    ▼
┌─────────────────────────────────────────────────────────────┐
│                    index.php (Front Controller)              │
│  → auth/login.php  → dashboard.php  → module/*/index.php    │
└─────────────────────────────────────────────────────────────┘
          │
          ▼
┌─────────────────────────────────────────────────────────────┐
│                    includes/ (Shared Layer)                  │
│  init.php  db.php  auth.php  validation.php  list_query.php  │
│  crud.php  helpers.php  charts.php  mobile.php  layout.php   │
└─────────────────────────────────────────────────────────────┘
          │
          ▼
┌─────────────────────────────────────────────────────────────┐
│                    MySQL / MariaDB                           │
│  university_management  •  9 tables  •  FK constraints       │
└─────────────────────────────────────────────────────────────┘
```

**Module Convention** — every module has the same three files:
- `index.php` — permission check, search/filter/sort, one prepared query, shared renderers
- `form.php` — `validate()` against rule map → insert/update with bound types
- `view.php` — one row + related lists via `render_deflist()` / `render_related_list()`

> That's why nine modules are nearly the same file three times, and a new module is mostly a matter of copying `faculties/`.

---

## Requirements

| Component | Version |
|-----------|---------|
| **PHP** | 8.0+ with `mysqli` extension |
| **Database** | MySQL 8.x or MariaDB 10.4+ |
| **Web Server** | Apache with `mod_rewrite` enabled (for the included access rules), Nginx, or any PHP-capable server |

Developed and tested on **XAMPP** (PHP 8.0.30, MariaDB, Apache on port 80).

---

## Installation

### 1. Get the Files
```bash
git clone https://github.com/Shariif-Cabdiraxman/Campus-Manager.git
```

Place under your web root (e.g., `htdocs/Campus-Manager` or `/var/www/html/Campus-Manager`).

### 2. Create Database & Import Schema
```bash
mysql -u root < database.sql
```
This creates:
- `university_management` database
- 9 tables with foreign keys
- Full demonstration data (see [Seed Data](#seed-data))

> Or use phpMyAdmin: **Import** → choose `database.sql`

### 3. Configure Credentials
```bash
cp config/database.example.php config/database.php
```
Edit `config/database.php` with your credentials. All values support environment variables for production:
```php
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', (int) (getenv('DB_PORT') ?: 3306));
define('DB_NAME', getenv('DB_NAME') ?: 'university_management');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
```

### 4. Set the First Password
`database.sql` ships no usable password. Set one for the first administrator:

```bash
php tools/set_password.php admin 'choose-a-strong-password'
```

For a local demonstration only, `php tools/set_password.php --demo` restores the
public demo passwords.

### 5. Open in Browser
Navigate to `http://localhost/Campus-Manager/` and sign in with the
administrator password you set in step 4.

---

## Seed Data

A fresh import contains enough data to exercise every screen, including the delete guards:

| Faculties | Departments | Lecturers | Courses | Students | Enrolments | Grades | Announcements | Users |
|-----------|-------------|-----------|---------|----------|------------|--------|---------------|-------|
| 5 | 12 | 19 | 37 | 400 | 644 | 1,076 | 7 | 3 |

---

## Demo Accounts & Roles

### Accounts
`database.sql` creates the three staff rows but ships **no usable password**.
Set one from the command line:

```bash
# Real installation: choose a strong password for the first administrator
php tools/set_password.php admin 'choose-a-strong-password'

# Local demonstration only: restore the public demo passwords
php tools/set_password.php --demo
```

Then enable demo mode if you want the sign-in page to list the demo accounts.
Never run `--demo` against a real deployment.

### Permission Matrix

| Permission | admin | registrar |
|------------|:-----:|:---------:|
| `faculties.manage` | ✅ | — |
| `departments.manage` | ✅ | — |
| `lecturers.manage` | ✅ | — |
| `users.manage` | ✅ | — |
| `students.manage` | ✅ | ✅ |
| `courses.manage` | ✅ | ✅ |
| `enrollments.manage` | ✅ | ✅ |
| `grades.manage` | ✅ | ✅ |
| `announcements.manage` | ✅ | ✅ |

Permissions are checked server-side in `require_permission()` at the top of every write endpoint — editing the URL or posting directly cannot bypass the interface.

### Debug / Demo Mode
Demo mode is **off by default**, so the sign-in page never advertises working
credentials on a fresh install. Enable it only for a local portfolio copy:

```powershell
# Show the demo hint block on the login page
setx APP_DEMO_MODE 1    # restart Apache

# Hide it again (an explicit off value always wins)
setx APP_DEMO_MODE 0    # restart Apache

# Enable debug exceptions (local only!)
setx APP_DEBUG 1        # restart Apache
```

Or create empty files (gitignored):
- `config/demo.local.php` — enables demo mode
- `config/demo.off.local.php` — disables demo mode even if the environment enables it
- `config/debug.local.php` — enables debug mode

---

## Modules

| Module | Purpose | Notable Business Rules |
|--------|---------|------------------------|
| **Faculties** | Top-level academic units | — |
| **Departments** | Academic departments | Head must be a lecturer in the owning faculty |
| **Lecturers** | Teaching staff | Delete blocked while students registered to lecturer |
| **Courses** | Offered courses | Delete blocked while students enrolled |
| **Students** | Students (per department) | Student number: unique, upper-cased, format-checked; public ID in URLs |
| **Enrollments** | Student ↔ Course in term/year | Rejected if course dept ≠ student dept |
| **Grades** | Marks per assessment | Letter grade computed server-side from mark (never typed) |
| **Announcements** | Notices (draft/published) | `published_at` stamped on first publish |
| **Users** | Sign-in accounts | bcrypt passwords; 5 failures per session → 15-min cool-down |

> Example: a grade of `87.5` is recorded as `B`. The letter is always derived from the mark, so a stored letter can never disagree with its mark.

---

## On a Phone

The **same application, drawn for a thumb**. No second set of screens, no second set of queries. `includes/mobile.php` decides from the request which shell to draw; the page above it is the same code path. A record saved on a phone is the record the desktop shows.

| Aspect | Phone | Desktop |
|--------|-------|---------|
| **Navigation** | 4-item bottom bar + full module index at `/m/` | Persistent rail, collapses to menu < 900px |
| **Lists** | Search full-width, filters behind fold, prev/next pager | Search, filters, page size in one row |
| **Tables** | Each row → labelled card (heading copied per cell) | Real `<table>` |
| **Forms** | 1 column, 16px controls (no iOS zoom), pinned save bar | Multi-column grid |
| **Touch Targets** | ≥ 44px | Pointer-sized |
| **Notches** | `viewport-fit=cover`, `env(safe-area-inset-*)` on bars | — |

### Shell Detection
- **Preferred**: measured viewport width (via `assets/js/mobile.js` → 1-day cookie)
- **Fallback**: user agent (tablet = desktop)
- **Manual override**: `?view=mobile` or `?view=desktop` (remembered in session)

### Installable PWA
- `manifest.webmanifest` + `sw.js` make it installable
- Service worker **precaches only static assets** (CSS, JS, icons)
- **Never caches HTML** — every screen is behind a session; caching a page would leak records
- Offline navigations show a small offline page

> Try it on desktop: resize window narrow + reload, or open `?view=mobile`

---

## Tests

The smoke suites sign in as the demo administrator, so run
`php tools/set_password.php --demo` on the local test database first. Never run
that command against a real deployment.

### Desktop Smoke Suite (86 checks)
```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File tools\smoke_modules.ps1
```
Signs in, visits every list page, follows dashboard deep links, does create+delete round-trip per module with validation paths (duplicate keys, mismatched departments, cross-faculty heads, password mismatches).

**Current: 86 checks, 0 failures.**

### Mobile Smoke Suite (132 checks)
```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File tools\smoke_mobile.ps1
```
Verifies phone-width requests get mobile shell, desktop requests don't, every screen type carries phone furniture, create+delete works through phone shell, shell-switching sticks.

**Current: 132 checks, 0 failures.**

Both suites create and remove their own rows — a green run leaves the database exactly as it found it. They exit non-zero on failure, so they work as CI steps.

### Manual Probe
```powershell
$env:SMOKE_PASSWORD = 'your-password'   # or run tools\set_password.php --demo first
. tools\smoke.ps1
Login 'admin' $env:SMOKE_PASSWORD
$r = Http-Get '/grades/index.php'
$r.Status                      # 200
Diagnostics $r.Html            # $null = no PHP diagnostics leaked
```

### Syntax Check
```bash
find . -name '*.php' -exec php -l {} \;
```

---

## Project Structure

```
Campus-Manager/
├── index.php                 # Front controller → redirects to sign-in
├── auth/                     # login.php, logout.php
├── dashboard.php             # Landing page with deep links per module
├── database.sql              # Schema, constraints, seed data
├── manifest.webmanifest      # PWA manifest (phone build)
├── sw.js                     # Service worker (static assets only)
├── assets/                   # 1 stylesheet, 3 scripts, no build step
├── config/                   # app.php + gitignored database.php
├── includes/                 # Shared layer
│   ├── init.php              # Bootstrap, error handling, output buffering
│   ├── db.php                # mysqli wrapper, exceptions, safe identifiers
│   ├── auth.php              # Roles, permissions, login throttling
│   ├── validation.php        # Field validation rules
│   ├── list_query.php        # List rendering, filters, sort, pagination
│   ├── crud.php              # Write guards, guarded delete
│   ├── helpers.php           # Escaping, redirects, flashes
│   ├── charts.php            # Dashboard counters & bar charts
│   ├── error_page.php        # 403 / 404 / 500 page
│   ├── flash.php             # One-shot messages
│   ├── icons.php             # Inline SVG sprite
│   ├── mobile.php            # Shell detection + phone chrome
│   └── layout.php            # header, footer, navbar, sidebar, layout
├── m/                        # Phone module index (desktop sent onward)
├── faculties/  departments/  lecturers/  courses/
├── students/   enrollments/  grades/  announcements/  users/
├── .htaccess                 # Denies .git, config, includes, tools, SQL and docs
└── tools/                    # set_password.php, seed_students.php, smoke suites
```

---

## Security

- **Passwords**: bcrypt via `password_hash()`; the seed file ships no usable password
- **Demo Mode**: off by default; the login page advertises accounts only when explicitly enabled
- **Authorization**: every write behind `require_permission()`, server-side
- **XSS Prevention**: all output through `e()`; `safe_identifier()` guards SQL interpolation
- **SQL Injection**: every statement prepared + parameter-bound
- **CSRF**: state-changing forms carry a token
- **CSP**: `Content-Security-Policy` set — no inline handlers (filters use `data-auto-submit`)
- **Brute-Force Protection**: 5 failed logins per session → 15-minute cool-down (server-side throttling is the next hardening step)
- **Timing Attack Resistance**: unknown usernames still run `password_verify()` against dummy hash
- **Web Exposure**: `.htaccess` denies `.git`, `config/`, `includes/`, `tools/`, SQL dumps, Markdown notes, and dotfiles; directory listings are off
- **CLI Tools**: `seed_students.php` and `set_password.php` refuse to run outside the command line

---

## Contributing

1. Fork the repo
2. Create a feature branch (`git checkout -b feature/amazing-thing`)
3. Run syntax check: `find . -name '*.php' -exec php -l {} \;`
4. Run smoke tests: `powershell -File tools\smoke_modules.ps1`
5. Submit a PR with a clear description

---

## License

**MIT License** — see [LICENSE](LICENSE)

Copyright (c) 2026 Shariif Abdi

---

## About the Author

**Shariif Abdi** — [@Shariif-Cabdiraxman](https://github.com/Shariif-Cabdiraxman)

*A campus management system by Shariif Abdi. If you find this useful, ⭐ the repo!*

---

> **Related Repos**  
> - [Teacher Digital Gradebook](https://github.com/Shariif-Cabdiraxman/gradebook) — Offline-first, sync-enabled gradebook (TypeScript/React/Tauri/React Native)
