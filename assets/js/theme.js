/**
 * Applies the saved theme before the page is painted.
 *
 * This is deliberately a blocking script in <head> rather than part of the
 * deferred bundle: it has to run before the first paint or a dark-mode user
 * gets a white flash. It is the one script the document does not defer, which
 * is what lets the Content-Security-Policy keep script-src at 'self' with no
 * inline exceptions.
 */
(function () {
    var key = 'hagmah-theme';
    var theme;

    try {
        theme = localStorage.getItem(key);
    } catch (e) {
        theme = null;
    }

    if (theme !== 'light' && theme !== 'dark') {
        theme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
            ? 'dark'
            : 'light';
    }

    document.documentElement.setAttribute('data-theme', theme);
})();
