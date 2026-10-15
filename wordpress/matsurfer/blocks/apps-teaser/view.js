/**
 * Home applications teaser — a chip under the isotype lights up its field
 * in the figure (port of the <script> in src/pages/[...lang]/index.astro).
 * Each chip carries data-field = application slug; each callout of the
 * figure carries the same data-field. Pointer and keyboard focus both
 * work. Every teaser on the page is wired on its own (chips only light
 * the figure of their own section). It only switches colours, so it runs
 * the same with prefers-reduced-motion (the CSS keeps it instant there).
 */
(function () {
  'use strict';

  function setHot(callouts, field, on) {
    for (var i = 0; i < callouts.length; i++) {
      var c = callouts[i];
      if (c.getAttribute('data-field') !== field) continue;
      if (on) c.setAttribute('data-hot', '');
      else c.removeAttribute('data-hot');
    }
  }

  function init(list) {
    if (!list || list.getAttribute('data-apps-ready')) return;
    list.setAttribute('data-apps-ready', '1');
    var root = (list.closest && list.closest('section')) || list.parentNode || document;
    var callouts = root.querySelectorAll('[data-isotype] .iso__callout[data-field]');
    if (!callouts.length) return;
    var chips = list.querySelectorAll('[data-field]');
    Array.prototype.forEach.call(chips, function (chip) {
      var field = chip.getAttribute('data-field');
      if (!field) return;
      var on = function () {
        setHot(callouts, field, true);
      };
      var off = function () {
        setHot(callouts, field, false);
      };
      chip.addEventListener('pointerenter', on);
      chip.addEventListener('pointerleave', off);
      chip.addEventListener('focus', on);
      chip.addEventListener('blur', off);
    });
  }

  function boot() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-apps-links]'), init);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
