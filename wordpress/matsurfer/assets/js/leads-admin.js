/**
 * Contactos (leads inbox) — small admin helpers, no dependencies.
 *  - confirmation before anything is deleted (row action, bulk action,
 *    detail screen, automatic deletion setting)
 *  - "Copiar el email" button
 * The screens work without this file; it only adds safety nets.
 */
(function () {
  'use strict';

  /* links / buttons carrying data-mats-confirm="question" */
  document.addEventListener('click', function (e) {
    var el = e.target && e.target.closest ? e.target.closest('[data-mats-confirm]') : null;
    if (!el) return;
    if (!window.confirm(el.getAttribute('data-mats-confirm'))) {
      e.preventDefault();
      e.stopPropagation();
    }
  });

  /* bulk "Borrar definitivamente" in the list */
  var list = document.getElementById('mats-leads-form');
  if (list) {
    list.addEventListener('submit', function (e) {
      var submitter = e.submitter || document.activeElement;
      if (submitter && submitter.name === 'filter_action') return;
      var top = list.querySelector('#bulk-action-selector-top');
      var bottom = list.querySelector('#bulk-action-selector-bottom');
      var action = submitter && submitter.id === 'doaction2' && bottom ? bottom.value : top ? top.value : '';
      if (action !== 'delete') return;
      var checked = list.querySelectorAll('input[name="leads[]"]:checked').length;
      if (!checked) return; // the server explains that nothing was selected
      var text = checked === 1
        ? '¿Borrar definitivamente 1 contacto? No se puede deshacer.'
        : '¿Borrar definitivamente ' + checked + ' contactos? No se puede deshacer.';
      if (!window.confirm(text)) e.preventDefault();
    });
  }

  /* automatic deletion: confirm before switching it on */
  var retention = document.querySelector('form[data-mats-retention]');
  if (retention) {
    var select = retention.querySelector('select[name="retention"]');
    var initial = select ? select.value : '0';
    retention.addEventListener('submit', function (e) {
      if (!select || select.value === '0' || select.value === initial) return;
      var label = select.options[select.selectedIndex].text.replace(/^Borrar/, 'borrar');
      var text = 'Al guardar se van a ' + label.toLowerCase() +
        ', ahora y cada día. Lo que se borra no se puede recuperar.\n\n¿Quieres activarlo?';
      if (!window.confirm(text)) e.preventDefault();
    });
  }

  /* copy an email address to the clipboard */
  document.addEventListener('click', function (e) {
    var btn = e.target && e.target.closest ? e.target.closest('[data-mats-copy]') : null;
    if (!btn) return;
    e.preventDefault();
    var text = btn.getAttribute('data-mats-copy') || '';
    var label = btn.getAttribute('data-mats-label') || btn.textContent;
    btn.setAttribute('data-mats-label', label);
    var done = function () {
      btn.textContent = btn.getAttribute('data-mats-copied') || '✓';
      window.setTimeout(function () { btn.textContent = label; }, 2000);
    };
    var fallback = function () {
      var area = document.createElement('textarea');
      area.value = text;
      area.setAttribute('readonly', '');
      area.style.position = 'absolute';
      area.style.left = '-9999px';
      document.body.appendChild(area);
      area.select();
      try { document.execCommand('copy'); done(); } catch (err) { window.prompt('Copia el email:', text); }
      document.body.removeChild(area);
    };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(done, fallback);
    } else {
      fallback();
    }
  });
})();
