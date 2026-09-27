<?php
/**
 * Sign in.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$errors = [];
$identifier = '';

if (is_post()) {
    verify_csrf();

    $identifier = (string) input('identifier', '');
    $password   = (string) input('password', '');

    if ($identifier === '') {
        $errors['identifier'] = 'Enter your username or email address.';
    }

    if ($password === '') {
        $errors['password'] = 'Enter your password.';
    }

    if ($errors === []) {
        $result = attempt_login($identifier, $password);

        if ($result['ok']) {
            $intended = $_SESSION['intended_url'] ?? null;
            unset($_SESSION['intended_url']);

            // Only follow a path inside this application.
            $destination = (is_string($intended) && strpos($intended, '..') === false && !preg_match('#^https?://#i', $intended))
                ? $intended
                : 'dashboard.php';

            flash_set('success', 'Welcome back, ' . $result['user']['full_name'] . '.');
            redirect($destination);
        }

        $errors['password'] = $result['message'];
    }
}

$flashes = flash_take();

render_standalone_head('', ['bodyClass' => 'authpage']);

$username = 'admin';
$password = 'Admin@123';
?>
<div class="auth">

    <section class="auth__intro">
        <div class="auth__brand">
            <span class="auth__mark"><?= brand_mark(46) ?></span>
            <div>
                <p class="auth__institution"><?= e(APP_NAME) ?></p>
                <p class="auth__office"><?= e(APP_TAGLINE) ?></p>
            </div>
        </div>

        <h1 class="auth__headline">The university record,<br>kept in one place.</h1>

        <p class="auth__lede">
            Student records, teaching structure, enrollments and results for the
            whole institution — maintained in one system rather than a folder per office.
        </p>

        <ul class="auth__facts">
            <li>
                <span class="auth__facticon"><?= icon('student', 16) ?></span>
                <div>
                    <strong>Student records</strong>
                    <span>Enrolment, contact details and status</span>
                </div>
            </li>
            <li>
                <span class="auth__facticon"><?= icon('faculty', 16) ?></span>
                <div>
                    <strong>Academic structure</strong>
                    <span>Faculties, departments and teaching staff</span>
                </div>
            </li>
            <li>
                <span class="auth__facticon"><?= icon('grades', 16) ?></span>
                <div>
                    <strong>Enrolment and results</strong>
                    <span>Courses, credits and grades</span>
                </div>
            </li>
        </ul>
    </section>

    <section class="auth__panel">
        <div class="auth__card">
            <div class="auth__cardhead">
                <h2 class="auth__title">Sign in</h2>
                <p class="auth__sub">Use the account issued to you by the registrar&rsquo;s office.</p>
            </div>

            <?php if ($flashes !== []): ?>
                <div class="flashes" role="status">
                    <?php foreach ($flashes as $message): ?>
                        <div class="flash flash--<?= e($message['type']) ?>" data-flash>
                            <span class="flash__icon"><?= icon(FLASH_ICONS[$message['type']] ?? 'info', 16) ?></span>
                            <p class="flash__text"><?= e($message['message']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form class="auth__form" method="post" action="<?= e(url('auth/login.php')) ?>" novalidate>
                <?= csrf_field() ?>

                <div class="field<?= isset($errors['identifier']) ? ' is-invalid' : '' ?>">
                    <label class="field__label" for="identifier">Username or email</label>
                    <div class="field__control">
                        <span class="field__leading"><?= icon('student', 16) ?></span>
                        <input
                            type="text"
                            id="identifier"
                            name="identifier"
                            value="<?= e($identifier) ?>"
                            autocomplete="username"
                            autocapitalize="none"
                            spellcheck="false"
                            required
                            autofocus
                            <?= isset($errors['identifier']) ? 'aria-invalid="true" aria-describedby="identifier-error"' : '' ?>>
                    </div>
                    <?php if (isset($errors['identifier'])): ?>
                        <p class="field__error" id="identifier-error"><?= icon('alert', 13) ?><?= e($errors['identifier']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field<?= isset($errors['password']) ? ' is-invalid' : '' ?>">
                    <label class="field__label" for="password">Password</label>
                    <div class="field__control">
                        <span class="field__leading"><?= icon('lock', 16) ?></span>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            autocomplete="current-password"
                            required
                            <?= isset($errors['password']) ? 'aria-invalid="true" aria-describedby="password-error"' : '' ?>>
                        <button class="field__reveal" type="button" data-reveal-password="password"
                                aria-label="Show password" aria-pressed="false"><?= icon('eye', 16) ?></button>
                    </div>
                    <?php if (isset($errors['password'])): ?>
                        <p class="field__error" id="password-error"><?= icon('alert', 13) ?><?= e($errors['password']) ?></p>
                    <?php endif; ?>
                </div>

                <button class="btn btn--primary btn--block btn--lg" type="submit">
                    <?= icon('arrow-right', 17) ?><span>Sign in</span>
                </button>
            </form>

            <div class="auth__demo">
                <p class="auth__demotitle">Demonstration accounts</p>
                <dl class="auth__demos">
                    <div>
                        <dt>Administrator</dt>
                        <dd><code><?= e($username) ?></code> / <code><?= e($password) ?></code></dd>
                    </div>
                    <div>
                        <dt>Registrar</dt>
                        <dd><code>registrar</code> / <code>Registrar@123</code></dd>
                    </div>
                </dl>
                <p class="auth__demonote">
                    This is a portfolio project. Change these credentials before any real use.
                </p>
            </div>
        </div>
    </section>
</div>
<?php
render_document_foot();
