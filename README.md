# Hagmah University Management System

A student records system for a small university, written in plain PHP with no
framework and no build step. It covers the academic structure of the
institution (faculties, departments, staff, courses), the people in it
(lecturers and students), and what they do (enrolments, grades,
announcements) behind a role-based sign-in.

Nine modules, each with a searchable, sortable, paginated list, a create/edit
form, a detail page and a guarded delete. Foreign keys are enforced in the
database, and a delete that would orphan records is refused with a sentence
naming what is in the way rather than a constraint-violation error.

---

## Requirements

| | |
|---|---|
| PHP | 8.0 or newer, with `mysqli` |
| Database | MySQL 8.x or MariaDB 10.4+ |
| Web server | Apache with `mod_rewrite` not required; any PHP-capable server works |

Developed and tested on XAMPP (PHP 8.0.30, MariaDB, Apache on port 80).

---

## Installation

**1. Get the files.** Clone or download, then place them under your web root:

```bash
git clone https://github.com/Shariif-Cabdiraxman/UMS.git
```

**2. Create the database and import the schema.** `database.sql` creates the
`university_management` database, all nine tables, their foreign keys, and a
full set of demonstration data.

```bash
mysql -u root < database.sql
```

Or in phpMyAdmin: **Import** → choose `database.sql`.

**3. Create your local config.** `config/database.php` is gitignored because
it holds credentials. Copy the template and edit it:

```bash
cp config/database.example.php config/database.php
```

Every value can come from the environment instead, which suits a server:

```php
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', (int) (getenv('DB_PORT') ?: 3306));
define('DB_NAME', getenv('DB_NAME') ?: 'university_management');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
```

**4. Open it.** <http://localhost/UMS/> and sign in with one of the demo
accounts below.

### Seed data

A fresh import contains enough to exercise every screen, including the
delete guards:

| Faculties | Departments | Lecturers | Courses | Students | Enrolments | Grades | Announcements | Users |
|---|---|---|---|---|---|---|---|---|
| 5 | 12 | 19 | 37 | 48 | 644 | 1076 | 7 | 3 |

---

## Demo accounts

These are printed on `auth/login.php` on purpose so the project can be tried
immediately. That hint block is behind `APP_DEMO_MODE`, which is **on** by
default for exactly that reason, and can be switched off for a real
installation either by creating an empty `config/demo.off.local.php`
(gitignored) or with the environment variable:

```powershell
setx APP_DEMO_MODE 0    # then restart Apache
```

The accounts are seeded by `database.sql`; change the passwords or drop the
users, not just the hint.

| Username | Password | Role | Sees |
|---|---|---|---|
| `admin` | `Admin@123` | admin | every module |
| `registrar` | `Registrar@123` | registrar | students, courses, enrolments, grades, announcements |
| `admissions` | `Registrar@123` | registrar | as above |

### Roles

Two roles exist. Permissions are checked on the server in `require_permission()`
at the top of every page that writes, so editing the URL or posting to an
endpoint directly does not get anyone past the interface.

| Permission | admin | registrar |
|---|:--:|:--:|
| `faculties.manage` | yes | — |
| `departments.manage` | yes | — |
| `lecturers.manage` | yes | — |
| `users.manage` | yes | — |
| `students.manage` | yes | yes |
| `courses.manage` | yes | yes |
| `enrollments.manage` | yes | yes |
| `grades.manage` | yes | yes |
| `announcements.manage` | yes | yes |

### Debugging

`APP_DEBUG` is **off** unless switched on, so a fresh checkout on a public
server cannot leak exception messages. To see them locally, create an empty
`config/debug.local.php` (gitignored) or set the environment variable:

```powershell
setx APP_DEBUG 1     # then restart Apache
```

---

## Modules

| Module | What it holds | Notable rules |
|---|---|---|
| Faculties | The university's faculties | — |
| Departments | Academic departments | A head of department must be a lecturer in the owning faculty |
| Lecturers | Teaching staff, each in one department | Deleting one is blocked while students are registered to it |
| Courses | Offered courses, each owned by a department | Deleting one is blocked while students are enrolled |
| Students | Students, each in one department | Student number is unique, upper-cased, and format-checked; it is the record's public identifier, so it appears in URLs |
| Enrollments | Who is taking which course, in which term and year | Rejected if the course belongs to a different department than the student |
| Grades | Marks per assessment per enrolment | The letter grade is computed server-side, never typed |
| Announcements | Notices, draft or published | `published_at` is stamped on first publish |
| Users | Sign-in accounts | Passwords are bcrypt hashed; 5 failures triggers a 15-minute cool-down |

A grade of `87.5` is recorded as `B`. The letter is always derived from the
mark, so a stored letter can never disagree with its mark.

---

## On a phone

The same application, drawn for a thumb. There is no second set of screens and
no second set of queries: `includes/mobile.php` decides, from the request, which
shell to draw, and the page above it is the same page either way. A record saved
on a phone is the record the desktop shows, because it is the same code path.

What changes is the chrome and the furniture:

| | Phone | Desktop |
|---|---|---|
| Navigation | Four-item bar at the foot of the screen, plus a full module index at `/m/` | Persistent rail, collapsed to a menu below 900px |
| Lists | Search at full width, filters behind a fold, previous/next instead of a numbered pager | Search, filters and page size in one row |
| Tables | Each row becomes a labelled card, the heading copied onto each cell | A real table |
| Forms | One column, 16px controls so iOS does not zoom, and a save bar pinned to the foot | Multi-column grid |
| Targets | Nothing tappable is under 44px | Sized for a pointer |
| Notches | `viewport-fit=cover`, with `env(safe-area-inset-*)` on the bars | — |

**Which shell you get.** A measured viewport width is preferred, because a user
agent cannot tell a phone from a narrow desktop window; `assets/js/mobile.js`
reports the width on each page and the server reads it from a one-day cookie.
The user agent is the fallback, and a tablet is treated as a desktop. To choose
by hand, use `?view=mobile` or `?view=desktop`: the choice is remembered in the
session, so following a link from one shell to the other does not bounce you
back. The phone footer carries both links.

**Installable.** `manifest.webmanifest` and `sw.js` make it an app. The service
worker is deliberately narrow: it precaches the stylesheets, scripts and icons,
and **never** a page. Every screen in this application is behind a session, so
caching an HTML response would put one person's records on another person's
screen. Navigations go to the network, and are answered with a small offline
page when there is none.

To try it in a desktop browser, resize the window narrow and reload, or open
any page with `?view=mobile`.

---

## Tests

`tools/smoke_modules.ps1` drives a real HTTP session against the running app:
it signs in, visits every list page, follows the dashboard deep links, and does
a create-plus-delete round trip per module along with the validation paths
unique to that module — duplicate keys, mismatched departments, cross-faculty
heads, password mismatches.

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File tools\smoke_modules.ps1
```

Current result: **86 checks, 0 failures.**

`tools/smoke_mobile.ps1` covers what is only true for the phone: that a
phone-width request is given the mobile shell and a desktop one is not, that
every kind of screen carries the phone's own furniture, that a create-and-delete
round trip still works through the shell, and that switching shell in either
direction sticks.

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File tools\smoke_mobile.ps1
```

Current result: **132 checks, 0 failures.**

Both suites create and remove their own rows, so a green run leaves the database
exactly as it found it. They exit non-zero on any failure, so they work as CI
steps.

To probe a single page by hand, dot-source the harness:

```powershell
. tools\smoke.ps1
Login 'admin' 'Admin@123'
$r = Http-Get '/grades/index.php'
$r.Status                      # 200
Diagnostics $r.Html            # $null means no PHP diagnostics leaked into the page
```

`Http-Get` and `Post-Form` return both `.Status` and `.Html`. `Post-Form`
carries the CSRF token; `Http-Post` does not, so it cannot be used for anything
that writes. Set `$Global:Agent` to a mobile user agent to make the harness
arrive as a phone, and pass `-Cookie 'ums_viewport=390'` to send the viewport
width a real device would have reported.

### Syntax check

```bash
find . -name '*.php' -exec php -l {} \;
```

---

## Project structure

```
UMS/
├── index.php            Front controller; redirects to the sign-in screen
├── auth/                login.php and logout.php
├── dashboard.php        Landing page, deep links into each module
├── database.sql         Schema, constraints and seed data
├── manifest.webmanifest Installable-app description for the phone build
├── sw.js                Service worker; static assets only, never a page
├── assets/              One stylesheet, three scripts, no build step
├── config/              app.php and the gitignored database.php
├── includes/            The shared layer, described below
│   ├── init.php         Bootstrap, error handling, output buffering
│   ├── db.php           mysqli wrapper, exceptions, safe identifiers
│   ├── auth.php         Roles, permissions, login throttling
│   ├── validation.php   Field validation rules
│   ├── list_query.php   List rendering, filters, sort, pagination, forms
│   ├── crud.php         Write guards and the guarded delete
│   ├── helpers.php      Escaping, redirects, flashes
│   ├── charts.php       Dashboard counters and bar charts
│   ├── error_page.php   The 403 / 404 / 500 page
│   ├── flash.php        One-shot messages
│   ├── icons.php        Inline SVG sprite
│   ├── mobile.php       Which shell this request gets, and the phone's chrome
│   └── layout.php       header.php, footer.php, layout.php,
│                       navbar.php, sidebar.php
├── m/                   The phone's module index; a desktop is sent onward
├── faculties/  departments/  lecturers/  courses/
├── students/   enrollments/  grades/  announcements/  users/
└── tools/                smoke.ps1, smoke_modules.ps1, smoke_mobile.ps1
```

The module convention is the point of this layout. Every module has the same
three files, and the shared layer does the work:

- `index.php` — permission check, search/filter/sort state, one prepared
  query, then shared renderers for the toolbar, table, pager and delete form
- `form.php` — `validate()` against a rule map, then an insert or update bound
  with the correct types
- `view.php` — one row plus related lists, all through `render_deflist()` and
  `render_related_list()`

That is why the nine modules are nearly the same file three times, and why a
new module is mostly a matter of copying `faculties/`.

---

## Security

- Passwords are bcrypt hashed via `password_hash()`; nothing reads the column
  back out
- Every write is behind `require_permission()`, checked server-side
- All output goes through `e()`; `safe_identifier()` guards anything
  interpolated into SQL
- Every statement is prepared and parameter-bound
- The state-changing forms carry a CSRF token
- A `Content-Security-Policy` is set, which is why no inline `onchange`
  handlers are used — filters submit through `data-auto-submit` instead
- Failed logins are throttled: 5 attempts, then a 15-minute cool-down
- A sign-in for an unknown username still runs `password_verify()` against a
  dummy hash, so the response time does not reveal which usernames exist

---

## License

MIT. See [LICENSE](LICENSE).

Copyright (c) 2026 Shariif Abdi
