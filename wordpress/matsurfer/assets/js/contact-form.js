/**
 * Contact form behaviour around the WPForms form in the contact section
 * (port of the <script> of src/components/LeadForm.astro).
 *
 *  - ?interest=product|application|research|partnership|distribution|<value>
 *    preselects "I am interested in" (field 5) and ?application=<slug>
 *    preselects "Area of interest" (field 6). WPForms uses the option LABEL
 *    as value, so values are mapped to the labels of the page's language.
 *  - fills the hidden matsurfer_meta[…] inputs (cta_source and utm from
 *    sessionStorage 'lead:cta_source' / 'lead:utm', written by site.js;
 *    application, language and source page from the URL)
 *  - per-field error messages from the original copy (data-msg-* read by
 *    WPForms' validation), browser autofill hints
 *  - lead:track events like the original form: form_start,
 *    form_submit_attempt, form_submit_success, form_submit_error
 *  - when sending fails (network or server error, or a refusal without
 *    field errors) the original message is shown under the button, in the
 *    page's language, with the contact email (.lf__status, role="alert")
 *  - the placeholder of the optional "Area of interest" select can be
 *    chosen again (WPForms renders it disabled; the original did not)
 *
 * Config: data-lf-config on .lf__card (inc/modules/wpforms.php →
 * mats_wpforms_client_config). Plain ES2017, no dependencies; jQuery is used
 * only when present, to hear WPForms' own events.
 */
(function () {
  'use strict';

  var CTA_KEY = 'lead:cta_source';
  var UTM_KEY = 'lead:utm';

  function storageGet(key) {
    try {
      return window.sessionStorage.getItem(key);
    } catch (e) {
      return null;
    }
  }

  function track(name, data) {
    try {
      window.dispatchEvent(new CustomEvent('lead:track', { detail: { name: name, data: data || {} } }));
    } catch (e) {
      /* never let tracking break the form */
    }
  }

  function readConfig(card) {
    try {
      var cfg = JSON.parse(card.getAttribute('data-lf-config') || '{}');
      return cfg && typeof cfg === 'object' ? cfg : {};
    } catch (e) {
      return {};
    }
  }

  function norm(text) {
    return String(text == null ? '' : text).replace(/\s+/g, ' ').trim().toLowerCase();
  }

  /** Field element(s) by WPForms field ID (name="wpforms[fields][N]"). */
  function fieldInput(form, id) {
    return form.querySelector('[name="wpforms[fields][' + id + ']"]') || form.querySelector('[name="wpforms[fields][' + id + '][]"]');
  }

  /**
   * Selects the option whose label is `label`; falls back to the same
   * position as the original option list when staff renamed the options.
   */
  function selectOption(select, label, index, total) {
    if (!select || !label) return false;
    var options = Array.prototype.slice.call(select.options);
    var real = options.filter(function (o) {
      return o.value !== '';
    });
    var match = null;
    var wanted = norm(label);
    real.forEach(function (o) {
      if (!match && (norm(o.value) === wanted || norm(o.text) === wanted)) match = o;
    });
    if (!match && index > -1 && real.length === total) match = real[index];
    if (!match) return false;
    select.value = match.value;
    try {
      select.dispatchEvent(new Event('change', { bubbles: true }));
    } catch (e) {
      /* old browsers */
    }
    if (window.jQuery) window.jQuery(select).trigger('change');
    return true;
  }

  function preselect(form, cfg) {
    var params = new URLSearchParams(window.location.search);
    var rawInterest = params.get('interest') || '';
    if (rawInterest) {
      var map = cfg.interestMap || {};
      var value = Object.prototype.hasOwnProperty.call(map, rawInterest) ? map[rawInterest] : rawInterest;
      var keys = Object.keys(cfg.interest || {});
      var label = (cfg.interest || {})[value] || '';
      selectOption(fieldInput(form, 5), label, keys.indexOf(value), keys.length);
    }
    var slug = params.get('application') || '';
    if (slug) {
      var areaKeys = Object.keys(cfg.area || {});
      var areaLabel = (cfg.area || {})[slug] || '';
      selectOption(fieldInput(form, 6), areaLabel, areaKeys.indexOf(slug), areaKeys.length);
    }
    return slug;
  }

  function setMeta(form, key, value) {
    var input = form.querySelector('input[name="matsurfer_meta[' + key + ']"]');
    if (input) input.value = value == null ? '' : String(value);
  }

  function fillMeta(form, cfg, slug) {
    setMeta(form, 'cta_source', storageGet(CTA_KEY) || 'direct');
    setMeta(form, 'utm', storageGet(UTM_KEY) || '');
    setMeta(form, 'application', slug || '');
    setMeta(form, 'language', cfg.lang || document.documentElement.lang || '');
    setMeta(form, 'source_page', window.location.pathname);
  }

  /** Original per-field messages, read by jQuery Validate (data-msg-*). */
  function messages(form, cfg) {
    var all = cfg.messages || {};
    Object.keys(all).forEach(function (id) {
      var nodes = form.querySelectorAll('[name="wpforms[fields][' + id + ']"], [name="wpforms[fields][' + id + '][]"]');
      Array.prototype.forEach.call(nodes, function (node) {
        Object.keys(all[id]).forEach(function (rule) {
          var text = all[id][rule];
          if (!text) return;
          node.setAttribute('data-msg-' + rule, text);
          if (window.jQuery) {
            window.jQuery(node).data('msg' + rule.charAt(0).toUpperCase() + rule.slice(1), text);
          }
        });
      });
    });
    var auto = cfg.autocomplete || {};
    Object.keys(auto).forEach(function (id) {
      var input = fieldInput(form, id);
      if (input && !input.getAttribute('autocomplete')) input.setAttribute('autocomplete', auto[id]);
    });
  }

  /** Selected option of field 5/6 as the original option value (label → value). */
  function selectedValue(form, id, options) {
    var el = fieldInput(form, id);
    var label = el && el.value ? el.value : '';
    if (!label) return '';
    var wanted = norm(label);
    var keys = Object.keys(options || {});
    for (var i = 0; i < keys.length; i++) {
      if (norm(options[keys[i]]) === wanted) return keys[i];
    }
    return label;
  }

  /** Non-personal qualification data only — safe for analytics. */
  function eventProps(form, slug, cfg) {
    return {
      ctaSource: storageGet(CTA_KEY) || 'direct',
      interest: selectedValue(form, 5, cfg.interest),
      areaOfInterest: selectedValue(form, 6, cfg.area),
      application: slug || '',
      sourcePage: window.location.pathname,
    };
  }

  /** The optional select (field 6) can go back to "Select…", like the original. */
  function freeAreaPlaceholder(form) {
    var select = fieldInput(form, 6);
    if (!select || select.required || select.hasAttribute('required')) return;
    Array.prototype.forEach.call(select.options, function (o) {
      if (o.value === '' && o.disabled) o.disabled = false;
    });
  }

  /** Failure message under the button: errorText + mailto link (built as DOM, no HTML). */
  function showStatus(form, cfg) {
    if (!cfg.errorText) return;
    var box = form.querySelector('.lf__status');
    if (!box) {
      box = document.createElement('p');
      box.className = 'lf__status';
      box.setAttribute('data-status', 'error');
      box.setAttribute('role', 'alert');
      box.appendChild(document.createTextNode(cfg.errorText + ' '));
      if (cfg.email) {
        var link = document.createElement('a');
        link.href = 'mailto:' + cfg.email;
        link.textContent = cfg.email;
        box.appendChild(link);
        box.appendChild(document.createTextNode('.'));
      }
      form.appendChild(box);
    }
    box.hidden = false;
  }

  function hideStatus(form) {
    var box = form.querySelector('.lf__status');
    if (box) box.hidden = true;
  }

  /** WPForms answered "not sent" with errors on fields (shown by WPForms itself)? */
  function hasFieldErrors(json) {
    var errors = json && json.data && json.data.errors;
    var field = errors && errors.field;
    return !!(field && typeof field === 'object' && Object.keys(field).length);
  }

  function focusConfirmation(card) {
    var box = card.querySelector('.wpforms-confirmation-container-full, [id^="wpforms-confirmation"]');
    if (!box) return;
    box.setAttribute('tabindex', '-1');
    if (!box.getAttribute('role')) box.setAttribute('role', 'status');
    try {
      box.focus({ preventScroll: true });
    } catch (e) {
      box.focus();
    }
  }

  function init(card) {
    if (card.getAttribute('data-lf-ready')) return;
    card.setAttribute('data-lf-ready', '1');
    var cfg = readConfig(card);
    var form = card.querySelector('form.wpforms-form, form[id^="wpforms-form-"]');

    /* non-AJAX fallback: the page came back with the thank-you message */
    if (!form) {
      if (card.querySelector('.wpforms-confirmation-container-full')) {
        track('form_submit_success', { sourcePage: window.location.pathname, ctaSource: storageGet(CTA_KEY) || 'direct' });
        focusConfirmation(card);
      }
      return;
    }

    freeAreaPlaceholder(form);
    var slug = preselect(form, cfg);
    fillMeta(form, cfg, slug);
    messages(form, cfg);

    var props = eventProps(form, slug, cfg);
    var started = false;
    var done = false;

    form.addEventListener('focusin', function () {
      if (started) return;
      started = true;
      track('form_start', {});
    });

    /* capture: runs before WPForms' own submit handling */
    form.addEventListener(
      'submit',
      function () {
        hideStatus(form);
        fillMeta(form, cfg, slug);
        props = eventProps(form, slug, cfg);
        track('form_submit_attempt', {});
      },
      true
    );

    function success() {
      if (done) return;
      done = true;
      track('form_submit_success', props);
      window.setTimeout(function () {
        focusConfirmation(card);
      }, 60);
    }

    /* wpformsAjaxSubmitError: no answer (network, server error).
       wpformsAjaxSubmitFailed: WPForms refused the submission; with field
       errors WPForms marks the fields itself, otherwise (anti-spam check,
       expired token…) the visitor also gets the email to write to. */
    function failure(e, json) {
      track('form_submit_error', props);
      if (e && e.type === 'wpformsAjaxSubmitFailed' && hasFieldErrors(json)) return;
      showStatus(form, cfg);
    }

    if (window.jQuery) {
      var $ = window.jQuery;
      $(form).on('wpformsAjaxSubmitSuccess', success);
      $(form).on('wpformsAjaxSubmitFailed wpformsAjaxSubmitError', failure);
      $(card).on('wpformsAjaxSubmitSuccessConfirmation', success);
    }
    form.addEventListener('wpformsAjaxSubmitSuccess', success);

    /* last resort: the confirmation replaced the form */
    if ('MutationObserver' in window) {
      var observer = new MutationObserver(function () {
        if (card.querySelector('.wpforms-confirmation-container-full')) {
          success();
          observer.disconnect();
        }
      });
      observer.observe(card, { childList: true, subtree: true });
    }
  }

  function boot() {
    Array.prototype.forEach.call(document.querySelectorAll('.lf__card[data-lf-config]'), init);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
