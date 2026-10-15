/**
 * "Google y redes sociales" — panel of the block editor sidebar (pages and
 * news articles). Title and description for Google and social networks,
 * with character counters and a live preview of the search result.
 * Values are saved with the page in the post meta _mats_seo_title /
 * _mats_seo_description (registered in inc/modules/seo.php, which also
 * passes window.matsSeo). Empty fields → the automatic texts shown as
 * placeholders. No build step: plain JS on the wp.* globals.
 */
(function (wp, cfg) {
  'use strict';
  if (!wp || !wp.plugins || !wp.data || !wp.element || !wp.components || !cfg) return;

  var Panel =
    (wp.editor && wp.editor.PluginDocumentSettingPanel) ||
    (wp.editPost && wp.editPost.PluginDocumentSettingPanel);
  if (!Panel) return;

  var el = wp.element.createElement;
  var c = wp.components;
  var useSelect = wp.data.useSelect;
  var useDispatch = wp.data.useDispatch;
  var common = { __nextHasNoMarginBottom: true };

  /* characters as people count them (emoji, accents → 1) */
  function count(text) {
    return Array.from(String(text || '')).length;
  }

  function plain(text) {
    return String(text || '')
      .replace(/<[^>]*>/g, ' ')
      .replace(/&nbsp;/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  /* what Google shows: cut on a word with "…" */
  function cut(text, max) {
    var chars = Array.from(String(text || ''));
    if (chars.length <= max) return chars.join('');
    var short = chars.slice(0, max - 1).join('');
    var space = short.lastIndexOf(' ');
    if (space > max * 0.6) short = short.slice(0, space);
    return short.replace(/[\s,;:.\-—–]+$/, '') + '…';
  }

  function Counter(props) {
    var n = count(props.value);
    var max = props.max;
    var cls = 'is-ok';
    var text;
    if (!n) {
      cls = 'is-empty';
      text = 'Vacío: se usará el texto automático.';
    } else if (n > max) {
      cls = 'is-warn';
      text = n + ' de ' + max + ' caracteres: algo largo, Google cortará el final.';
    } else if (props.min && n < props.min) {
      cls = 'is-warn';
      text = n + ' caracteres: un poco corto. Lo ideal es entre ' + props.min + ' y ' + max + '.';
    } else {
      text = n + ' de ' + max + ' caracteres: perfecto.';
    }
    return el('p', { className: 'mats-seo__count ' + cls, 'aria-live': 'polite' }, text);
  }

  function SeoPanel() {
    var post = useSelect(function (select) {
      var ed = select('core/editor');
      return {
        meta: ed.getEditedPostAttribute('meta') || {},
        title: ed.getEditedPostAttribute('title') || '',
        excerpt: ed.getEditedPostAttribute('excerpt') || '',
        featured: ed.getEditedPostAttribute('featured_media') || 0,
        link: (ed.getPermalink && ed.getPermalink()) || '',
      };
    }, []);
    var editPost = useDispatch('core/editor').editPost;

    var keys = cfg.keys;
    var seoTitle = post.meta[keys.title] || '';
    var seoDesc = post.meta[keys.description] || '';
    var isPost = cfg.postType === 'post';

    function setMeta(key, value) {
      var patch = {};
      patch[key] = value;
      editPost({ meta: Object.assign({}, post.meta, patch) });
    }

    /* the automatic texts, exactly as the site builds them */
    var docTitle = plain(post.title);
    var autoTitle = cfg.isFront
      ? cfg.siteTitle
      : docTitle && docTitle !== cfg.brand
        ? docTitle + cfg.sep + cfg.brand
        : cfg.brand;
    var excerpt = plain(post.excerpt);
    var autoDesc = cfg.isFront ? cfg.siteDesc : excerpt || (isPost ? '' : cfg.siteDesc);

    var shownTitle = plain(seoTitle) || autoTitle;
    var shownDesc = plain(seoDesc) || autoDesc;
    var url = post.link || cfg.home;

    var preview = el(
      'div',
      { className: 'mats-seo__preview', 'aria-label': 'Vista previa del resultado en Google' },
      el('span', { className: 'mats-seo__label' }, 'Vista previa en Google'),
      el('div', { className: 'mats-seo__site' }, cfg.brand),
      el('div', { className: 'mats-seo__url' }, url.replace(/^https?:\/\//, '').replace(/\/$/, '')),
      el('p', { className: 'mats-seo__ptitle' }, cut(shownTitle, cfg.max.title)),
      shownDesc
        ? el('p', { className: 'mats-seo__pdesc' }, cut(shownDesc, cfg.max.description))
        : el('p', { className: 'mats-seo__pdesc is-auto' }, 'Se usará el principio del texto de la noticia.')
    );

    var descHelp = cfg.isFront
      ? 'Si lo dejas vacío se usa la descripción general de la web.'
      : isPost
        ? 'Si lo dejas vacío se usa el «Extracto» de la noticia o, si no hay, el principio del texto.'
        : 'Si lo dejas vacío se usa el «Extracto» de la página o la descripción general de la web.';

    return el(
      Panel,
      { name: 'matsurfer-seo', title: 'Google y redes sociales', className: 'mats-seo' },
      el(
        'p',
        { className: 'mats-seo__intro' },
        isPost
          ? 'Así aparecerá esta noticia en Google y al compartirla en redes sociales.'
          : 'Así aparecerá esta página en Google y al compartirla en redes sociales.'
      ),
      preview,
      el(
        'div',
        { className: 'mats-seo__field' },
        el(c.TextControl, Object.assign({}, common, {
          __next40pxDefaultSize: true,
          label: 'Título en Google',
          help: 'El título azul de Google y el de la pestaña del navegador. Si lo dejas vacío se usa el de la vista previa.',
          placeholder: autoTitle,
          value: seoTitle,
          onChange: function (value) {
            setMeta(keys.title, value);
          },
        })),
        el(Counter, { value: seoTitle, max: cfg.max.title })
      ),
      el(
        'div',
        { className: 'mats-seo__field' },
        el(c.TextareaControl, Object.assign({}, common, {
          label: 'Descripción en Google',
          help: 'El texto gris bajo el título. Cuenta en una o dos frases qué encontrará la persona. ' + descHelp,
          placeholder: autoDesc || 'Principio del texto de la noticia',
          rows: 4,
          value: seoDesc,
          onChange: function (value) {
            setMeta(keys.description, value.replace(/\s*\n+\s*/g, ' '));
          },
        })),
        el(Counter, { value: seoDesc, max: cfg.max.description, min: 50 })
      ),
      el(
        'p',
        { className: 'mats-seo__note' },
        post.featured
          ? 'Imagen al compartir en redes: la «Imagen destacada» de ' + (isPost ? 'esta noticia.' : 'esta página.')
          : 'Imagen al compartir en redes: la imagen general de MatSurfer. Para usar otra, elige una «Imagen destacada».'
      )
    );
  }

  wp.plugins.registerPlugin('matsurfer-seo', { render: SeoPanel, icon: 'search' });
})(window.wp, window.matsSeo);
