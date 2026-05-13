<script>
    (function () {
        'use strict';

        // Mobile menu toggle
        var toggle = document.querySelector('[data-mkt-nav-toggle]');
        var nav    = document.querySelector('[data-mkt-nav]');
        if (toggle && nav) {
            toggle.addEventListener('click', function () {
                nav.classList.toggle('is-open');
                var open = nav.classList.contains('is-open');
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        }

        // Smooth-scroll for in-page anchor links
        document.querySelectorAll('a[href^="#"]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                var id = a.getAttribute('href');
                if (id && id.length > 1) {
                    var el = document.querySelector(id);
                    if (el) {
                        e.preventDefault();
                        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }
            });
        });
    })();
</script>
