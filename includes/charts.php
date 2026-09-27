<?php
/**
 * Charts.
 *
 * Drawn as ordinary HTML and CSS rather than a charting library: a labelled
 * bar is a div with a width, which keeps the numbers in the page, needs no
 * external script, and prints properly. assets/js/charts.js only animates the
 * widths into place.
 *
 * The rule followed throughout: a value is always written next to its bar, so
 * the chart is never the only place a number appears, and length carries the
 * comparison while colour carries the category.
 */

declare(strict_types=1);

/**
 * A horizontal bar chart.
 *
 * @param array<int,array{label:string,value:int|float,note?:string,href?:string,tone?:string}> $rows
 * @param array{max?:int|float,unit?:string,noun?:string,empty:string} $options
 */
function render_bar_chart(array $rows, array $options = []): void
{
    $max = $options['max'] ?? null;
    $unit = $options['unit'] ?? '';
    $noun = $options['noun'] ?? '';

    if ($rows === []) {
        render_empty_state('grades', 'Nothing to chart yet', $options['empty'] ?? 'There is no data to show here yet.');

        return;
    }

    if ($max === null) {
        $max = 0;
        foreach ($rows as $row) {
            $max = max($max, (float) $row['value']);
        }
        $max = max($max, 1);
    }
    ?>
    <div class="chart" data-chart data-chart-max="<?= e((string) $max) ?>">
        <?php foreach ($rows as $row): ?>
            <?php
            $tone = $row['tone'] ?? '';
            $width = max(0, min(100, ((float) $row['value'] / (float) $max) * 100));
            $label = isset($row['href'])
                ? '<a href="' . e($row['href']) . '">' . e($row['label']) . '</a>'
                : e($row['label']);
            ?>
            <div class="chart__row<?= $tone !== '' ? ' chart__row--' . e($tone) : '' ?>">
                <span class="chart__label" title="<?= e($row['label']) ?>"><?= $label ?></span>
                <span class="chart__track">
                    <span class="chart__bar" data-chart-bar data-value="<?= e((string) $row['value']) ?>"
                          style="width: <?= e(number_format($width, 2)) ?>%"></span>
                </span>
                <span class="chart__value">
                    <?= e(number_format((float) $row['value'])) ?><?= e($unit) ?>
                    <?php if (!empty($row['note'])): ?>
                        <small><?= e($row['note']) ?></small>
                    <?php endif; ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php if ($noun !== ''): ?>
        <p class="chart__foot">
            <span><?= e(ucfirst(rtrim($noun, 's'))) ?>s by <?= e($noun === 'student' ? 'faculty' : 'category') ?></span>
            <span><?= e(number_format((float) $max)) ?> max</span>
        </p>
    <?php endif; ?>
    <?php
}

/**
 * The grade distribution, as one stacked bar plus a legend with the counts.
 *
 * @param array<int,array{letter:string,count:int,avg:?float}> $distribution
 */
function render_grade_distribution(array $distribution, int $total): void
{
    $tones = ['A' => 'ok', 'B' => 'info', 'C' => 'warn', 'D' => 'warn', 'F' => 'danger'];
    $swatches = ['A' => 'ok', 'B' => 'info', 'C' => 'warn', 'D' => 'warn', 'F' => 'danger'];

    if ($total === 0) {
        render_empty_state('grades', 'No grades recorded', 'Marks entered against enrollments will be summarised here.');

        return;
    }
    ?>
    <div class="stack" role="img"
         aria-label="Distribution of <?= e((string) $total) ?> recorded grades: <?php
            $parts = [];
            foreach ($distribution as $row) {
                $parts[] = $row['letter'] . ' ' . $row['count'] . ' ('
                    . round(($row['count'] / $total) * 100) . '%)';
            }
            echo e(implode(', ', $parts));
         ?>">
        <?php foreach ($distribution as $row): ?>
            <?php if ($row['count'] === 0) { continue; } ?>
            <span class="stack__seg swatch--<?= e($swatches[$row['letter']] ?? 'neutral') ?>"
                  style="width: <?= e(number_format(($row['count'] / $total) * 100, 3)) ?>%"
                  title="<?= e($row['letter']) ?>: <?= e((string) $row['count']) ?>"></span>
        <?php endforeach; ?>
    </div>

    <div class="legend">
        <?php foreach ($distribution as $row): ?>
            <span class="legend__item">
                <span class="legend__swatch swatch--<?= e($swatches[$row['letter']] ?? 'neutral') ?>"></span>
                <strong><?= e($row['letter']) ?></strong>
                <span class="text-3"><?= e((string) $row['count']) ?></span>
                <?php if ($row['avg'] !== null): ?>
                    <span class="text-3">avg <?= e(number_format((float) $row['avg'], 1)) ?></span>
                <?php endif; ?>
            </span>
        <?php endforeach; ?>
    </div>
    <?php
    unset($tones);
}

/**
 * A short list of key/value figures, used under the dashboard charts.
 *
 * @param array<string,array{value:string,label:string,tone?:string}> $figures
 */
function render_figures(array $figures): void
{
    ?>
    <dl class="deflist deflist--single">
        <?php foreach ($figures as $figure): ?>
            <div>
                <dt class="deflist__term"><?= e($figure['label']) ?></dt>
                <dd class="deflist__desc">
                    <span class="strong"><?= e($figure['value']) ?></span>
                    <?php if (!empty($figure['tone'])): ?>
                        <?= badge($figure['tone']) ?>
                    <?php endif; ?>
                </dd>
            </div>
        <?php endforeach; ?>
    </dl>
    <?php
}
