/* Set the pinned rail width before styles paint to avoid a layout flash. */
(function () {
    var root = document.documentElement;
    var mobile = window.matchMedia('(max-width: 767px)').matches;
    var stored = null;
    try { stored = localStorage.getItem('hagmah-sidebar-pinned-v2'); } catch (e) { /* storage may be disabled */ }
    window.hagmahSidebarStored = stored;
    var expanded = stored === 'expanded';
    root.setAttribute('data-sidebar', mobile ? 'mobile' : (expanded ? 'expanded' : 'collapsed'));
    root.style.setProperty('--sidebar-w', mobile ? '0px' : (expanded ? '256px' : '72px'));
})();
