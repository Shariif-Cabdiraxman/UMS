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
    // -----------------------------------------------------------------

    function initSidebar() {
        var sidebar = doc.getElementById('sidebar');
        var scrim = doc.querySelector('.scrim');
        if (!sidebar) { return; }

        var lastFocused = null;

        function open() {
            lastFocused = doc.activeElement;
            sidebar.classList.add('is-open');
            if (scrim) { scrim.hidden = false; }
            doc.body.style.overflow = 'hidden';
            var closeBtn = sidebar.querySelector('.sidebar__close');
            if (closeBtn) { closeBtn.focus(); }
        }

        function close() {
            sidebar.classList.remove('is-open');
            if (scrim) { scrim.hidden = true; }
            doc.body.style.overflow = '';
            if (lastFocused && lastFocused.focus) { lastFocused.focus(); }
        }

        doc.addEventListener('click', function (event) {
            var opener = event.target.closest('[data-sidebar-open]');
            if (opener) {
                event.preventDefault();
                open();
                return;
            }
            if (event.target.closest('[data-sidebar-close]')) {
                event.preventDefault();
                close();
            }
        });

        doc.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && sidebar.classList.contains('is-open')) {
                close();
            }
        });

        // Following a link inside the drawer should close it as well.
        sidebar.addEventListener('click', function (event) {
            if (event.target.closest('.nav__link') && sidebar.classList.contains('is-open')) {
                close();
            }
        });
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

    // -----------------------------------------------------------------
    // Table checkbox selection
    // -----------------------------------------------------------------

    function initSelectAll() {
        doc.addEventListener('change', function (event) {
            var master = event.target;
            if (!master.matches || !master.matches('[data-select-all]')) { return; }

            var scope = doc.querySelector(master.getAttribute('data-select-all'));
            if (!scope) { return; }

            Array.prototype.forEach.call(
                scope.querySelectorAll('input[type="checkbox"][name]'),
                function (box) { box.checked = master.checked; }
            );
        });
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
        initSelectAll();
    });
})();
