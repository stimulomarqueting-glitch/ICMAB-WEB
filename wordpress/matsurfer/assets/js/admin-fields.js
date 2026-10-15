/**
 * MatSurfer — admin screens of Equipo, Aplicaciones, Publicaciones and
 * Valoraciones (inc/modules/content-types-admin.php). Plain ES2017, no
 * build step. Everything works without it; this only makes it friendlier:
 *
 *   - live character guide under texts that have a recommended length;
 *     long texts grow with their content
 *   - EN | ES columns: "sin traducir" note while a Spanish text is empty
 *     (and "falta el inglés" the other way round)
 *   - web addresses get "https://" when it is missing; the DOI keeps only
 *     its code
 *   - application identifier: filled from the English title while empty;
 *     locked once published until "Cambiar" is pressed; cleaned as typed
 *   - photo picker (media library) with preview; warns when a cut-out is
 *     a JPG (no transparent background)
 *   - asks before leaving the page with unsaved changes
 *   - list screen: highlights the row that was just moved with ▲▼
 */
(function () {
  'use strict';

  var cfg = window.matsCptAdmin || {};

  /* ── list screen ─────────────────────────────────────────────────── */
  var moved = document.querySelector('[data-mats-moved]');
  if (moved) {
    var row = document.getElementById('post-' + moved.getAttribute('data-mats-moved'));
    if (row) {
      row.classList.add('mats-cpta-moved');
      window.setTimeout(function () {
        row.classList.remove('mats-cpta-moved');
      }, 2600);
    }
  }

  var box = document.querySelector('.mats-cpta');
  if (!box) return;
  var form = document.getElementById('post');

  /* the title field reads its help line */
  var titleField = document.getElementById('title');
  if (titleField && document.getElementById('mats-cpta-title-help')) {
    titleField.setAttribute('aria-describedby', 'mats-cpta-title-help');
  }

  function each(list, fn) {
    Array.prototype.forEach.call(list, fn);
  }

  function describedBy(input, id, add) {
    var ids = (input.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
    var at = ids.indexOf(id);
    if (add && at === -1) ids.push(id);
    if (!add && at !== -1) ids.splice(at, 1);
    if (ids.length) input.setAttribute('aria-describedby', ids.join(' '));
    else input.removeAttribute('aria-describedby');
  }

  /* ── character guide ─────────────────────────────────────────────── */
  function updateCount(input) {
    var max = parseInt(input.getAttribute('data-mats-max'), 10);
    var counter = document.getElementById(input.id + '-count');
    if (!max || !counter) return;
    var len = Array.from ? Array.from(input.value).length : input.value.length;
    var n = counter.querySelector('.mats-cpta__count-n');
    var over = counter.querySelector('.mats-cpta__count-over');
    if (n) n.textContent = String(len);
    counter.classList.toggle('is-over', len > max);
    if (over) over.hidden = len <= max;
  }

  /* ── both languages filled? ──────────────────────────────────────── */
  function updatePair(row) {
    var en = row.querySelector('.mats-cpta__col[data-lang="en"] .mats-cpta__input');
    var es = row.querySelector('.mats-cpta__col[data-lang="es"] .mats-cpta__input');
    if (!en || !es) return;
    var hasEn = en.value.trim() !== '';
    var hasEs = es.value.trim() !== '';
    [
      [es, hasEn && !hasEs],
      [en, hasEs && !hasEn],
    ].forEach(function (pair) {
      var input = pair[0];
      var missing = pair[1];
      var col = input.closest('.mats-cpta__col');
      var note = document.getElementById(input.id + '-missing');
      if (col) col.classList.toggle('is-missing', missing);
      if (note) note.hidden = !missing;
      describedBy(input, input.id + '-missing', missing);
    });
  }

  each(box.querySelectorAll('.mats-cpta__input[data-mats-max]'), function (input) {
    input.addEventListener('input', function () {
      updateCount(input);
    });
    updateCount(input);
  });

  /* long texts grow with their content (no inner scrollbar) */
  function autosize(area) {
    area.style.height = 'auto';
    area.style.height = area.scrollHeight + 2 + 'px';
  }
  each(box.querySelectorAll('textarea.mats-cpta__input'), function (area) {
    area.addEventListener('input', function () {
      autosize(area);
    });
    autosize(area);
  });
  window.addEventListener('resize', function () {
    each(box.querySelectorAll('textarea.mats-cpta__input'), autosize);
  });

  each(box.querySelectorAll('.mats-cpta__row--i18n'), function (row) {
    row.addEventListener('input', function () {
      updatePair(row);
    });
    updatePair(row);
  });

  /* ── web addresses and DOI ───────────────────────────────────────── */
  each(box.querySelectorAll('input[type="url"]'), function (input) {
    input.addEventListener('blur', function () {
      var v = input.value.trim();
      if (v && !/^[a-z][a-z0-9+.\-]*:/i.test(v)) v = 'https://' + v.replace(/^\/+/, '');
      if (v !== input.value) {
        input.value = v;
        input.dispatchEvent(new Event('input', { bubbles: true }));
      }
    });
  });

  each(box.querySelectorAll('input[data-kind="doi"]'), function (input) {
    input.addEventListener('blur', function () {
      var v = input.value.trim().replace(/^(https?:\/\/)?(dx\.)?doi\.org\//i, '').replace(/^doi:\s*/i, '');
      if (v !== input.value) input.value = v;
    });
  });

  /* ── application identifier ──────────────────────────────────────── */
  function slugify(text) {
    var s = String(text || '').toLowerCase();
    if (s.normalize) s = s.normalize('NFD').replace(/[̀-ͯ]/g, '');
    return s
      .replace(/&/g, ' ')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '')
      .slice(0, 60)
      .replace(/-+$/, '');
  }

  var slugWrap = box.querySelector('[data-mats-slug]');
  if (slugWrap) {
    var slugInput = slugWrap.querySelector('input[type="text"]');
    var openInput = slugWrap.querySelector('[data-mats-slug-open]');
    var changeBtn = slugWrap.querySelector('[data-mats-slug-change]');
    var auto = slugWrap.getAttribute('data-auto') === '1';
    var source = box.querySelector('input[name="mats_cpta[title_en]"]');
    var titleInput = document.getElementById('title');

    var fillFromTitle = function () {
      if (!auto || slugInput.readOnly) return;
      var from = source && source.value.trim() ? source.value : titleInput ? titleInput.value : '';
      slugInput.value = slugify(from);
    };
    if (source) source.addEventListener('input', fillFromTitle);
    if (titleInput) titleInput.addEventListener('input', fillFromTitle);

    slugInput.addEventListener('input', function () {
      if (slugInput.readOnly) return;
      auto = slugInput.value === '';
      var clean = slugInput.value.toLowerCase().replace(/\s+/g, '-');
      if (clean !== slugInput.value) slugInput.value = clean;
    });
    slugInput.addEventListener('blur', function () {
      if (slugInput.readOnly) return;
      slugInput.value = slugify(slugInput.value);
      if (!slugInput.value) {
        auto = true;
        fillFromTitle();
      }
    });

    if (changeBtn) {
      changeBtn.addEventListener('click', function () {
        slugInput.readOnly = false;
        slugWrap.classList.remove('is-locked');
        if (openInput) openInput.value = '1';
        changeBtn.hidden = true;
        var note = box.querySelector('[data-mats-slug-note]');
        var warn = box.querySelector('[data-mats-slug-warn]');
        if (note) note.hidden = true;
        if (warn) {
          warn.hidden = false;
          warn.id = slugInput.id + '-warn';
          describedBy(slugInput, warn.id, true);
        }
        slugInput.focus();
        slugInput.select();
      });
    }
  }

  /* ── photo picker ────────────────────────────────────────────────── */
  each(box.querySelectorAll('[data-mats-media]'), function (wrap) {
    var input = wrap.querySelector('[data-mats-media-input]');
    var preview = wrap.querySelector('[data-mats-media-preview]');
    var choose = wrap.querySelector('[data-mats-media-choose]');
    var remove = wrap.querySelector('[data-mats-media-remove]');
    var warn = wrap.querySelector('[data-mats-media-warn]');
    var warnText = wrap.querySelector('[data-mats-media-warn-text]');
    var fileLine = wrap.querySelector('[data-mats-media-file]');
    var emptyHtml = preview.querySelector('.mats-cpta__media-empty');
    var emptyText = emptyHtml ? emptyHtml.textContent : '';
    var frame = null;

    if (!window.wp || !window.wp.media) {
      choose.disabled = true;
      return;
    }

    function setEmpty() {
      input.value = '';
      preview.textContent = '';
      var span = document.createElement('span');
      span.className = 'mats-cpta__media-empty';
      span.textContent = emptyText;
      preview.appendChild(span);
      choose.textContent = wrap.getAttribute('data-choose');
      remove.hidden = true;
      warn.hidden = true;
      if (fileLine) fileLine.hidden = true;
      markDirty();
    }

    function setImage(att) {
      input.value = String(att.id);
      preview.textContent = '';
      var img = document.createElement('img');
      var size = att.sizes && (att.sizes.medium || att.sizes.large || att.sizes.full);
      img.src = size ? size.url : att.url;
      img.alt = '';
      preview.appendChild(img);
      choose.textContent = wrap.getAttribute('data-change');
      remove.hidden = false;
      if (fileLine) {
        fileLine.textContent = att.filename || '';
        fileLine.hidden = !att.filename;
      }
      var cutout = /^(webp|png|avif|gif)$/i.test(att.subtype || '');
      if (!cutout && warnText && cfg.notCutout) {
        warnText.textContent = cfg.notCutout.replace('%s', String(att.subtype || 'archivo').toUpperCase());
      }
      warn.hidden = cutout;
      markDirty();
    }

    choose.addEventListener('click', function (event) {
      event.preventDefault();
      if (!frame) {
        frame = window.wp.media({
          title: cfg.mediaTitle || 'Elige una foto',
          button: { text: cfg.mediaButton || 'Usar esta foto' },
          library: { type: 'image' },
          multiple: false,
        });
        frame.on('open', function () {
          /* new users would land on "Subir archivos": show the library first */
          if (frame.content && frame.content.mode() !== 'browse') frame.content.mode('browse');
          var selection = frame.state().get('selection');
          var id = parseInt(input.value, 10);
          selection.reset(id ? [window.wp.media.attachment(id)] : []);
        });
        frame.on('select', function () {
          var att = frame.state().get('selection').first();
          if (att) setImage(att.toJSON());
        });
      }
      frame.open();
    });

    remove.addEventListener('click', function (event) {
      event.preventDefault();
      setEmpty();
      choose.focus();
    });
  });

  /* ── unsaved changes ─────────────────────────────────────────────── */
  var dirty = false;
  var submitting = false;
  function markDirty() {
    dirty = true;
  }
  box.addEventListener('input', markDirty);
  box.addEventListener('change', markDirty);
  var order = document.getElementById('mats-cpta-menu-order');
  if (order) order.addEventListener('input', markDirty);
  if (form) {
    form.addEventListener('submit', function () {
      submitting = true;
    });
  }
  window.addEventListener('beforeunload', function (event) {
    if (!dirty || submitting) return undefined;
    event.preventDefault();
    event.returnValue = cfg.unsaved || '';
    return cfg.unsaved || '';
  });
})();
