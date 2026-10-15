/**
 * Page hero — port of the <script> in src/components/PageHero.astro.
 * The photo plate tilts with the pointer anywhere over the hero (the hero
 * carries --rx/--ry, the plate reads them) and eases back on leave.
 * Works for any number of heroes on the page (or none); does nothing when
 * the visitor prefers reduced motion.
 */
(function () {
  'use strict';

  function init() {
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    var heroes = document.querySelectorAll('[data-phero]');
    Array.prototype.forEach.call(heroes, function (hero) {
      if (hero.matsPheroReady) return;
      var plate = hero.querySelector('.phero__plate');
      if (!plate) return;
      hero.matsPheroReady = true;

      hero.addEventListener('pointermove', function (e) {
        var r = hero.getBoundingClientRect();
        if (!r.width || !r.height) return;
        var nx = (e.clientX - r.left) / r.width - 0.5;
        var ny = (e.clientY - r.top) / r.height - 0.5;
        hero.style.setProperty('--ry', (nx * 8).toFixed(2) + 'deg');
        hero.style.setProperty('--rx', (-ny * 6).toFixed(2) + 'deg');
      });

      hero.addEventListener('pointerleave', function () {
        hero.style.setProperty('--rx', '0deg');
        hero.style.setProperty('--ry', '0deg');
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
