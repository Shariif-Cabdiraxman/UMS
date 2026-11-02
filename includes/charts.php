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

/** A labelled SVG area chart for monthly activity. */
function render_area_chart(array $rows, string $label = 'Monthly activity'): void
{
    if ($rows === []) {
        render_empty_state('enrollment', 'No activity yet', 'New activity will appear here when it is recorded.');

        return;
    }

    $width = 720;
    $top = 18;
    $bottom = 172;
    $left = 38;
    $right = 700;
    $max = max(1, max(array_map(static fn(array $row): int => (int) $row['n'], $rows)));
    $count = count($rows);
    $points = [];

    foreach ($rows as $index => $row) {
        $x = $count === 1 ? ($left + $right) / 2 : $left + (($right - $left) * $index / ($count - 1));
        $y = $bottom - (((int) $row['n'] / $max) * ($bottom - $top));
        $points[] = [round($x, 1), round($y, 1), (int) $row['n'], (string) $row['month_label']];
    }

    $line = implode(' ', array_map(static fn(array $point, int $index): string =>
        ($index === 0 ? 'M ' : 'L ') . $point[0] . ' ' . $point[1], $points, array_keys($points)));
    $first = $points[0];
    $last = $points[count($points) - 1];
    $area = $line . ' L ' . $last[0] . ' ' . $bottom . ' L ' . $first[0] . ' ' . $bottom . ' Z';
    $tooltipText = number_format($last[2]) . ' · ' . $last[3];
    // Keep the label comfortably readable, even when the final month is a
    // long name, and keep the whole bubble inside the plotting area.
    $tooltipWidth = max(84, (strlen($tooltipText) * 6.4) + 20);
    $tooltipX = min($right - $tooltipWidth, max($left, $last[0] - ($tooltipWidth / 2)));
    $tooltipY = max(2, $last[1] - 33);
    ?>
    <div class="area-chart" role="img" aria-label="<?= e($label) ?>: <?php
        echo e(implode(', ', array_map(static fn(array $row): string => $row['month_label'] . ' ' . $row['n'], $rows)));
    ?>">
        <svg viewBox="0 0 <?= e((string) $width) ?> 220" preserveAspectRatio="xMidYMid meet" aria-hidden="true" focusable="false">
            <defs>
                <linearGradient id="activity-fill" x1="0" x2="0" y1="0" y2="1">
                    <stop offset="0%" stop-color="var(--mint-400)" stop-opacity=".34" />
                    <stop offset="100%" stop-color="var(--mint-300)" stop-opacity=".02" />
                </linearGradient>
            </defs>
            <?php for ($grid = 0; $grid < 4; $grid++): ?>
                <?php $gridY = $top + (($bottom - $top) * $grid / 3); ?>
                <line class="area-chart__grid" x1="<?= e((string) $left) ?>" y1="<?= e((string) $gridY) ?>" x2="<?= e((string) $right) ?>" y2="<?= e((string) $gridY) ?>" />
            <?php endfor; ?>
            <path class="area-chart__fill" d="<?= e($area) ?>" />
            <path class="area-chart__line" d="<?= e($line) ?>" />
            <?php foreach ($points as $point): ?>
                <circle class="area-chart__point" cx="<?= e((string) $point[0]) ?>" cy="<?= e((string) $point[1]) ?>" r="3.5" />
            <?php endforeach; ?>
            <circle class="area-chart__focus" cx="<?= e((string) $last[0]) ?>" cy="<?= e((string) $last[1]) ?>" r="6" />
            <g class="area-chart__tooltip" transform="translate(<?= e((string) $tooltipX) ?> <?= e((string) $tooltipY) ?>)">
                <rect width="<?= e(number_format($tooltipWidth, 1, '.', '')) ?>" height="25" rx="7" />
                <text x="<?= e(number_format($tooltipWidth / 2, 1, '.', '')) ?>" y="16" text-anchor="middle"><?= e($tooltipText) ?></text>
            </g>
            <?php foreach ($points as $index => $point): ?>
                <?php if ($count <= 7 || $index === 0 || $index === $count - 1 || $index % 2 === 0): ?>
                    <text class="area-chart__axis" x="<?= e((string) $point[0]) ?>" y="205" text-anchor="middle"><?= e(substr($point[3], 0, 3)) ?></text>
                <?php endif; ?>
            <?php endforeach; ?>
        </svg>
    </div>
    <?php
}

/** A proportional monochrome grade bar with the pass rate and an accessible legend. */
function render_grade_donut(array $distribution, int $total, float $passRate): void
{
    if ($total === 0) {
        render_empty_state('grades', 'No grades recorded', 'Marks entered against enrollments will be summarised here.');

        return;
    }

    ?>
    <div class="grade-overview">
        <div class="grade-overview__summary"><strong><?= e(number_format($passRate, 1)) ?>% <span>pass rate</span></strong><span><?= e(number_format($total)) ?> marks</span></div>
        <div class="grade-donut" role="img" aria-label="Grade distribution across <?= e(number_format($total)) ?> marks; pass rate <?= e(number_format($passRate, 1)) ?> percent">
            <?php foreach ($distribution as $row): ?>
                <?php if ($row['count'] <= 0) { continue; } ?>
                <span class="grade-donut__segment grade-donut__segment--<?= e(strtolower($row['letter'])) ?>" style="width: <?= e(number_format(($row['count'] / $total) * 100, 3)) ?>%" title="<?= e($row['letter']) ?>: <?= e(number_format($row['count'])) ?>"></span>
            <?php endforeach; ?>
        </div>
        <ul class="grade-legend" aria-label="Grade counts and percentages">
            <?php foreach ($distribution as $row): ?>
                <?php if ($row['count'] <= 0) { continue; } ?>
                <li class="grade-legend__item grade-legend__item--<?= e(strtolower($row['letter'])) ?>">
                    <span class="grade-legend__key"><?= e($row['letter']) ?></span>
                    <span class="grade-legend__count"><?= e(number_format($row['count'])) ?></span>
                    <span class="grade-legend__share"><?= e(number_format(($row['count'] / $total) * 100, 1)) ?>%</span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
}

/** Compact rounded columns for the same monthly activity series. */
function render_monthly_enrollment_chart(array $rows, int $total): void
{
    if ($rows === []) {
        render_empty_state('enrollment', 'No enrollments yet', 'Monthly counts will appear once enrollments are recorded.');

        return;
    }

    $max = max(1, max(array_map(static fn(array $row): int => (int) $row['n'], $rows)));
    ?>
    <div class="column-chart" role="img" aria-label="Monthly enrollment counts: <?php
        echo e(implode(', ', array_map(static fn(array $row): string => $row['month_label'] . ' ' . $row['n'], $rows)));
    ?>">
        <p class="column-chart__total"><?= e(number_format($total)) ?><span>enrollments recorded</span></p>
        <div class="column-chart__bars" aria-hidden="true">
            <?php foreach ($rows as $row): ?>
                <?php $height = max(8, (int) round(((int) $row['n'] / $max) * 100)); ?>
                <div class="column-chart__column<?= (int) $row['n'] === $max ? ' is-peak' : '' ?>" title="<?= e($row['month_label']) ?>: <?= e((string) $row['n']) ?>">
                    <span class="column-chart__value"><?= e((string) $row['n']) ?></span>
                    <span class="column-chart__track"><i style="height: <?= e((string) $height) ?>%"></i></span>
                    <span class="column-chart__label"><?= e(substr((string) $row['month_label'], 0, 3)) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
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

/**
 * A smooth area/line chart drawn as inline SVG.
 *
 * Values are supplied by the caller from the database, never generated here.
 * The chart is a comparison aid: every value is also written above its point
 * and in the accompanying summary, so the shape is never the only answer.
 *
 * @param array<int,array{label:string,value:int|float}> $rows
 * @param array{max?:int|float,unit?:string,height?:int} $options
 */
function render_line_chart(array $rows, array $options = []): void
{
    if ($rows === []) {
        render_empty_state('chart', 'Nothing to chart yet', $options['empty'] ?? 'There is no data to show here yet.');

        return;
    }

    $width  = 660;
    $height = (int) ($options['height'] ?? 220);
    $padL   = 34;
    $padR   = 14;
    $padT   = 18;
    $padB   = 30;
    $plotW  = $width - $padL - $padR;
    $plotH  = $height - $padT - $padB;

    $max = $options['max'] ?? 0;
    foreach ($rows as $row) {
        $max = max($max, (float) $row['value']);
    }
    $max = max($max, 1);
    // Round the top of the scale so the gridlines read as sensible numbers.
    $step = max(1, (int) ceil($max / 4));
    $top  = $step * 4;

    $count = count($rows);
    $points = [];
    foreach ($rows as $i => $row) {
        $x = $count === 1 ? $padL + $plotW / 2 : $padL + ($plotW * $i / ($count - 1));
        $y = $padT + $plotH - (((float) $row['value'] / $top) * $plotH);
        $points[] = ['x' => $x, 'y' => $y, 'row' => $row];
    }

    $line = '';
    foreach ($points as $i => $point) {
        if ($i === 0) {
            $line .= 'M' . number_format($point['x'], 2) . ' ' . number_format($point['y'], 2);
            continue;
        }

        $previous = $points[$i - 1];
        $midX = ($previous['x'] + $point['x']) / 2;
        $line .= ' C' . number_format($midX, 2) . ' ' . number_format($previous['y'], 2)
            . ' ' . number_format($midX, 2) . ' ' . number_format($point['y'], 2)
            . ' ' . number_format($point['x'], 2) . ' ' . number_format($point['y'], 2);
    }

    $area = $line
        . ' L' . number_format($points[$count - 1]['x'], 2) . ' ' . number_format($padT + $plotH, 2)
        . ' L' . number_format($points[0]['x'], 2) . ' ' . number_format($padT + $plotH, 2) . ' Z';

    $gradientId = 'lineGradient' . substr(md5(implode(',', array_column($rows, 'label'))), 0, 6);
    ?>
    <div class="linechart">
        <svg class="linechart__svg" viewBox="0 0 <?= e((string) $width) ?> <?= e((string) $height) ?>"
             role="img" aria-label="Line chart: <?= e(implode(', ', array_map(
                 static fn ($row) => $row['label'] . ' ' . $row['value'],
                 $rows
             ))) ?>">
            <defs>
                <linearGradient id="<?= e($gradientId) ?>" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#545454" stop-opacity="0.24"/>
                    <stop offset="100%" stop-color="#545454" stop-opacity="0"/>
                </linearGradient>
            </defs>

            <?php for ($i = 0; $i <= 4; $i++):
                $y = $padT + ($plotH * $i / 4);
                $value = $top - ($top * $i / 4);
                ?>
                <line class="linechart__grid" x1="<?= $padL ?>" y1="<?= number_format($y, 2) ?>"
                      x2="<?= $width - $padR ?>" y2="<?= number_format($y, 2) ?>"/>
                <text class="linechart__axis" x="<?= $padL - 8 ?>" y="<?= number_format($y + 3, 2) ?>"
                      text-anchor="end"><?= e(number_format($value)) ?></text>
            <?php endfor; ?>

            <path d="<?= e($area) ?>" fill="url(#<?= e($gradientId) ?>)"/>
            <path d="<?= e($line) ?>" class="linechart__line"/>

            <?php foreach ($points as $point): ?>
                <circle class="linechart__dot" cx="<?= number_format($point['x'], 2) ?>"
                        cy="<?= number_format($point['y'], 2) ?>" r="3.4">
                    <title><?= e($point['row']['label']) ?>: <?= e(number_format((float) $point['row']['value'])) ?></title>
                </circle>
            <?php endforeach; ?>

            <?php foreach ($points as $point): ?>
                <text class="linechart__axis" x="<?= number_format($point['x'], 2) ?>"
                      y="<?= $height - 9 ?>" text-anchor="middle"><?= e($point['row']['label']) ?></text>
            <?php endforeach; ?>
        </svg>
    </div>
    <?php
}

/**
 * A donut chart, built from a conic-gradient so it needs no library.
 *
 * @param array<int,array{label:string,value:int|float,tone?:string}> $rows
 * @param array{center?:string,label?:string,unit?:string} $options
 */
function render_donut_chart(array $rows, array $options = []): void
{
    $total = 0;
    foreach ($rows as $row) {
        $total += (float) $row['value'];
    }

    if ($total <= 0) {
        render_empty_state('chart', 'Nothing to chart yet', $options['empty'] ?? 'There is no data to show here yet.');

        return;
    }

    $palette = [
        'var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)',
        'var(--chart-5)', '#A6A6A6', '#717171', '#909090',
    ];
    $stops = [];
    $cursor = 0.0;

    foreach (array_values($rows) as $i => $row) {
        $share = ((float) $row['value'] / $total) * 100;
        $color = $palette[$i % count($palette)];
        $stops[] = $color . ' ' . number_format($cursor, 2) . '% '
            . number_format($cursor + $share, 2) . '%';
        $cursor += $share;
    }

    $center = (string) ($options['center'] ?? number_format($total));
    $label  = (string) ($options['label'] ?? 'Total');
    ?>
    <div class="donut">
        <div class="donut__ring" style="background: conic-gradient(<?= e(implode(', ', $stops)) ?>);"
             role="img" aria-label="<?= e($label . ': ' . $center . '. ' . implode(', ', array_map(
                 static fn ($row) => $row['label'] . ' ' . $row['value'],
                 $rows
             ))) ?>">
            <div class="donut__hole">
                <span class="donut__value"><?= e($center) ?></span>
                <span class="donut__label"><?= e($label) ?></span>
            </div>
        </div>
        <div class="donut__legend">
            <?php foreach (array_values($rows) as $i => $row): ?>
                <span class="donut__item">
                    <span class="donut__dot" style="background: <?= e($palette[$i % count($palette)]) ?>"></span>
                    <span class="donut__name"><?= e($row['label']) ?></span>
                    <span class="donut__amount"><?= e(number_format((float) $row['value'])) ?></span>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

/**
 * A compact vertical bar chart for a short series.
 *
 * @param array<int,array{label:string,value:int|float}> $rows
 * @param array{unit?:string,empty?:string} $options
 */
function render_column_chart(array $rows, array $options = []): void
{
    if ($rows === []) {
        render_empty_state('chart', 'Nothing to chart yet', $options['empty'] ?? 'There is no data to show here yet.');

        return;
    }

    $max = 1;
    foreach ($rows as $row) {
        $max = max($max, (float) $row['value']);
    }
    ?>
    <div class="columns" role="img" aria-label="Column chart: <?= e(implode(', ', array_map(
        static fn ($row) => $row['label'] . ' ' . $row['value'],
        $rows
    ))) ?>">
        <?php foreach ($rows as $row): ?>
            <?php $height = max(3, ((float) $row['value'] / $max) * 100); ?>
            <div class="columns__item">
                <span class="columns__value"><?= e(number_format((float) $row['value'])) ?></span>
                <span class="columns__bar" style="height: <?= e(number_format($height, 2)) ?>%"></span>
                <span class="columns__label" title="<?= e($row['label']) ?>"><?= e($row['label']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

/** A circular percentage with a caption, used for pass rates and completion. */
function render_circle_metric(float $percent, string $title, string $text = ''): void
{
    $percent = max(0, min(100, $percent));
    ?>
    <div class="circlemetric">
        <div class="circlemetric__ring"
             style="background: conic-gradient(var(--primary) 0 <?= e(number_format($percent, 2)) ?>%, var(--surface-3) <?= e(number_format($percent, 2)) ?>% 100%);"
             role="img" aria-label="<?= e($title . ': ' . number_format($percent, 1) . '%') ?>">
            <div class="circlemetric__hole"><?= e(number_format($percent, 1)) ?>%</div>
        </div>
        <div>
            <p class="circlemetric__title"><?= e($title) ?></p>
            <?php if ($text !== ''): ?>
                <p class="circlemetric__text"><?= e($text) ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * A single-month calendar with dots for days that carry an event.
 *
 * @param array<int,int> $eventDays Day numbers in the month that have something on them.
 */
function render_mini_calendar(int $year, int $month, array $eventDays, string $title = ''): void
{
    $first     = mktime(0, 0, 0, $month, 1, $year);
    $days      = (int) date('t', $first);
    $startCol  = ((int) date('N', $first)) - 1; // Monday = 0
    $today     = date('Y-n-j');
    $monthName = date('F Y', $first);
    $events    = array_flip(array_map('intval', $eventDays));
    ?>
    <div class="minical">
        <div class="minical__head">
            <p class="minical__title"><?= e($title !== '' ? $title : $monthName) ?></p>
        </div>
        <div class="minical__week" aria-hidden="true">
            <?php foreach (['M', 'T', 'W', 'T', 'F', 'S', 'S'] as $day): ?>
                <span class="minical__weekday"><?= e($day) ?></span>
            <?php endforeach; ?>
        </div>
        <div class="minical__grid">
            <?php for ($i = 0; $i < $startCol; $i++): ?>
                <span class="minical__day minical__day--muted" aria-hidden="true"></span>
            <?php endfor; ?>
            <?php for ($day = 1; $day <= $days; $day++):
                $isToday = $today === $year . '-' . $month . '-' . $day;
                $hasEvent = isset($events[$day]);
                ?>
                <span class="minical__day<?= $isToday ? ' minical__day--today' : '' ?><?= $hasEvent ? ' minical__day--event' : '' ?>"><?= e((string) $day) ?></span>
            <?php endfor; ?>
        </div>
    </div>
    <?php
}
