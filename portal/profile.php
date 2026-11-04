<?php
/**
 * Student portal — the full personal and programme record.
 *
 * This page is read-only. Personal and programme details belong to the
 * registrar's office, so the portal shows the record exactly as it is held
 * rather than offering edits that would bypass that office.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/portal.php';

$student   = require_student();
$programme = student_programme($student);
$account   = student_account((int) $student['id']);
$name      = full_name($student['first_name'], $student['last_name']);

layout_start('My profile', 'profile');

render_page_head([
    'eyebrow'  => 'Student record',
    'title'    => $name,
    'subtitle' => 'Student ' . $student['student_no'],
    'meta'     => 'Record created ' . e(format_datetime($student['created_at']))
        . ' &middot; last changed ' . e(format_datetime($student['updated_at'])),
    'actions'  => '<a class="btn btn--quiet" href="' . e(url('portal/dashboard.php')) . '">'
        . icon('arrow-left', 15) . '<span>Back to dashboard</span></a>',
]);
?>

<div class="profile-layout">
    <aside class="profile-card">
        <div class="profile-card__avatar" aria-hidden="true"><?= icon('student', 36) ?></div>
        <h2 class="profile-card__name"><?= e($name) ?></h2>
        <p class="profile-card__role">
            <?= e($programme['department_name'] ?? 'No department') ?>
            <?php if ($programme !== null): ?>
                · <?= e($programme['faculty_name']) ?>
            <?php endif; ?>
        </p>
        <div class="profile-card__badges">
            <?= badge($student['status']) ?>
            <span class="badge badge--neutral"><span class="badge__mark" aria-hidden="true"></span><?= e(humanize($student['gender'])) ?></span>
        </div>

        <div class="profile-card__divider"></div>

        <div class="profile-meta">
            <div class="profile-meta__item">
                <span class="profile-meta__icon"><?= icon('student', 14) ?></span>
                <span>
                    <span class="profile-meta__label">Student number</span>
                    <span class="profile-meta__value table__id"><?= e($student['student_no']) ?></span>
                </span>
            </div>
            <div class="profile-meta__item">
                <span class="profile-meta__icon"><?= icon('mail', 14) ?></span>
                <span>
                    <span class="profile-meta__label">Email</span>
                    <span class="profile-meta__value"><a href="mailto:<?= e($student['email']) ?>"><?= e($student['email']) ?></a></span>
                </span>
            </div>
            <div class="profile-meta__item">
                <span class="profile-meta__icon"><?= icon('phone', 14) ?></span>
                <span>
                    <span class="profile-meta__label">Phone</span>
                    <span class="profile-meta__value">
                        <?= $student['phone'] !== null ? e($student['phone']) : '<span class="text-3">Not recorded</span>' ?>
                    </span>
                </span>
            </div>
            <div class="profile-meta__item">
                <span class="profile-meta__icon"><?= icon('calendar', 14) ?></span>
                <span>
                    <span class="profile-meta__label">Date of birth</span>
                    <span class="profile-meta__value">
                        <?= e(format_db_date($student['date_of_birth'])) ?>
                        <?php if ($student['date_of_birth'] !== null): ?>
                            · age <?= e((string) age_from($student['date_of_birth'])) ?>
                        <?php endif; ?>
                    </span>
                </span>
            </div>
            <div class="profile-meta__item">
                <span class="profile-meta__icon"><?= icon('faculty', 14) ?></span>
                <span>
                    <span class="profile-meta__label">Programme</span>
                    <span class="profile-meta__value"><?= e($programme['department_name'] ?? 'Not assigned') ?></span>
                </span>
            </div>
            <div class="profile-meta__item">
                <span class="profile-meta__icon"><?= icon('clock', 14) ?></span>
                <span>
                    <span class="profile-meta__label">Enrolled</span>
                    <span class="profile-meta__value"><?= e((string) $student['enrollment_year']) ?> · <?= e(format_db_date($student['enrollment_date'])) ?></span>
                </span>
            </div>
        </div>

        <a class="btn btn--quiet btn--block mt-3" href="<?= e(url('portal/account.php')) ?>">
            <?= icon('settings', 15) ?><span>Account settings</span>
        </a>
    </aside>

    <div class="stackgap">
        <section class="panel">
            <div class="panel__head">
                <div class="panel__titles">
                    <h2 class="panel__title">Personal details</h2>
                    <p class="panel__sub">As held by the registrar&rsquo;s office</p>
                </div>
            </div>
            <div class="panel__body">
                <?php render_deflist([
                    ['term' => 'Full name', 'desc' => e($name)],
                    ['term' => 'Student number', 'desc' => '<span class="table__id">' . e($student['student_no']) . '</span>'],
                    ['term' => 'Gender', 'desc' => e(humanize($student['gender']))],
                    ['term' => 'Date of birth', 'desc' => e(format_db_date($student['date_of_birth']))
                        . ($student['date_of_birth'] !== null ? ' · age ' . e((string) age_from($student['date_of_birth'])) : '')],
                    ['term' => 'Email', 'desc' => '<a class="strong" href="mailto:' . e($student['email']) . '">' . e($student['email']) . '</a>'],
                    ['term' => 'Phone', 'desc' => $student['phone'] !== null ? e($student['phone']) : '<span class="text-3">Not recorded</span>'],
                ]); ?>
            </div>
        </section>

        <section class="panel">
            <div class="panel__head">
                <div class="panel__titles">
                    <h2 class="panel__title">Programme and enrolment</h2>
                    <p class="panel__sub">The academic record attached to this student number</p>
                </div>
            </div>
            <div class="panel__body">
                <?php render_deflist([
                    ['term' => 'Faculty', 'desc' => e($programme['faculty_name'] ?? 'Not assigned')],
                    ['term' => 'Department', 'desc' => e($programme['department_name'] ?? 'Not assigned')],
                    ['term' => 'Enrollment year', 'desc' => e((string) $student['enrollment_year'])],
                    ['term' => 'Enrollment date', 'desc' => e(format_db_date($student['enrollment_date']))],
                    ['term' => 'Status', 'desc' => badge($student['status'])],
                ]); ?>
            </div>
        </section>

        <section class="panel">
            <div class="panel__head">
                <div class="panel__titles">
                    <h2 class="panel__title">Portal account</h2>
                    <p class="panel__sub">The sign-in attached to this student record</p>
                </div>
            </div>
            <div class="panel__body">
                <?php if ($account === null): ?>
                    <p class="text-2 text-sm">No portal account has been issued yet. Contact the registrar&rsquo;s office to request access.</p>
                <?php else: ?>
                    <?php render_deflist([
                        ['term' => 'Sign-in', 'desc' => '<span class="table__id">' . e($student['student_no']) . '</span> or ' . e($student['email'])],
                        ['term' => 'Account status', 'desc' => badge($account['status'])],
                        ['term' => 'Last sign-in', 'desc' => e(format_datetime($account['last_login_at']))],
                        ['term' => 'Issued', 'desc' => e(format_datetime($account['created_at']))],
                    ]); ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel">
            <div class="panel__head">
                <div class="panel__titles">
                    <h2 class="panel__title">Need a change?</h2>
                </div>
            </div>
            <div class="panel__body">
                <p class="text-2 text-sm">
                    Name, contact, programme and enrolment details are maintained by the registrar.
                    Contact the registrar&rsquo;s office if anything on this page is out of date.
                </p>
            </div>
        </section>
    </div>
</div>

<?php layout_end(); ?>
