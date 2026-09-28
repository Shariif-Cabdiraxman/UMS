/* ==========================================================================
   Mobile behaviour

   Enhancement for the phone shell, and the only part of it that is not already
   a normal request. Everything the mobile screens do — searching, filtering,
   saving, deleting — is an ordinary server round trip that works with this file
   blocked. What is here removes friction a phone introduces:

     1. Telling the server how wide the screen is, so a phone-width window is
        given the phone shell and a desktop window is not
     2. Keeping the sticky save bar clear of the on-screen keyboard
     3. Registering the service worker, so the app can be installed

   No screen depends on any of it.
   ========================================================================== */
(function () {
    'use strict';

    var doc = document;
    var COOKIE_WIDTH = 'ums_viewport';

    function onReady(fn) {
        if (doc.readyState !== 'loading') {
            fn();
        } else {
            doc.addEventListener('DOMContentLoaded', fn);
        }
    }

    // -----------------------------------------------------------------
    // 1. Reporting the viewport
    //
    // The server has to choose a shell before it can send anything, and the
    // user agent is a poor proxy: a narrow desktop window and a tablet in
    // desktop mode both claim to be a computer. Reporting the measured width
    // lets it choose on what the reader will actually see.
    // -----------------------------------------------------------------

    function reportViewport() {
        var width = window.innerWidth || doc.documentElement.clientWidth;

        if (!width) { return; }

        try {
            // A day is long enough for one session and short enough that a
            // resized desktop window is re-measured the next morning.
            doc.cookie = COOKIE_WIDTH + '=' + Math.round(width)
                + ';path=/;max-age=86400;samesite=lax';
        } catch (e) { /* cookies unavailable: the server falls back to the agent */ }
    }

    // -----------------------------------------------------------------
    // 2. A save bar that gets out of the way of the keyboard
    //
    // The action bar is sticky at the foot of a form so the save button does
    // not have to be scrolled back up to. On a phone the keyboard covers the
    // bottom of the screen, which would hide the very button it is there to
    // reach, so the bar is dropped while the keyboard is up.
    // -----------------------------------------------------------------

    function handleKeyboard() {
        var actions = doc.querySelector('.formactions');
        var viewport = window.visualViewport;

        if (!actions || !viewport) { return; }

        function update() {
            // How much of the layout viewport the keyboard is covering.
            var covered = window.innerHeight - viewport.height - viewport.offsetTop;

            actions.classList.toggle('is-keyboarded', covered > 120);
        }

        viewport.addEventListener('resize', update);
        viewport.addEventListener('scroll', update);
    }

    // -----------------------------------------------------------------
    // 3. Installability
    //
    // Registered only where it is supported, and only on the phone shell, so
    // a desktop reader is never prompted about an app they did not ask for.
    // updateViaCache bypasses the HTTP cache for the worker script itself,
    // which is what stops a stale worker from outliving a deployment.
    // -----------------------------------------------------------------

    function registerServiceWorker() {
        if (!('serviceWorker' in navigator)) { return; }

        var meta = doc.querySelector('meta[name="app-base"]');
        if (!meta) { return; }

        var base = meta.getAttribute('content') || '/';

        navigator.serviceWorker.register(base + 'sw.js', { scope: base, updateViaCache: 'none' })
            .catch(function () { /* the app works perfectly well without it */ });
    }

    onReady(function () {
        reportViewport();
        handleKeyboard();
        registerServiceWorker();
    });
})();
