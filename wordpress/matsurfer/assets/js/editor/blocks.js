/**
 * MatSurfer sections — one generic editor for every block in /blocks.
 *
 * Each section shows the REAL public markup (server-rendered through the
 * REST block renderer, same render.php as the site), so what editors see is
 * what visitors get. On top of that preview:
 *   - texts marked data-mats-edit="path" are edited in place (click, type,
 *     click outside to save — Enter also saves, Esc cancels);
 *   - images marked data-mats-image="path" open the media library on click;
 *   - the side panel lists every field of the section with clear labels.
 * The field schema comes from each block.json ("matsurfer.fields"), passed
 * in window.matsBlocks by inc/blocks.php. No build step: plain JS on the
 * wp.* globals.
 */
(function (wp, data) {
  'use strict';
  if (!wp || !wp.blocks || !data || !data.blocks) return;

  var el = wp.element.createElement;
  var Fragment = wp.element.Fragment;
  var useState = wp.element.useState;
  var useEffect = wp.element.useEffect;
  var useRef = wp.element.useRef;
  var be = wp.blockEditor;
  var c = wp.components;
  var apiFetch = wp.apiFetch;

  /* ── attribute paths ('items.2.title') ─────────────────────────────── */
  function getPath(obj, path) {
    return String(path)
      .split('.')
      .reduce(function (node, key) {
        return node == null ? undefined : node[key];
      }, obj);
  }

  function setIn(node, segs, value) {
    if (!segs.length) return value;
    var key = segs[0];
    var isIndex = /^\d+$/.test(key);
    var base;
    if (Array.isArray(node)) base = node.slice();
    else if (node && typeof node === 'object') base = Object.assign({}, node);
    else base = isIndex ? [] : {};
    var k = isIndex ? Number(key) : key;
    base[k] = setIn(base[k], segs.slice(1), value);
    return base;
  }

  function setAttrPath(attributes, setAttributes, path, value) {
    var segs = String(path).split('.');
    var top = segs[0];
    var patch = {};
    patch[top] = segs.length === 1 ? value : setIn(attributes[top], segs.slice(1), value);
    setAttributes(patch);
  }

  /** Field definition for an attribute path (walks repeaters). */
  function fieldAt(fields, path) {
    var segs = String(path).split('.');
    var list = fields;
    var field = null;
    for (var i = 0; i < segs.length; i++) {
      var seg = segs[i];
      if (/^\d+$/.test(seg)) continue;
      field = null;
      for (var j = 0; j < (list || []).length; j++) {
        if (list[j].key === seg) {
          field = list[j];
          break;
        }
      }
      if (!field) {
        return null;
      }
      if (field.type === 'link' && segs[i + 1] === 'label') return { type: 'text', key: 'label' };
      if (field.type === 'image' && segs[i + 1] === 'alt') return { type: 'text', key: 'alt', inline: false };
      list = field.fields || [];
    }
    return field;
  }

  function blankFor(field) {
    switch (field.type) {
      case 'toggle':
        return !!field.default;
      case 'number':
        return field.default || 0;
      case 'repeater':
      case 'list':
        return [];
      case 'image':
      case 'video':
      case 'link':
        return {};
      default:
        return field.default || '';
    }
  }

  /* ── side panel fields ─────────────────────────────────────────────── */
  var common = { __nextHasNoMarginBottom: true };

  function TextField(p) {
    return el(c.TextControl, Object.assign({}, common, {
      __next40pxDefaultSize: true,
      label: p.field.label,
      help: p.field.help,
      placeholder: p.field.placeholder,
      value: p.value == null ? '' : String(p.value),
      onChange: p.onChange,
    }));
  }

  function TextareaField(p) {
    return el(c.TextareaControl, Object.assign({}, common, {
      label: p.field.label,
      help: p.field.help,
      placeholder: p.field.placeholder,
      rows: p.field.rows || 4,
      value: p.value == null ? '' : String(p.value),
      onChange: p.onChange,
    }));
  }

  function SelectField(p) {
    return el(c.SelectControl, Object.assign({}, common, {
      __next40pxDefaultSize: true,
      label: p.field.label,
      help: p.field.help,
      value: p.value == null ? '' : String(p.value),
      options: p.field.options || [],
      onChange: p.onChange,
    }));
  }

  function ToggleField(p) {
    return el(c.ToggleControl, Object.assign({}, common, {
      label: p.field.label,
      help: p.field.help,
      checked: !!p.value,
      onChange: p.onChange,
    }));
  }

  function NumberField(p) {
    return el(c.TextControl, Object.assign({}, common, {
      __next40pxDefaultSize: true,
      type: 'number',
      label: p.field.label,
      help: p.field.help,
      value: p.value == null ? '' : String(p.value),
      onChange: function (v) {
        p.onChange(v === '' ? 0 : Number(v));
      },
    }));
  }

  function openMediaFrame(kind, onPick) {
    if (!wp.media) return;
    var isVideo = kind === 'video';
    var frame = wp.media({
      title: isVideo ? 'Elige un vídeo' : 'Elige una foto',
      library: { type: isVideo ? 'video' : 'image' },
      multiple: false,
      button: { text: isVideo ? 'Usar este vídeo' : 'Usar esta foto' },
    });
    frame.on('select', function () {
      var a = frame.state().get('selection').first().toJSON();
      onPick(a);
    });
    frame.open();
  }

  function ImageField(p) {
    var v = p.value && typeof p.value === 'object' ? p.value : {};
    var has = !!(v.url || v.id);
    var pick = function () {
      openMediaFrame('image', function (a) {
        var thumb = a.sizes && (a.sizes.medium || a.sizes.large);
        p.onChange({ id: a.id, url: a.url, thumb: thumb ? thumb.url : a.url, alt: a.alt || '' });
      });
    };
    return el(
      c.BaseControl,
      { label: p.field.label, help: p.field.help, __nextHasNoMarginBottom: true },
      el(
        'div',
        { className: 'mats-field-image' },
        has
          ? el('button', { type: 'button', className: 'mats-field-image__thumb', onClick: pick, title: 'Cambiar foto' },
              el('img', { src: v.thumb || v.url, alt: '' }))
          : el('button', { type: 'button', className: 'mats-field-image__empty', onClick: pick }, 'Elegir foto'),
        el(
          'div',
          { className: 'mats-field-image__actions' },
          el(c.Button, { variant: 'secondary', onClick: pick }, has ? 'Cambiar foto' : 'Elegir foto'),
          has && !p.field.required
            ? el(c.Button, { variant: 'link', isDestructive: true, onClick: function () { p.onChange({}); } }, 'Quitar')
            : null
        ),
        has
          ? el(c.TextareaControl, Object.assign({}, common, {
              label: 'Descripción de la foto',
              help: 'Para personas que usan lector de pantalla y para Google. Describe lo que se ve.',
              rows: 2,
              value: v.alt || '',
              onChange: function (alt) {
                p.onChange(Object.assign({}, v, { alt: alt }));
              },
            }))
          : null
      )
    );
  }

  function VideoField(p) {
    var v = p.value && typeof p.value === 'object' ? p.value : {};
    var has = !!(v.url || v.id);
    var pick = function () {
      openMediaFrame('video', function (a) {
        p.onChange({ id: a.id, url: a.url, name: a.filename || a.title || '' });
      });
    };
    return el(
      c.BaseControl,
      { label: p.field.label, help: p.field.help, __nextHasNoMarginBottom: true },
      el(
        'div',
        { className: 'mats-field-video' },
        el('p', { className: 'mats-field-video__name' }, has ? v.name || String(v.url).split('/').pop() : 'Sin vídeo'),
        el(
          'div',
          { className: 'mats-field-image__actions' },
          el(c.Button, { variant: 'secondary', onClick: pick }, has ? 'Cambiar vídeo' : 'Elegir vídeo'),
          has ? el(c.Button, { variant: 'link', isDestructive: true, onClick: function () { p.onChange({}); } }, 'Quitar') : null
        )
      )
    );
  }

  function LinkField(p) {
    var v = p.value && typeof p.value === 'object' ? p.value : {};
    var urlMode = typeof v.url === 'string' && v.url !== '';
    var options = [{ value: '', label: '— Elige a dónde lleva —' }]
      .concat(data.targets || [])
      .concat([{ value: '__url', label: 'Otra dirección (web externa, PDF…)' }]);
    return el(
      'div',
      { className: 'mats-field-link' },
      p.field.label ? el('p', { className: 'mats-field-link__title' }, p.field.label) : null,
      p.field.noLabel
        ? null
        : el(c.TextControl, Object.assign({}, common, {
            __next40pxDefaultSize: true,
            label: 'Texto del botón',
            value: v.label || '',
            onChange: function (label) {
              p.onChange(Object.assign({}, v, { label: label }));
            },
          })),
      el(c.SelectControl, Object.assign({}, common, {
        __next40pxDefaultSize: true,
        label: 'Lleva a',
        value: urlMode ? '__url' : v.page || '',
        options: options,
        onChange: function (page) {
          if (page === '__url') p.onChange(Object.assign({}, v, { page: '', url: v.url || 'https://', query: '', hash: '' }));
          else p.onChange(Object.assign({}, v, { page: page, url: '', query: page === v.page ? v.query : '', hash: page === v.page ? v.hash : '' }));
        },
      })),
      urlMode
        ? el(c.TextControl, Object.assign({}, common, {
            __next40pxDefaultSize: true,
            type: 'url',
            label: 'Dirección',
            help: 'Empieza por https://',
            value: v.url,
            onChange: function (url) {
              p.onChange(Object.assign({}, v, { url: url }));
            },
          }))
        : null,
      p.field.help ? el('p', { className: 'mats-field-help' }, p.field.help) : null
    );
  }

  function NoticeField(p) {
    var link = p.field.admin
      ? el('a', { href: (data.adminUrl || '/wp-admin/') + p.field.admin, target: '_blank', rel: 'noopener', className: 'mats-notice__link' }, (p.field.linkLabel || 'Abrir') + ' ↗')
      : null;
    return el('div', { className: 'mats-notice' }, el('p', null, p.field.text), link);
  }

  function itemTitle(field, item, i) {
    var label = field.itemLabel || 'Elemento';
    var t = '';
    if (typeof item === 'string') t = item;
    else if (item && field.titleField) {
      var raw = getPath(item, field.titleField);
      t = typeof raw === 'object' && raw ? raw.label || '' : raw || '';
    }
    t = String(t || '').trim();
    if (t.length > 42) t = t.slice(0, 40) + '…';
    return t ? t : label + ' ' + (i + 1);
  }

  function CollectionField(p) {
    var field = p.field;
    var isList = field.type === 'list';
    var items = Array.isArray(p.value) ? p.value : [];
    var min = field.min || 0;
    var max = field.max || 99;
    var fixed = field.min && field.max && field.min === field.max;
    var state = useState(-1);
    var open = state[0];
    var setOpen = state[1];

    var update = function (i, item) {
      var next = items.slice();
      next[i] = item;
      p.onChange(next);
    };
    var move = function (i, d) {
      var j = i + d;
      if (j < 0 || j >= items.length) return;
      var next = items.slice();
      var tmp = next[i];
      next[i] = next[j];
      next[j] = tmp;
      p.onChange(next);
      setOpen(open === i ? j : open);
    };
    var remove = function (i) {
      if (!window.confirm('¿Quitar «' + itemTitle(field, items[i], i) + '»?')) return;
      var next = items.slice();
      next.splice(i, 1);
      p.onChange(next);
      setOpen(-1);
    };
    var add = function () {
      var blank;
      if (isList) blank = '';
      else {
        blank = {};
        (field.fields || []).forEach(function (f) {
          if (f.key) blank[f.key] = blankFor(f);
        });
      }
      p.onChange(items.concat([blank]));
      setOpen(items.length);
    };

    return el(
      'div',
      { className: 'mats-repeater' },
      field.help ? el('p', { className: 'mats-field-help' }, field.help) : null,
      items.map(function (item, i) {
        var isOpen = open === i;
        return el(
          'div',
          { className: 'mats-repeater__item' + (isOpen ? ' is-open' : ''), key: i },
          el(
            'div',
            { className: 'mats-repeater__head' },
            el(
              'button',
              { type: 'button', className: 'mats-repeater__toggle', 'aria-expanded': isOpen, onClick: function () { setOpen(isOpen ? -1 : i); } },
              el('span', { className: 'mats-repeater__num' }, i + 1),
              el('span', { className: 'mats-repeater__title' }, itemTitle(field, item, i))
            ),
            el(c.Button, { icon: 'arrow-up-alt2', label: 'Subir', size: 'small', disabled: i === 0, onClick: function () { move(i, -1); } }),
            el(c.Button, { icon: 'arrow-down-alt2', label: 'Bajar', size: 'small', disabled: i === items.length - 1, onClick: function () { move(i, 1); } }),
            fixed ? null : el(c.Button, { icon: 'trash', label: 'Quitar', size: 'small', isDestructive: true, disabled: items.length <= min, onClick: function () { remove(i); } })
          ),
          isOpen
            ? el(
                'div',
                { className: 'mats-repeater__body' },
                isList
                  ? el(TextareaField, { field: { label: field.itemLabel || 'Texto', rows: 2 }, value: item, onChange: function (v) { update(i, v); } })
                  : renderFields(field.fields || [], item || {}, function (key, v) {
                      update(i, Object.assign({}, item || {}, (function () { var o = {}; o[key] = v; return o; })()));
                    })
              )
            : null
        );
      }),
      !fixed && items.length < max
        ? el(c.Button, { variant: 'secondary', icon: 'plus', className: 'mats-repeater__add', onClick: add }, 'Añadir ' + (field.itemLabel || 'elemento').toLowerCase())
        : null
    );
  }

  var FIELD_TYPES = {
    text: TextField,
    textarea: TextareaField,
    select: SelectField,
    toggle: ToggleField,
    number: NumberField,
    image: ImageField,
    video: VideoField,
    link: LinkField,
    notice: NoticeField,
    repeater: CollectionField,
    list: CollectionField,
  };

  function renderFields(fields, values, onChange) {
    return fields.map(function (field, i) {
      var Comp = FIELD_TYPES[field.type];
      if (!Comp) return null;
      return el(
        'div',
        { className: 'mats-field mats-field--' + field.type, key: (field.key || field.type) + i },
        el(Comp, {
          field: field,
          value: field.key ? values[field.key] : undefined,
          onChange: function (v) {
            if (field.key) onChange(field.key, v);
          },
        })
      );
    });
  }

  /** Splits the schema into side-panel groups ('panel' entries, repeaters). */
  function groupFields(fields) {
    var groups = [];
    var current = { title: 'Textos de la sección', fields: [] };
    fields.forEach(function (f) {
      if (f.type === 'panel') {
        if (current.fields.length) groups.push(current);
        current = { title: f.label || 'Más opciones', fields: [] };
      } else if (f.type === 'repeater' || f.type === 'list') {
        if (current.fields.length) groups.push(current);
        groups.push({ title: f.label, fields: [Object.assign({}, f, { label: '' })] });
        current = { title: 'Otros textos', fields: [] };
      } else {
        current.fields.push(f);
      }
    });
    if (current.fields.length) groups.push(current);
    return groups;
  }

  /* ── the block editor component ────────────────────────────────────── */
  function openSidebarOnce() {
    try {
      var editPost = wp.data.select('core/edit-post');
      if (editPost && editPost.isEditorSidebarOpened && !editPost.isEditorSidebarOpened()) {
        wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/block');
      }
    } catch (e) {
      /* not critical */
    }
  }

  function makeEdit(name, def) {
    return function Edit(props) {
      var attributes = props.attributes;
      var setAttributes = props.setAttributes;
      var isSelected = props.isSelected;
      var postId = wp.data.useSelect(function (select) {
        var ed = select('core/editor');
        return ed ? ed.getCurrentPostId() : 0;
      }, []);

      var htmlState = useState(null);
      var html = htmlState[0];
      var setHtml = htmlState[1];
      var loadingState = useState(true);
      var loading = loadingState[0];
      var setLoading = loadingState[1];
      var errorState = useState('');
      var error = errorState[0];
      var setError = errorState[1];

      var boxRef = useRef(null);
      var attrsRef = useRef(attributes);
      attrsRef.current = attributes;
      var editingRef = useRef(false);
      var key = JSON.stringify(attributes);

      useEffect(
        function () {
          var alive = true;
          setLoading(true);
          var timer = setTimeout(
            function () {
              apiFetch({
                path: wp.url.addQueryArgs('/wp/v2/block-renderer/' + name, { context: 'edit', post_id: postId || undefined }),
                method: 'POST',
                data: { attributes: attributes },
              })
                .then(function (res) {
                  if (!alive) return;
                  setHtml(res && typeof res.rendered === 'string' ? res.rendered : '');
                  setError('');
                })
                .catch(function (err) {
                  if (alive) setError((err && err.message) || 'No se ha podido cargar la vista previa.');
                })
                .then(function () {
                  if (alive) setLoading(false);
                });
            },
            html === null ? 0 : 300
          );
          return function () {
            alive = false;
            clearTimeout(timer);
          };
        },
        [key, postId]
      );

      useEffect(
        function () {
          if (isSelected) openSidebarOnce();
        },
        [isSelected]
      );

      /* inject the preview and wire in-place editing */
      useEffect(
        function () {
          var box = boxRef.current;
          if (!box || html === null) return;
          if (editingRef.current) return;
          box.innerHTML = html;
          var doc = box.ownerDocument;

          box.querySelectorAll('[data-mats-edit]').forEach(function (node) {
            var path = node.getAttribute('data-mats-edit');
            var field = fieldAt(def.fields || [], path);
            if (!field || field.inline === false) return;
            if (['text', 'textarea', 'list'].indexOf(field.type) === -1) return;
            var original = '';
            node.setAttribute('contenteditable', 'true');
            node.setAttribute('spellcheck', 'true');
            node.setAttribute('title', 'Haz clic para editar este texto');
            node.addEventListener('focus', function () {
              editingRef.current = true;
              original = node.textContent;
              node.classList.add('is-editing');
            });
            node.addEventListener('keydown', function (e) {
              if (e.key === 'Enter') {
                e.preventDefault();
                node.blur();
              } else if (e.key === 'Escape') {
                e.preventDefault();
                node.textContent = original;
                node.blur();
              }
              e.stopPropagation();
            });
            node.addEventListener('paste', function (e) {
              e.preventDefault();
              var text = ((e.clipboardData || window.clipboardData).getData('text/plain') || '').replace(/\s+/g, ' ');
              doc.execCommand('insertText', false, text);
            });
            node.addEventListener('blur', function () {
              editingRef.current = false;
              node.classList.remove('is-editing');
              // textContent, not innerText: innerText applies CSS text-transform, which
              // would save uppercase-styled copy (eyebrows, tags) in capitals.
              var value = node.textContent.replace(/\s+/g, ' ').trim();
              var current = getPath(attrsRef.current, path);
              if (value !== String(current == null ? '' : current)) {
                setAttrPath(attrsRef.current, setAttributes, path, value);
              }
            });
          });

          box.querySelectorAll('[data-mats-image]').forEach(function (node) {
            var path = node.getAttribute('data-mats-image');
            node.addEventListener('click', function (e) {
              e.preventDefault();
              e.stopPropagation();
              openMediaFrame('image', function (a) {
                var thumb = a.sizes && (a.sizes.medium || a.sizes.large);
                var prev = getPath(attrsRef.current, path) || {};
                setAttrPath(attrsRef.current, setAttributes, path, {
                  id: a.id,
                  url: a.url,
                  thumb: thumb ? thumb.url : a.url,
                  alt: a.alt || prev.alt || '',
                });
              });
            });
          });
        },
        [html]
      );

      var blockProps = be.useBlockProps({
        className: 'mats-preview' + (loading ? ' is-loading' : '') + (isSelected ? ' is-selected' : ''),
        onClickCapture: function (e) {
          var t = e.target;
          if (!t || !t.closest) return;
          /* editor-only notes link to admin screens: open them in a new tab */
          var note = t.closest('.mats-note a, .mats-empty a');
          if (note) {
            e.preventDefault();
            e.stopPropagation();
            window.open(note.href, '_blank', 'noopener');
            return;
          }
          /* everything else in the preview is a picture of the page: links and
             buttons must not navigate away from the editor */
          if (t.closest('a, button, summary') && !t.closest('[contenteditable="true"]')) {
            e.preventDefault();
          }
        },
        onSubmitCapture: function (e) {
          e.preventDefault();
        },
      });

      var groups = groupFields(def.fields || []);
      var inspector = el(
        be.InspectorControls,
        null,
        el(
          'div',
          { className: 'mats-inspector-intro' },
          el('strong', null, def.title),
          el('span', null, 'Edita los textos aquí o haz clic directamente sobre ellos en la página. Las fotos se cambian haciendo clic encima.')
        ),
        groups.map(function (g, i) {
          return el(
            c.PanelBody,
            { title: g.title, initialOpen: i === 0, key: g.title + i },
            renderFields(g.fields, attributes, function (k, v) {
              var patch = {};
              patch[k] = v;
              setAttributes(patch);
            })
          );
        })
      );

      return el(
        Fragment,
        null,
        inspector,
        el(
          'div',
          blockProps,
          el('span', { className: 'mats-preview__badge' }, def.title),
          error ? el(c.Notice, { status: 'error', isDismissible: false }, error) : null,
          html === null ? el('div', { className: 'mats-preview__loading' }, el(c.Spinner, null)) : null,
          el('div', { className: 'mats-preview__html', ref: boxRef }),
          html === '' ? el('div', { className: 'mats-empty' }, el('strong', null, def.title), el('span', null, 'Rellena esta sección en el panel de la derecha.')) : null
        )
      );
    };
  }

  Object.keys(data.blocks).forEach(function (name) {
    var def = data.blocks[name];
    wp.blocks.registerBlockType(name, {
      apiVersion: 3,
      title: def.title,
      edit: makeEdit(name, def),
      save: function () {
        return null;
      },
    });
  });
})(window.wp, window.matsBlocks);
