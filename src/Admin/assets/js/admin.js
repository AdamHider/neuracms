/* Small admin behaviours that Bootstrap does not cover. */
(function () {
    'use strict';

    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('[data-admin-toggle="nav"]');
        if (toggle) {
            var nav = document.getElementById('admin-nav');
            if (nav) {
                nav.classList.toggle('is-open');
            }
            return;
        }

        // Close the mobile sidebar when a link is followed.
        var link = event.target.closest('.admin__nav-link');
        if (link) {
            var open = document.getElementById('admin-nav');
            if (open) {
                open.classList.remove('is-open');
            }
        }
    });
})();
