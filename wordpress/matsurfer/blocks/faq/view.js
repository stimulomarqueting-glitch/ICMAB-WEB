/**
 * FAQ accordion — port of the <script> in src/components/Accordion.astro.
 * Open: unhide the panel, then grow it on the next frame (the CSS animates
 * its grid row); close: shrink, then hide when the transition ends.
 * Every accordion on the page is wired on its own (or none). With
 * prefers-reduced-motion the panel opens and closes at once.
 */
(function () {
  'use strict';

  function reduced() {
    return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  }

  function toggle(btn, root) {
    var expanded = btn.getAttribute('aria-expanded') === 'true';
    var id = btn.getAttribute('aria-controls') || '';
    var panel = id ? document.getElementById(id) : null;
    var item = btn.closest ? btn.closest('.acc__item') : null;
    btn.setAttribute('aria-expanded', String(!expanded));
    if (!panel || !item || !root.contains(panel)) return;

    if (expanded) {
      item.removeAttribute('data-open');
      if (reduced()) {
        panel.hidden = true;
        return;
      }
      var done = false;
      var hide = function () {
        if (done) return;
        done = true;
        panel.removeEventListener('transitionend', hide);
        /* reopened meanwhile: keep it visible */
        if (item.hasAttribute('data-open')) return;
        panel.hidden = true;
      };
      panel.addEventListener('transitionend', hide);
      window.setTimeout(hide, 420); // safety net if no transition fires
    } else {
      panel.hidden = false;
      if (reduced()) {
        item.setAttribute('data-open', '');
        return;
      }
      window.requestAnimationFrame(function () {
        window.requestAnimationFrame(function () {
          if (btn.getAttribute('aria-expanded') === 'true') item.setAttribute('data-open', '');
        });
      });
    }
  }

  function init(root) {
    if (!root || root.getAttribute('data-acc-ready')) return;
    root.setAttribute('data-acc-ready', '1');
    Array.prototype.forEach.call(root.querySelectorAll('.acc__trigger'), function (btn) {
      btn.addEventListener('click', function () {
        toggle(btn, root);
      });
    });
  }

  function boot() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-acc]'), init);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
