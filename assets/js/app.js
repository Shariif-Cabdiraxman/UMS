/**
 * Campus Manager — Campus Management System
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
        var root = doc.documentElement;
        var toggle = doc.querySelector('[data-sidebar-toggle]');
        var finePointer = window.matchMedia('(hover: hover) and (pointer: fine)');
        var mobileQuery = window.matchMedia('(max-width: 767px)');
        var focusable = 'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])';
        var pinned = root.getAttribute('data-sidebar') === 'expanded';
        var mobileOpen = false;
        var openTimer = 0;
        var closeTimer = 0;
        var lastFocused = null;

        function setWidth(width) { root.style.setProperty('--sidebar-w', width); }
        function sync() {
            var state = mobileQuery.matches ? 'mobile' : (pinned ? 'expanded' : 'collapsed');
            root.setAttribute('data-sidebar', state);
            if (!mobileQuery.matches) { setWidth(pinned ? '256px' : '72px'); }
            if (toggle) {
                toggle.setAttribute('aria-expanded', mobileQuery.matches ? (mobileOpen ? 'true' : 'false') : (pinned ? 'true' : 'false'));
                toggle.setAttribute('aria-label', mobileQuery.matches ? (mobileOpen ? 'Close navigation' : 'Open navigation') : (pinned ? 'Collapse navigation' : 'Expand navigation'));
            }
            sidebar.classList.toggle('is-open', mobileOpen && mobileQuery.matches);
            if (scrim) { scrim.hidden = !(mobileOpen && mobileQuery.matches); }
            if (mobileQuery.matches && !mobileOpen) {
                sidebar.setAttribute('inert', '');
                sidebar.setAttribute('aria-hidden', 'true');
            } else {
                sidebar.removeAttribute('inert');
                sidebar.removeAttribute('aria-hidden');
            }
            doc.body.style.overflow = mobileOpen && mobileQuery.matches ? 'hidden' : '';
        }
        function clearPeekTimers() {
            window.clearTimeout(openTimer); window.clearTimeout(closeTimer);
        }
        function peekOpen() {
            if (!mobileQuery.matches && !pinned && finePointer.matches) {
                root.setAttribute('data-sidebar', 'peek');
            }
        }
        function schedulePeekOpen() {
            if (!mobileQuery.matches && !pinned && finePointer.matches) {
                window.clearTimeout(closeTimer);
                window.clearTimeout(openTimer);
                openTimer = window.setTimeout(peekOpen, 135);
            }
        }
        function schedulePeekClose() {
            if (!pinned && root.getAttribute('data-sidebar') === 'peek') {
                window.clearTimeout(openTimer);
                window.clearTimeout(closeTimer);
                closeTimer = window.setTimeout(function () {
                    if (sidebar.matches(':hover') || sidebar.contains(doc.activeElement)) { return; }
                    root.setAttribute('data-sidebar', 'collapsed');
                }, 100);
            }
        }
        sidebar.addEventListener('mouseenter', schedulePeekOpen);
        sidebar.addEventListener('mouseleave', schedulePeekClose);
        sidebar.addEventListener('focusin', function () { clearPeekTimers(); peekOpen(); });
        sidebar.addEventListener('focusout', function (event) {
            if (!sidebar.contains(event.relatedTarget)) { schedulePeekClose(); }
        });
        if (toggle) {
            toggle.addEventListener('click', function () {
                clearPeekTimers();
                if (mobileQuery.matches) {
                    if (!mobileOpen) { lastFocused = toggle; }
                    mobileOpen = !mobileOpen;
                    sync();
                    if (mobileOpen) { (sidebar.querySelector('.sidebar__close') || sidebar.querySelector('a')).focus(); }
                    else if (lastFocused) { lastFocused.focus(); }
                    return;
                }
                pinned = !pinned;
                window.hagmahSidebarStored = pinned ? 'expanded' : 'collapsed';
                try { localStorage.setItem('hagmah-sidebar-pinned-v2', window.hagmahSidebarStored); } catch (e) { /* keep current page functional */ }
                sync();
            });
        }
        if (scrim) { scrim.addEventListener('click', function () { mobileOpen = false; sync(); if (toggle) { toggle.focus(); } }); }
        sidebar.addEventListener('click', function (event) {
            if (event.target.closest('[data-sidebar-close]')) { mobileOpen = false; sync(); if (toggle) { toggle.focus(); } }
            if (event.target.closest('.nav__link') && mobileOpen) { mobileOpen = false; sync(); }
        });
        doc.addEventListener('keydown', function (event) {
            if (event.key === '[' && !mobileQuery.matches && !/INPUT|TEXTAREA|SELECT/.test(doc.activeElement.tagName)) {
                event.preventDefault(); if (toggle) { toggle.click(); }
            }
            if (!mobileOpen || !mobileQuery.matches) { return; }
            if (event.key === 'Escape') { mobileOpen = false; sync(); if (toggle) { toggle.focus(); } return; }
            if (event.key === 'Tab') {
                var items = Array.prototype.filter.call(sidebar.querySelectorAll(focusable), function (node) { return node.offsetParent !== null; });
                if (!items.length) { return; }
                if (event.shiftKey && doc.activeElement === items[0]) { event.preventDefault(); items[items.length - 1].focus(); }
                else if (!event.shiftKey && doc.activeElement === items[items.length - 1]) { event.preventDefault(); items[0].focus(); }
            }
        });
        function onResize() {
            clearPeekTimers(); mobileOpen = false;
            var wasPeek = root.getAttribute('data-sidebar') === 'peek';
            if (window.hagmahSidebarStored == null && !mobileQuery.matches) { pinned = window.innerWidth >= 1280; }
            sync();
            if (wasPeek && !mobileQuery.matches && !pinned && finePointer.matches) { root.setAttribute('data-sidebar', 'peek'); }
        }
        if (typeof mobileQuery.addEventListener === 'function') { mobileQuery.addEventListener('change', onResize); }
        else { mobileQuery.addListener(onResize); }
        window.addEventListener('resize', onResize);
        sidebar.addEventListener('transitionend', function (event) {
            if (event.propertyName === 'width' && window.CustomEvent) { window.dispatchEvent(new Event('resize')); }
        });
        sync();
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
    // Search
    //
    // The field already submits as a normal GET form. This only adds the
    // conveniences people expect now: a clear button, a platform-aware
    // shortcut hint, Ctrl/Cmd+K and "/" to jump into the global search.
    // -----------------------------------------------------------------

    function initSearch() {
        var form = doc.querySelector('.topbar__search');
        if (!form) { return; }

        var input = form.querySelector('input[type="search"]');
        var clear = form.querySelector('[data-search-clear]');
        var hint = form.querySelector('.searchkbd');
        if (!input) { return; }

        function sync() {
            form.classList.toggle('has-value', input.value !== '');
        }

        function clearInput() {
            input.value = '';
            sync();
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.focus();
        }

        input.addEventListener('input', sync);
        sync();

        if (clear) {
            clear.addEventListener('click', clearInput);
        }

        if (hint && /Mac|iPhone|iPad|iPod/.test(navigator.platform || '')) {
            hint.textContent = '⌘K';
        }

        doc.addEventListener('keydown', function (event) {
            var target = event.target;
            var tag = target && target.tagName ? target.tagName.toLowerCase() : '';
            var typing = tag === 'input' || tag === 'textarea' || tag === 'select' || (target && target.isContentEditable);

            if ((event.key === '/' && !typing) || ((event.metaKey || event.ctrlKey) && String(event.key).toLowerCase() === 'k')) {
                event.preventDefault();
                input.focus();
                input.select();
                return;
            }

            if (event.key === 'Escape' && doc.activeElement === input) {
                if (input.value !== '') {
                    event.preventDefault();
                    clearInput();
                } else {
                    input.blur();
                }
            }
        });
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
        initSearch();
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
