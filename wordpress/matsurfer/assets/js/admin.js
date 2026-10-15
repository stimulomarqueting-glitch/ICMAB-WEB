/**
 * MatSurfer admin behaviour (plain ES2017, no build step).
 *
 * Everywhere in the admin:
 *   - buttons marked [data-mats-print] print the page (Guía de uso → PDF).
 *
 * In the block editor (window.matsEditorGuard, set by inc/modules/editor.php):
 *   - a notice at the top explains what can be changed on this page
 *     (structural pages: "secciones bloqueadas…", News page, legal texts,
 *     new pages);
 *   - editors: the Page attributes / Template panels of structural pages
 *     are removed, and the block style variations that are not part of the
 *     design (rounded image, outline button, striped table…) disappear, so
 *     the "Styles" panel never shows up.
 */
(function () {
  'use strict';

  document.addEventListener('click', function (event) {
    var target = event.target;
    var button = target && target.closest ? target.closest('[data-mats-print]') : null;
    if (!button) return;
    event.preventDefault();
    window.print();
  });

  var cfg = window.matsEditorGuard;
  var wp = window.wp;
  if (!cfg || !wp || !wp.data) return;

  /* ── block style variations (editors) ─────────────────────────────── */
  var STYLE_BLOCKS = ['core/image', 'core/button', 'core/quote', 'core/separator', 'core/table', 'core/pullquote', 'core/social-links', 'core/tag-cloud'];

  if (!cfg.isAdmin && wp.hooks && wp.hooks.addFilter) {
    /*
     * Before the core blocks register (they do on DOM ready) and before
     * core's own support filters (priority 5 < 10): no style variations,
     * no "Fit text", no renaming and no "Hide" for editors.
     */
    wp.hooks.addFilter(
      'blocks.registerBlockType',
      'matsurfer/editor-guard',
      function (settings, name) {
        if (!settings) return settings;
        var supports = Object.assign({}, settings.supports || {});
        supports.renaming = false;
        supports.visibility = false;
        if (supports.typography && typeof supports.typography === 'object') {
          supports.typography = Object.assign({}, supports.typography, { fitText: false });
        }
        var next = Object.assign({}, settings, { supports: supports });
        if (STYLE_BLOCKS.indexOf(name) !== -1) next.styles = [];
        return next;
      },
      5
    );
  }

  function removeStyleVariations() {
    var blocksStore = wp.data.select('core/blocks');
    if (cfg.isAdmin || !blocksStore || !blocksStore.getBlockStyles || !wp.blocks || !wp.blocks.unregisterBlockStyle) return;
    STYLE_BLOCKS.forEach(function (name) {
      (blocksStore.getBlockStyles(name) || []).forEach(function (style) {
        try {
          wp.blocks.unregisterBlockStyle(name, style.name);
        } catch (e) {
          /* not registered */
        }
      });
    });
  }

  /* ── once the editor has loaded the post ─────────────────────────── */
  function whenEditorReady(callback) {
    var select = wp.data.select;
    var ready = function () {
      var editor = select('core/editor');
      return !!(editor && editor.getCurrentPostId && editor.getCurrentPostId());
    };
    if (ready()) {
      callback();
      return;
    }
    var unsubscribe = wp.data.subscribe(function () {
      if (!ready()) return;
      unsubscribe();
      callback();
    });
  }

  function onReady() {
    var dispatch = wp.data.dispatch;

    (cfg.hidePanels || []).forEach(function (panel) {
      try {
        dispatch('core/editor').removeEditorPanel(panel);
      } catch (e) {
        /* older editor: nothing to remove */
      }
    });

    if (cfg.notice && dispatch('core/notices')) {
      dispatch('core/notices').createNotice('info', cfg.notice, {
        id: 'mats-page-kind',
        isDismissible: true,
        actions: cfg.guideUrl ? [{ label: 'Ver la guía', url: cfg.guideUrl }] : [],
      });
    }

    removeStyleVariations();
  }

  var start = function () {
    whenEditorReady(onReady);
  };
  if (wp.domReady) wp.domReady(start);
  else document.addEventListener('DOMContentLoaded', start);
})();
