/**
 * How it works — port of the <script> in src/pages/[...lang]/product.astro.
 * Branded video facade: the first click removes the poster (isotype + play
 * button) and starts playback. Every video box on the page is wired on its
 * own (or none). It only reacts to a click, so it runs the same with
 * prefers-reduced-motion.
 */
(function () {
  'use strict';

  function init(box) {
    if (!box || box.getAttribute('data-tech-ready')) return;
    var poster = box.querySelector('.tech__poster');
    var video = box.querySelector('.tech__video');
    if (!poster || !video) return;
    box.setAttribute('data-tech-ready', '1');

    poster.addEventListener('click', function () {
      if (poster.parentNode) poster.parentNode.removeChild(poster);
      var played = video.play();
      if (played && typeof played.catch === 'function') {
        played.catch(function () {});
      }
      try {
        video.focus({ preventScroll: true });
      } catch (e) {}
    });
  }

  function boot() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-tech-video]'), init);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
