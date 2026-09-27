/**
 * Chart animation.
 *
 * The bars themselves are rendered by PHP, because the numbers come from the
 * database and the markup has to be correct before any script runs. This file
 * only grows them from zero to their final width, which is why it is loaded
 * separately from app.js and only on screens that have a chart.
 *
 * Each bar carries its own value:
 *
 *     <span class="chart__bar" data-chart-bar data-value="62"></span>
 *
 * data-max is the value that corresponds to a full-width bar. Without it the
 * largest bar in the group is used.
 */
(function () {
    'use strict';

    function animate(group) {
        var bars = group.querySelectorAll('[data-chart-bar]');
        if (!bars.length) { return; }

        var declared = parseFloat(group.getAttribute('data-chart-max'));
        var values = [];

        Array.prototype.forEach.call(bars, function (bar) {
            values.push(parseFloat(bar.getAttribute('data-value')) || 0);
        });

        var max = declared > 0 ? declared : Math.max.apply(null, values.concat([1]));

        // Read the final widths from CSS so the two cannot drift apart, then
        // collapse to zero before the first frame paints.
        var widths = [];

        Array.prototype.forEach.call(bars, function (bar, index) {
            widths.push(Math.max(0, Math.min(100, (values[index] / max) * 100)));
            bar.style.width = '0%';
        });

        // One frame later, grow to the real width.
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                Array.prototype.forEach.call(bars, function (bar, index) {
                    bar.style.width = widths[index].toFixed(2) + '%';
                });
            });
        });
    }

    function init() {
        var groups = document.querySelectorAll('[data-chart]');

        Array.prototype.forEach.call(groups, function (group) {
            if (document.documentElement.getAttribute('data-theme') === 'dark') {
                // Nothing to do, but keep the read order obvious.
                group.setAttribute('data-chart-ready', '1');
            }
            animate(group);
        });
    }

    if (document.readyState !== 'loading') {
        init();
    } else {
        document.addEventListener('DOMContentLoaded', init);
    }
})();
