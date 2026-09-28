/* ==========================================================================
   Service worker

   This exists so the phone version can be installed to a home screen and start
   instantly, and it is deliberately narrow about what it keeps.

   Two rules:

     1. Nothing signed in is ever stored. Every page in this application is
        behind a session, so caching an HTML response would put one person's
        records on another person's screen. Navigations go to the network and
        nowhere else.

     2. Only the application's own stylesheets, scripts and icons are cached.
        They are the same for everyone, they change when the application is
        deployed, and the version below is what makes a change take effect.

   Anything that is not one of those is passed straight through.
   ========================================================================== */

'use strict';

// Bumped whenever a static asset changes, so the old cache is dropped on the
// next activation rather than lingering.
var CACHE = 'ums-static-v1';

var PRECACHE = [
    'assets/css/style.css',
    'assets/css/mobile.css',
    'assets/js/theme.js',
    'assets/js/app.js',
    'assets/js/mobile.js',
    'assets/js/charts.js',
    'assets/img/favicon.svg',
    'assets/img/app-icon.svg',
    'manifest.webmanifest'
];

/** The application root, which is this worker's scope. */
function base() {
    return new URL(self.registration.scope);
}

function assetUrl(path) {
    return new URL(path, base()).toString();
}

// ---------------------------------------------------------------------
// Install: fetch everything once, up front, so the first visit after
// installation is already warm.
// ---------------------------------------------------------------------

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE)
            .then(function (cache) {
                // addAll is all-or-nothing: one 404 and the worker never
                // installs, which would leave the app quietly doing nothing.
                // Each file is added on its own instead, so a missing icon
                // costs a cache entry rather than the whole worker.
                return Promise.all(PRECACHE.map(function (path) {
                    return cache.add(assetUrl(path)).catch(function () { return null; });
                }));
            })
            .then(function () { return self.skipWaiting(); })
    );
});

// ---------------------------------------------------------------------
// Activate: throw away the previous version's cache.
// ---------------------------------------------------------------------

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys()
            .then(function (keys) {
                return Promise.all(keys.map(function (key) {
                    return key === CACHE ? null : caches.delete(key);
                }));
            })
            .then(function () { return self.clients.claim(); })
    );
});

// ---------------------------------------------------------------------
// Fetch
// ---------------------------------------------------------------------

/**
 * The page shown when the phone is offline and a page is asked for.
 *
 * Small, inline and monochrome, because it has to work with no network, no
 * cached stylesheet and nothing but what is in this file.
 */
var OFFLINE_PAGE = '<!doctype html><html lang="en"><head><meta charset="utf-8">'
    + '<meta name="viewport" content="width=device-width, initial-scale=1">'
    + '<meta name="robots" content="noindex">'
    + '<title>Offline</title><style>'
    + 'body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f4f4f4;color:#1a1a1a;'
    + 'font:16px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;padding:1.5rem}'
    + 'div{max-width:24rem;text-align:center}'
    + 'h1{font-size:1.15rem;margin:0 0 .4rem}p{margin:0;color:#5c5c5c;font-size:.9rem}'
    + 'a{display:inline-block;margin-top:1rem;padding:.6rem 1.1rem;background:#111;color:#fff;'
    + 'text-decoration:none;border-radius:8px;font-weight:600}'
    + '@media (prefers-color-scheme:dark){body{background:#101010;color:#f0f0f0}p{color:#a8a8a8}'
    + 'a{background:#f0f0f0;color:#111}}'
    + '</style></head><body><div>'
    + '<h1>You are offline</h1>'
    + '<p>This page needs the university record, so it can only be shown while you are connected.</p>'
    + '<a href="">Try again</a>'
    + '</div></body></html>';

self.addEventListener('fetch', function (event) {
    var request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    var url;
    try {
        url = new URL(request.url);
    } catch (e) {
        return;
    }

    if (url.origin !== self.location.origin) {
        return;
    }

    if (!url.pathname.startsWith(base().pathname)) {
        return;
    }

    // Rule 1: a page is never cached.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(function () {
                return new Response(OFFLINE_PAGE, {
                    status: 503,
                    headers: { 'Content-Type': 'text/html; charset=utf-8' }
                });
            })
        );
        return;
    }

    // Rule 2: only the known static assets, served from cache first.
    if (PRECACHE.indexOf(url.pathname.slice(base().pathname.length)) === -1) {
        return;
    }

    event.respondWith(
        caches.match(request).then(function (hit) {
            if (hit) {
                return hit;
            }

            return fetch(request).then(function (response) {
                // Only a complete, successful response is worth keeping.
                if (response && response.status === 200 && response.type === 'basic') {
                    var copy = response.clone();
                    caches.open(CACHE).then(function (cache) {
                        cache.put(request, copy);
                    });
                }

                return response;
            });
        })
    );
});
