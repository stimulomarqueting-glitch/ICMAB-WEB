/**
 * MatSurfer — global front-end behaviour, loaded on every public page
 * (inc/assets.php, deferred). Plain ES2017, no modules, no dependencies.
 *
 *  1. Lead tracking (port of src/scripts/leadTracking.ts)
 *     - captureUtmParams(): utm_* params of the landing URL are kept in
 *       sessionStorage 'lead:utm' for the session (first landing wins)
 *     - setupCtaTracking(): a click on any [data-cta-source] element stores
 *       its source in sessionStorage 'lead:cta_source' and sends a
 *       'cta_click' event
 *     - trackEvent(name, data): dispatches window CustomEvent 'lead:track'
 *       with detail { name, data } — the single integration point for an
 *       analytics tool (GTM, Plausible…: listen to 'lead:track')
 *     Public API for the contact form and other scripts:
 *       window.matsLead = { trackEvent, getUtm, getCtaSource }
 *  2. Scroll reveal (port of BaseLayout.astro): .reveal elements get
 *     .is-visible when they enter the viewport (threshold 0.12, root margin
 *     0 0 -40px 0); everything is shown at once with prefers-reduced-motion
 *     or without IntersectionObserver. base.css hides .reveal (motion-safe
 *     users) only while <html> has the class 'mats-js', which the inline
 *     script of header.php adds before the styles load and removes again
 *     after 3 s unless this script has marked <html class="mats-ready">
 *     (done in init(), below). So without JavaScript, or when this file
 *     is blocked or fails, the sections are simply visible (chrome.css).
 *  3. Header menu (port of the Header.astro script): the burger toggles the
 *     menu under 64rem; Escape closes it and returns the focus to the
 *     burger; following a link closes it.
 */
(function () {
  'use strict';

  var CTA_KEY = 'lead:cta_source';
  var UTM_KEY = 'lead:utm';
  var UTM_FIELDS = ['source', 'medium', 'campaign', 'content', 'term'];

  if (window.matsLead && window.matsLead.ready) return; // loaded twice

  /* ── 1. lead tracking ──────────────────────────────────────────────── */

  /* sessionStorage can throw (private mode, blocked storage) — never let
     tracking break the page. */
  function storageGet(key) {
    try {
      return window.sessionStorage.getItem(key);
    } catch (e) {
      return null;
    }
  }

  function storageSet(key, value) {
    try {
      window.sessionStorage.setItem(key, value);
    } catch (e) {
      /* ignore */
    }
  }

  /**
   * Analytics wrapper: DOM CustomEvent 'lead:track' on window (so GTM or
   * any script can listen) + console.debug when window.matsDebug is true.
   *
   * INTEGRATION POINT — when an analytics tool exists, listen here, e.g.:
   *   window.addEventListener('lead:track', function (e) {
   *     window.dataLayer && window.dataLayer.push(Object.assign({ event: e.detail.name }, e.detail.data));
   *   });
   */
  function trackEvent(name, data) {
    var detail = { name: String(name || ''), data: data && typeof data === 'object' ? data : {} };
    try {
      var event;
      if (typeof window.CustomEvent === 'function') {
        event = new CustomEvent('lead:track', { detail: detail });
      } else {
        event = document.createEvent('CustomEvent');
        event.initCustomEvent('lead:track', false, false, detail);
      }
      window.dispatchEvent(event);
    } catch (e) {
      /* never let tracking break the page */
    }
    if (window.matsDebug && window.console && console.debug) {
      console.debug('[lead:track]', detail.name, detail.data);
    }
  }

  function emptyUtm() {
    return { source: '', medium: '', campaign: '', content: '', term: '' };
  }

  /** Store utm_* params once per session (first landing wins). */
  function captureUtmParams() {
    if (storageGet(UTM_KEY)) return;
    var params;
    try {
      params = new URLSearchParams(window.location.search);
    } catch (e) {
      return;
    }
    var utm = emptyUtm();
    var any = false;
    UTM_FIELDS.forEach(function (field) {
      var value = params.get('utm_' + field);
      utm[field] = value == null ? '' : value;
      if (utm[field] !== '') any = true;
    });
    if (any) storageSet(UTM_KEY, JSON.stringify(utm));
  }

  /** The stored utm_* params ({ source, medium, campaign, content, term }). */
  function getUtm() {
    var out = emptyUtm();
    var raw = storageGet(UTM_KEY);
    if (!raw) return out;
    try {
      var saved = JSON.parse(raw);
      if (saved && typeof saved === 'object') {
        UTM_FIELDS.forEach(function (field) {
          if (typeof saved[field] === 'string') out[field] = saved[field];
        });
      }
    } catch (e) {
      /* corrupted value: behave as if there was none */
    }
    return out;
  }

  /**
   * Delegated listener: any element with data-cta-source="hero|header|…"
   * records itself as the origin of the visit to the form.
   */
  function setupCtaTracking() {
    document.addEventListener('click', function (e) {
      var target = e.target;
      if (!target || typeof target.closest !== 'function') return;
      var el = target.closest('[data-cta-source]');
      if (!el) return;
      var source = el.getAttribute('data-cta-source') || '';
      if (source) {
        storageSet(CTA_KEY, source);
        trackEvent('cta_click', { source: source });
      }
    });
  }

  /** 'direct' when the user reached the form without clicking any tracked CTA. */
  function getCtaSource() {
    return storageGet(CTA_KEY) || 'direct';
  }

  window.matsLead = {
    trackEvent: trackEvent,
    getUtm: getUtm,
    getCtaSource: getCtaSource,
    ready: true,
  };

  captureUtmParams();
  setupCtaTracking();

  /* ── 2. scroll reveal ──────────────────────────────────────────────── */

  function setupReveal() {
    var targets = document.querySelectorAll('.reveal');
    if (!targets.length) return;
    var show = function (el) {
      el.classList.add('is-visible');
    };
    var reduced = false;
    try {
      reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    } catch (e) {
      reduced = false;
    }
    if (reduced || !('IntersectionObserver' in window)) {
      Array.prototype.forEach.call(targets, show);
      return;
    }
    var io = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            show(entry.target);
            io.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.12, rootMargin: '0px 0px -40px 0px' }
    );
    Array.prototype.forEach.call(targets, function (el) {
      io.observe(el);
    });
    /* a printed page shows everything */
    window.addEventListener('beforeprint', function () {
      Array.prototype.forEach.call(document.querySelectorAll('.reveal'), show);
    });
  }

  /* ── 3. header menu ────────────────────────────────────────────────── */

  function setupMenu() {
    var burger = document.querySelector('.header__burger');
    var nav = document.querySelector('.header__nav');
    if (!burger || !nav) return;

    var isOpen = function () {
      return burger.getAttribute('aria-expanded') === 'true';
    };

    var setOpen = function (open) {
      burger.setAttribute('aria-expanded', String(open));
      burger.setAttribute(
        'aria-label',
        open ? burger.getAttribute('data-label-close') || 'Close menu' : burger.getAttribute('data-label-open') || 'Open menu'
      );
      nav.classList.toggle('is-open', open);
    };

    burger.addEventListener('click', function () {
      setOpen(!isOpen());
    });

    nav.addEventListener('click', function (e) {
      var target = e.target;
      if (target && typeof target.closest === 'function' && target.closest('a')) setOpen(false);
    });

    document.addEventListener('keydown', function (e) {
      if ((e.key === 'Escape' || e.key === 'Esc') && isOpen()) {
        setOpen(false);
        burger.focus();
      }
    });

    /* back to the desktop layout: the menu is no longer a panel */
    try {
      var desktop = window.matchMedia('(min-width: 64.0625rem)');
      var onChange = function (mq) {
        if (mq.matches && isOpen()) setOpen(false);
      };
      if (typeof desktop.addEventListener === 'function') desktop.addEventListener('change', onChange);
      else if (typeof desktop.addListener === 'function') desktop.addListener(onChange);
    } catch (e) {
      /* old browser: nothing to do */
    }
  }

  function init() {
    setupReveal();
    /* the reveal is running: the inline script of header.php keeps the hidden state */
    document.documentElement.classList.add('mats-ready');
    setupMenu();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
