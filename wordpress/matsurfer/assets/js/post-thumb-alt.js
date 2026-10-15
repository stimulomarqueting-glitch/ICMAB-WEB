/**
 * «Descripción de la foto destacada» — a field under the photo of the
 * «Foto destacada» panel of news articles. The language versions of an
 * article share one image (Polylang does not translate media) and its one
 * «Texto alternativo»; this field lets each article describe the photo in
 * its own language. Saved with the article in the post meta
 * _mats_thumb_alt (registered in inc/modules/blog.php, which also passes
 * window.matsThumbAlt); empty → the image's own alt text, shown as the
 * placeholder. Choosing another photo (or removing it) empties the field:
 * the old text described the old photo. No build step: plain JS on the
 * wp.* globals.
 */
(function (wp, cfg) {
  'use strict';
  if (!wp || !wp.hooks || !wp.element || !wp.components || !wp.data || !cfg || !cfg.key) return;

  var el = wp.element.createElement;
  var Fragment = wp.element.Fragment;
  var useEffect = wp.element.useEffect;
  var useRef = wp.element.useRef;
  var useSelect = wp.data.useSelect;
  var useDispatch = wp.data.useDispatch;
  var TextareaControl = wp.components.TextareaControl;
  if (!useSelect || !useDispatch || !useEffect || !useRef || !TextareaControl) return;

  function ThumbAlt() {
    var data = useSelect(function (select) {
      var ed = select('core/editor');
      var id = ed.getEditedPostAttribute('featured_media') || 0;
      var media = id ? select('core').getMedia(id, { context: 'view' }) : null;
      return {
        type: ed.getCurrentPostType(),
        meta: ed.getEditedPostAttribute('meta') || {},
        id: id,
        loaded: !id || !!media,
        shared: media ? String(media.alt_text || '').trim() : '',
      };
    }, []);
    var editPost = useDispatch('core/editor').editPost;
    var value = data.meta[cfg.key] || '';
    var lastId = useRef(data.id);

    function save(text) {
      var patch = {};
      patch[cfg.key] = text;
      editPost({ meta: Object.assign({}, data.meta, patch) });
    }

    /* another photo (or none): the description written for the old one goes */
    useEffect(
      function () {
        if (lastId.current === data.id) return;
        lastId.current = data.id;
        if (value) save('');
      },
      [data.id]
    );

    if (data.type !== 'post' || !data.id) return null;

    var help =
      'Qué se ve en la foto, en el idioma de esta noticia. La oyen las personas que usan lector de pantalla, y la leen Google y las redes sociales.';
    if (data.loaded) {
      help += data.shared
        ? ' Si la dejas vacía se usa la de la biblioteca de medios (en gris), que es la misma en los dos idiomas.'
        : ' Si la dejas vacía, la foto no tendrá descripción.';
    }

    return el(
      'div',
      { className: 'mats-thumb-alt', style: { marginTop: '16px' } },
      el(TextareaControl, {
        __nextHasNoMarginBottom: true,
        label: 'Descripción de la foto destacada',
        help: help,
        placeholder: data.shared,
        rows: 3,
        value: value,
        onChange: function (text) {
          save(String(text || '').replace(/\s*\n+\s*/g, ' '));
        },
      })
    );
  }

  wp.hooks.addFilter('editor.PostFeaturedImage', 'matsurfer/post-thumb-alt', function (FeaturedImage) {
    return function (props) {
      return el(Fragment, null, el(FeaturedImage, props), el(ThumbAlt));
    };
  });
})(window.wp, window.matsThumbAlt);
