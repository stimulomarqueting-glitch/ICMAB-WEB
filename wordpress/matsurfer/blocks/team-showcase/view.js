/**
 * Team showcase (port of the TeamShowcase.astro script): avatar click →
 * swap portrait, bio and meta line. Rotates on its own every
 * data-interval ms (5.5s), pauses while hovered or focused, off-screen or
 * when the tab is hidden, restarts the clock after a manual pick, and
 * never autoplays under prefers-reduced-motion. Arrow keys move through
 * the picker. Works for any number of showcases on the page (or none).
 */
(function () {
  'use strict';

  function toArray(list) {
    return Array.prototype.slice.call(list || []);
  }

  function init(root) {
    if (!root || root.matsShowcase) return;
    root.matsShowcase = true; // once per section, even if this script runs twice

    var persons = toArray(root.querySelectorAll('.show__person'));
    var bios = toArray(root.querySelectorAll('.show__bio'));
    var metas = toArray(root.querySelectorAll('.show__meta'));
    var buttons = toArray(root.querySelectorAll('.show__avatar'));
    var count = buttons.length;
    if (!count) return;
    var interval = Number(root.getAttribute('data-interval')) || 5500;
    var reduceMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);

    var indexOf = function (el) {
      return Number(el.getAttribute('data-index'));
    };

    var active = 0;
    for (var i = 0; i < count; i++) {
      if (buttons[i].getAttribute('aria-pressed') === 'true') {
        active = i;
        break;
      }
    }

    var byIndex = function (list, index) {
      for (var j = 0; j < list.length; j++) {
        if (indexOf(list[j]) === index) return list[j];
      }
      return null;
    };

    var setActive = function (el, on) {
      if (on) el.setAttribute('data-active', '');
      else el.removeAttribute('data-active');
    };

    /* restart the settle animation by toggling the class between frames */
    var refade = function (el) {
      if (!el) return;
      el.classList.remove('is-in');
      void el.offsetWidth;
      el.classList.add('is-in');
    };

    var select = function (index) {
      if (index === active || index < 0 || index >= count) return;
      active = index;
      persons.forEach(function (p) { setActive(p, indexOf(p) === index); });
      bios.forEach(function (b) { setActive(b, indexOf(b) === index); });
      metas.forEach(function (m) { setActive(m, indexOf(m) === index); });
      buttons.forEach(function (b) { b.setAttribute('aria-pressed', String(indexOf(b) === index)); });
      refade(byIndex(bios, index));
      var meta = byIndex(metas, index);
      refade(meta ? meta.querySelector('.show__name') : null);
    };

    /* ── autoplay ───────────────────────────────────────────────────── */
    var timer;
    var paused = false;
    var visible = true;

    var stop = function () {
      if (timer !== undefined) window.clearInterval(timer);
      timer = undefined;
    };
    var start = function () {
      stop();
      if (reduceMotion || paused || !visible || document.hidden || count < 2) return;
      timer = window.setInterval(function () {
        select((active + 1) % count);
      }, interval);
    };

    buttons.forEach(function (b) {
      b.addEventListener('click', function () {
        select(indexOf(b));
        start(); // a manual pick restarts the clock from that person
      });
    });

    var picker = root.querySelector('.show__picker');
    if (picker) {
      picker.addEventListener('keydown', function (e) {
        var key = e.key;
        if (key !== 'ArrowRight' && key !== 'ArrowLeft') return;
        e.preventDefault();
        var next = (active + (key === 'ArrowRight' ? 1 : -1) + count) % count;
        select(next);
        var target = buttons[next];
        if (target) target.focus();
        start();
      });
    }

    var pause = function () {
      paused = true;
      stop();
    };
    var resume = function () {
      paused = false;
      start();
    };
    root.addEventListener('pointerenter', pause);
    root.addEventListener('pointerleave', resume);
    root.addEventListener('focusin', pause);
    root.addEventListener('focusout', function (e) {
      if (!root.contains(e.relatedTarget)) resume();
    });
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) stop();
      else start();
    });

    if ('IntersectionObserver' in window) {
      new IntersectionObserver(
        function (entries) {
          var entry = entries[0];
          if (!entry) return;
          visible = entry.isIntersecting;
          if (visible) start();
          else stop();
        },
        { threshold: 0.35 }
      ).observe(root);
    } else {
      start();
    }
  }

  function boot() {
    toArray(document.querySelectorAll('[data-showcase]')).forEach(init);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
