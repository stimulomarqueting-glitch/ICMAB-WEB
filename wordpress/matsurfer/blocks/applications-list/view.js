/**
 * Applications list — scroll-spy for the sticky in-page nav (port of the
 * <script> in src/pages/[...lang]/applications.astro): the pill of the
 * application block in view gets aria-current. Each nav is wired to the
 * blocks its own pills point at (data-target = block id), so the list can
 * appear more than once — or not at all — on a page. It only toggles an
 * attribute (no motion), so it runs the same with prefers-reduced-motion.
 */
(function () {
  'use strict';

  function init(nav) {
    if (!nav || nav.getAttribute('data-apps-ready')) return;
    nav.setAttribute('data-apps-ready', '1');

    var pills = Array.prototype.slice.call(nav.querySelectorAll('[data-target]'));
    var cards = [];
    pills.forEach(function (p) {
      var card = document.getElementById(p.getAttribute('data-target') || '');
      if (card && cards.indexOf(card) === -1) cards.push(card);
    });
    if (!pills.length || !cards.length || !('IntersectionObserver' in window)) return;

    var setCurrent = function (id) {
      pills.forEach(function (p) {
        if (p.getAttribute('data-target') === id) p.setAttribute('aria-current', '');
        else p.removeAttribute('aria-current');
      });
    };

    var io = new IntersectionObserver(
      function (entries) {
        var hits = entries
          .filter(function (e) {
            return e.isIntersecting;
          })
          .sort(function (a, b) {
            return b.intersectionRatio - a.intersectionRatio;
          });
        if (hits[0]) setCurrent(hits[0].target.id);
      },
      { rootMargin: '-30% 0px -55% 0px', threshold: [0, 0.25, 0.5, 0.75, 1] }
    );
    cards.forEach(function (c) {
      io.observe(c);
    });
    pills.forEach(function (p) {
      p.addEventListener('click', function () {
        setCurrent(p.getAttribute('data-target') || '');
      });
    });
  }

  function boot() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-apps-nav]'), init);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
