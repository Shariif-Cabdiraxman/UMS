<?php
/**
 * One-shot messages.
 *
 * Set with flash_set() on the request that did the work, displayed on the next
 * one, then cleared. Reading them clears them, so refreshing a page does not
 * repeat the message.
 */

declare(strict_types=1);

/** Tone => icon, so a message is recognisable before it is read. */
const FLASH_ICONS = [
    'success' => 'check',
    'error'   => 'alert',
    'warning' => 'alert',
    'info'    => 'info',
];

function render_flashes(): void
{
    $messages = flash_take();

    if ($messages === []) {
        return;
    }
    ?>
    <div class="flashes" role="status" aria-live="polite">
        <?php foreach ($messages as $message): ?>
            <?php $tone = in_array($message['type'], array_keys(FLASH_ICONS), true) ? $message['type'] : 'info'; ?>
            <div class="flash flash--<?= e($tone) ?>" data-flash>
                <span class="flash__icon"><?= icon(FLASH_ICONS[$tone], 16) ?></span>
                <p class="flash__text"><?= e($message['message']) ?></p>
                <button class="flash__close" type="button" data-flash-close aria-label="Dismiss"><?= icon('x', 14) ?></button>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}
