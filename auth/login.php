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

render_standalone_head('');

// Demonstration credentials are shown only when the deployment says this is a
// demonstration. On a real installation the panel is never rendered, so the
// seeded passwords are not advertised to anyone who loads the sign-in page.
$demoAccounts = demo_accounts();
?>
<div class="auth">
    <main class="auth__shell">

        <header class="auth__brand">
            <span class="auth__mark"><?= brand_mark(30) ?></span>
            <span class="auth__brandtext">
                <span class="auth__institution"><?= e(APP_NAME) ?></span>
                <span class="auth__office"><?= e(APP_TAGLINE) ?></span>
            </span>
        </header>

        <section class="auth__card" aria-labelledby="auth-title">
            <div class="auth__cardhead">
                <p class="auth__eyebrow">Secure access</p>
                <h1 class="auth__title" id="auth-title">Welcome back</h1>
                <p class="auth__sub">Sign in with the account issued by the registrar&rsquo;s office.</p>
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
                        <span class="field__leading"><?= icon('student', 17) ?></span>
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
                        <span class="field__leading"><?= icon('lock', 17) ?></span>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            autocomplete="current-password"
                            required
                            <?= isset($errors['password']) ? 'aria-invalid="true" aria-describedby="password-error"' : '' ?>>
                        <button class="field__reveal" type="button" data-reveal-password="password"
                                aria-label="Show password" aria-pressed="false"><?= icon('eye', 17) ?></button>
                    </div>
                    <?php if (isset($errors['password'])): ?>
                        <p class="field__error" id="password-error"><?= icon('alert', 13) ?><?= e($errors['password']) ?></p>
                    <?php endif; ?>
                </div>

                <button class="btn btn--primary btn--block" type="submit">
                    <span>Sign in</span>
                    <?= icon('arrow-right', 17) ?>
                </button>
            </form>

            <?php if ($demoAccounts !== []): ?>
                <div class="auth__demo">
                    <p class="auth__demotitle">Demonstration accounts</p>
                    <dl class="auth__demos">
                        <?php foreach ($demoAccounts as $demo): ?>
                            <div>
                                <dt><?= e($demo['role']) ?></dt>
                                <dd><code><?= e($demo['username']) ?></code><span class="auth__demodiv">/</span><code><?= e($demo['password']) ?></code></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                    <details class="auth__demomore">
                        <summary>About these accounts</summary>
                        <p class="auth__demonote">
                            This installation is running in demonstration mode. To stop
                            the sign-in page advertising these accounts, create an empty
                            file <code>config/demo.off.local.php</code> or set
                            <code>APP_DEMO_MODE=0</code>, and change the passwords before
                            any real use.
                        </p>
                    </details>
                </div>
            <?php endif; ?>
        </section>

        <p class="auth__foot"><?= e(APP_NAME) ?> &middot; <?= e(APP_TAGLINE) ?> &middot; <?= e(date('Y')) ?></p>
    </main>
</div>
<?php
render_document_foot();
