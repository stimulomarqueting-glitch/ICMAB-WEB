/**
 * Audiences ("Who it's for") — port of the <script> in
 * src/components/Audiences.astro. Hover/focus/tap makes a panel active;
 * the spotlight tracks the pointer inside the hovered panel. Until the
 * visitor interacts the active panel cycles every few seconds (desktop,
 * motion-safe only), pausing while hovered and when the row is off-screen.
 * Every [data-audiences] row on the page runs on its own; nothing happens
 * when the section is absent. Content never depends on this script: the
 * first panel is rendered active and the rest stays readable.
 */
(function () {
  'use strict';

  function init(root) {
    if (!root || root.getAttribute('data-audiences-ready')) return;
    var panels = Array.prototype.slice.call(root.querySelectorAll('.aud__panel'));
    if (!panels.length) return;
    root.setAttribute('data-audiences-ready', '1');

    var interval = Number(root.getAttribute('data-interval')) || 3800;
    var desktop = window.matchMedia('(min-width: 64rem)');
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var active = 0;
    var engaged = false; // set once the visitor picks a panel; stops the cycling
    var timer;

    panels.forEach(function (p, k) {
      if (p.hasAttribute('data-active')) active = k;
    });

    function activate(i) {
      active = i;
      panels.forEach(function (p, k) {
        if (k === i) p.setAttribute('data-active', '');
        else p.removeAttribute('data-active');
      });
    }

    function stop() {
      if (timer !== undefined) window.clearInterval(timer);
      timer = undefined;
    }

    function start() {
      stop();
      if (reduce || engaged || !desktop.matches || document.hidden) return;
      timer = window.setInterval(function () {
        activate((active + 1) % panels.length);
      }, interval);
    }

    panels.forEach(function (p, i) {
      var pick = function () {
        engaged = true;
        stop();
        activate(i);
      };
      p.addEventListener('pointerenter', pick);
      p.addEventListener('focus', pick);
      p.addEventListener('click', pick);
      p.addEventListener('pointermove', function (e) {
        var r = p.getBoundingClientRect();
        if (!r.width || !r.height) return;
        p.style.setProperty('--mx', ((e.clientX - r.left) / r.width) * 100 + '%');
        p.style.setProperty('--my', ((e.clientY - r.top) / r.height) * 100 + '%');
      });
    });

    document.addEventListener('visibilitychange', function () {
      if (document.hidden) stop();
      else start();
    });
    if (desktop.addEventListener) desktop.addEventListener('change', start);
    else if (desktop.addListener) desktop.addListener(start);

    if ('IntersectionObserver' in window) {
      new IntersectionObserver(
        function (entries) {
          var e = entries[entries.length - 1];
          if (e && e.isIntersecting) start();
          else stop();
        },
        { threshold: 0.4 }
      ).observe(root);
    } else {
      start();
    }
  }

  function boot() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-audiences]'), init);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
