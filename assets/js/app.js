/**
 * Hagmah University — Registrar's Office
 * Progressive enhancement.
 *
 * Everything here is an addition to markup that already works. The
 * application is fully usable with JavaScript disabled: forms post, lists
 * filter and sort, records are created and deleted. What follows only saves
 * a click or two — closing a drawer, dismissing a message, animating a bar.
 */
(function () {
    'use strict';

    var doc = document;

    function onReady(fn) {
        if (doc.readyState !== 'loading') {
            fn();
        } else {
            doc.addEventListener('DOMContentLoaded', fn);
        }
    }

    // -----------------------------------------------------------------
    // Navigation drawer (small screens)
    //
    // Below 900px the rail is translated off-canvas but stays in the
    // document. Without `inert` a keyboard user tabs straight into a menu
    // they cannot see, so the closed drawer is taken out of the tab order
    // and the focus is kept inside it while it is open.
    // -----------------------------------------------------------------

    function initSidebar() {
        var sidebar = doc.getElementById('sidebar');
        var scrim = doc.querySelector('.scrim');
        if (!sidebar) { return; }

        var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
        var drawer = window.matchMedia('(max-width: 900px)');
        var openers = doc.querySelectorAll('[data-sidebar-open]');
        var lastFocused = null;

        function isOpen() {
            return sidebar.classList.contains('is-open');
        }

        function setInert(value) {
            if (!drawer.matches) { return; }
            if (value) {
                sidebar.removeAttribute('inert');
                sidebar.removeAttribute('aria-hidden');
            } else {
                sidebar.setAttribute('inert', '');
                sidebar.setAttribute('aria-hidden', 'true');
            }
        }

        function syncOpeners(value) {
            Array.prototype.forEach.call(openers, function (button) {
                button.setAttribute('aria-expanded', value ? 'true' : 'false');
            });
        }

        function open() {
            lastFocused = doc.activeElement;
            sidebar.classList.add('is-open');
            setInert(false);
            syncOpeners(true);
            if (scrim) { scrim.hidden = false; }
            doc.body.style.overflow = 'hidden';

            var closeBtn = sidebar.querySelector('.sidebar__close');
            if (closeBtn) { closeBtn.focus(); }
        }

        function close(restoreFocus) {
            if (!isOpen()) { return; }
            sidebar.classList.remove('is-open');
            setInert(true);
            syncOpeners(false);
            if (scrim) { scrim.hidden = true; }
            doc.body.style.overflow = '';
            if (restoreFocus !== false && lastFocused && lastFocused.focus) { lastFocused.focus(); }
        }

        // Leaving the narrow breakpoint reveals the rail, so drop the state.
        function handleBreakpoint(event) {
            if (event.matches) {
                setInert(true);
                syncOpeners(false);
                if (scrim) { scrim.hidden = true; }
                doc.body.style.overflow = '';
                sidebar.classList.remove('is-open');
            } else {
                setInert(false);
                sidebar.removeAttribute('aria-hidden');
                if (scrim) { scrim.hidden = true; }
                doc.body.style.overflow = '';
            }
        }

        if (typeof drawer.addEventListener === 'function') {
            drawer.addEventListener('change', handleBreakpoint);
        } else if (typeof drawer.addListener === 'function') {
            drawer.addListener(handleBreakpoint);
        }

        doc.addEventListener('click', function (event) {
            var opener = event.target.closest('[data-sidebar-open]');
            if (opener) {
                event.preventDefault();
                isOpen() ? close() : open();
                return;
            }
            if (event.target.closest('[data-sidebar-close]')) {
                event.preventDefault();
                close();
            }
        });

        doc.addEventListener('keydown', function (event) {
            if (!isOpen()) { return; }

            if (event.key === 'Escape') {
                close();
                return;
            }

            // Trap Tab inside the open drawer.
            if (event.key === 'Tab') {
                var items = Array.prototype.filter.call(
                    sidebar.querySelectorAll(FOCUSABLE),
                    function (node) { return node.offsetParent !== null; }
                );
                if (items.length === 0) { return; }

                var first = items[0];
                var last = items[items.length - 1];

                if (event.shiftKey && doc.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && doc.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        });

        // Following a link inside the drawer should close it as well.
        sidebar.addEventListener('click', function (event) {
            if (event.target.closest('.nav__link') && isOpen()) {
                close(false);
            }
        });

        handleBreakpoint(drawer);
    }

    // -----------------------------------------------------------------
    // Error page recovery
    //
    // The error page is served under the same strict policy as the rest of
    // the application, which blocks inline script and therefore
    // "javascript:" URLs. The primary recovery control is a real button
    // here so it still works with scripting disabled, and the history hop
    // is what the button upgrades to.
    // -----------------------------------------------------------------

    function initErrorActions() {
        var fallback = doc.querySelector('[data-error-home]');
        var home = fallback ? fallback.getAttribute('data-error-home') : null;

        Array.prototype.forEach.call(
            doc.querySelectorAll('[data-action="history-back"]'),
            function (button) {
                button.addEventListener('click', function () {
                    // A landing page reached directly has no history to
                    // return to, so fall back to a known-good screen.
                    if (window.history.length > 1) {
                        window.history.back();
                    } else if (home) {
                        window.location.assign(home);
                    }
                });
            }
        );

        // Retrying a failed request re-submits the current URL by reloading
        // it. A cached response is not a concern here because the whole page
        // is a failure notice.
        Array.prototype.forEach.call(
            doc.querySelectorAll('[data-action="reload"]'),
            function (button) {
                button.addEventListener('click', function () {
                    window.location.reload();
                });
            }
        );
    }

    // -----------------------------------------------------------------
    // Theme
    // -----------------------------------------------------------------

    function initTheme() {
        var toggles = doc.querySelectorAll('[data-theme-toggle]');

        Array.prototype.forEach.call(toggles, function (button) {
            button.addEventListener('click', function () {
                var root = doc.documentElement;
                var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                root.setAttribute('data-theme', next);
                try {
                    localStorage.setItem('hagmah-theme', next);
                } catch (e) { /* private browsing: theme lasts this page only */ }
            });
        });

        // Follow the operating system while the visitor has not chosen.
        if (!window.matchMedia) { return; }

        var query = window.matchMedia('(prefers-color-scheme: dark)');
        var listener = function (event) {
            var stored = null;
            try { stored = localStorage.getItem('hagmah-theme'); } catch (e) { /* ignore */ }
            if (stored === null) {
                doc.documentElement.setAttribute('data-theme', event.matches ? 'dark' : 'light');
            }
        };

        if (typeof query.addEventListener === 'function') {
            query.addEventListener('change', listener);
        } else if (typeof query.addListener === 'function') {
            query.addListener(listener);
        }
    }

    // -----------------------------------------------------------------
    // Flash messages
    // -----------------------------------------------------------------

    function initFlashes() {
        var messages = doc.querySelectorAll('[data-flash]');

        Array.prototype.forEach.call(messages, function (message, index) {
            var close = message.querySelector('[data-flash-close]');
            if (close) {
                close.addEventListener('click', function () { message.remove(); });
            }

            // Success messages clear themselves; problems stay until read.
            if (message.classList.contains('flash--success')) {
                window.setTimeout(function () {
                    message.style.transition = 'opacity .3s ease';
                    message.style.opacity = '0';
                    window.setTimeout(function () { message.remove(); }, 320);
                }, 5000 + index * 400);
            }
        });
    }

    // -----------------------------------------------------------------
    // Confirmations
    //
    // Destructive buttons carry data-confirm. Without JavaScript the form
    // submits normally, so this is a convenience, not a safeguard — the
    // safeguard is that deletes are POST requests with a CSRF token.
    // -----------------------------------------------------------------

    function initConfirms() {
        doc.addEventListener('submit', function (event) {
            var form = event.target;
            if (!form.matches || !form.matches('.deletefrom')) { return; }

            var button = form.querySelector('[data-confirm]');
            if (!button) { return; }

            if (!window.confirm(button.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    }

    // -----------------------------------------------------------------
    // Password field
    // -----------------------------------------------------------------

    function initPasswordReveal() {
        var buttons = doc.querySelectorAll('[data-reveal-password]');

        Array.prototype.forEach.call(buttons, function (button) {
            button.addEventListener('click', function () {
                var input = doc.getElementById(button.getAttribute('data-reveal-password'));
                if (!input) { return; }

                var showing = input.type === 'text';
                input.type = showing ? 'password' : 'text';
                button.setAttribute('aria-pressed', showing ? 'false' : 'true');
                button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
            });
        });
    }

    // -----------------------------------------------------------------
    // User menu
    //
    // <details> already opens and closes; this adds closing on an outside
    // click, which is what people expect from a menu.
    // -----------------------------------------------------------------

    function initUserMenu() {
        var menus = doc.querySelectorAll('.usermenu');

        Array.prototype.forEach.call(menus, function (menu) {
            doc.addEventListener('click', function (event) {
                if (menu.open && !menu.contains(event.target)) {
                    menu.open = false;
                }
            });
        });
    }

    // -----------------------------------------------------------------
    // Filter selects submit their own form
    //
    // The change event is handled here rather than with an inline onchange
    // so that markup stays free of JavaScript and the form still works with
    // it disabled.
    // -----------------------------------------------------------------

    function initAutoSubmit() {
        doc.addEventListener('change', function (event) {
            var control = event.target;
            if (!control.matches || !control.matches('[data-auto-submit]')) { return; }
            if (control.form) { control.form.submit(); }
        });
    }

    // -----------------------------------------------------------------
    // Forms
    // -----------------------------------------------------------------

    // -----------------------------------------------------------------
    // Tables on small screens
    //
    // A wide data table is the one thing that cannot simply reflow. Below
    // 700px each row becomes a stacked block and the column headings are
    // carried on the cells as labels, so no data is hidden and nothing has
    // to be scrolled sideways. The label is a data attribute on the cell,
    // which the stylesheet reveals only in this mode.
    // -----------------------------------------------------------------

    function initTableLabels() {
        // Tables that already declare per-cell labels are left alone.
        var tables = doc.querySelectorAll('.tablewrap table:not([data-stacked])');

        Array.prototype.forEach.call(tables, function (table) {
            var headers = [];
            var headCells = table.querySelectorAll('thead th');

            Array.prototype.forEach.call(headCells, function (th) {
                headers.push((th.textContent || '').trim());
            });

            var rows = table.querySelectorAll('tbody tr');

            Array.prototype.forEach.call(rows, function (row) {
                var cells = row.querySelectorAll('td');

                Array.prototype.forEach.call(cells, function (cell, index) {
                    if (!headers[index] || cell.getAttribute('data-label')) { return; }
                    cell.setAttribute('data-label', headers[index]);
                });
            });

            table.setAttribute('data-stacked', 'ready');
        });
    }

    function initValidation() {
        // Stop a double submit creating the record twice.
        doc.addEventListener('submit', function (event) {
            var form = event.target;
            if (!form.matches || !form.matches('form[data-once]')) { return; }
            if (form.dataset.submitted === '1') {
                event.preventDefault();
                return;
            }
            form.dataset.submitted = '1';

            var button = form.querySelector('button[type="submit"]');
            if (button) {
                button.setAttribute('aria-disabled', 'true');
                button.style.opacity = '0.7';
            }
        });

        // Trim text fields on blur so a stray space never reaches the database.
        doc.addEventListener('blur', function (event) {
            var field = event.target;
            if (!field.matches || !field.matches('input[type="text"], input[type="email"], input[type="tel"]')) { return; }
            field.value = field.value.replace(/^\s+|\s+$/g, '');
        }, true);
    }

    onReady(function () {
        initSidebar();
        initTheme();
        initFlashes();
        initConfirms();
        initPasswordReveal();
        initUserMenu();
        initAutoSubmit();
        initValidation();
        initErrorActions();
        initTableLabels();
    });
})();
