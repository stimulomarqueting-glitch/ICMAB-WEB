/**
 * WPForms TEST STUB — front-end behaviour (MatSurfer QA only, never shipped).
 * Emulates what the theme relies on from WPForms' wpforms.js:
 *   - token copied from data-token into wpforms[token]
 *   - client validation with the jQuery Validate conventions WPForms uses
 *     (data-msg-required / data-msg-email per element, global defaults
 *     otherwise), errors as <em class="wpforms-error"> (modern) or
 *     <label class="wpforms-error"> (classic), .wpforms-has-error on the field
 *   - AJAX submit to admin-ajax.php?action=wpforms_submit, button text from
 *     data-alt-text while sending, confirmation replacing the container
 *   - jQuery events: wpformsBeforeFormSubmit, wpformsAjaxSubmitSuccess,
 *     wpformsAjaxSubmitFailed, wpformsAjaxSubmitError,
 *     wpformsAjaxSubmitSuccessConfirmation
 */
(function ($, settings) {
  'use strict';
  if (!$) return;
  settings = settings || {};
  var modern = settings.engine !== 'classic';

  function message(el, rule) {
    var $el = $(el);
    var key = 'msg' + rule.charAt(0).toUpperCase() + rule.slice(1);
    return $el.data(key) || $el.attr('data-msg-' + rule) || (rule === 'email' ? settings.val_email : settings.val_required);
  }

  function clearError(el) {
    var $field = $(el).closest('.wpforms-field');
    $field.removeClass('wpforms-has-error');
    $field.find('.wpforms-error').not('input, select, textarea').remove();
    $field.find('input, select, textarea').removeClass('wpforms-error').removeAttr('aria-invalid');
  }

  function showError(el, text) {
    var $el = $(el);
    var $field = $el.closest('.wpforms-field');
    var id = $el.attr('id') || '';
    clearError(el);
    $field.addClass('wpforms-has-error');
    $el.addClass('wpforms-error').attr('aria-invalid', 'true');
    var $msg = modern
      ? $('<em class="wpforms-error" role="alert" aria-label="Error message"></em>').attr('id', id + '-error')
      : $('<label class="wpforms-error"></label>').attr({ id: id + '-error', for: id });
    $msg.text(text);
    var $after = $el.is(':checkbox') ? $el.closest('ul') : $el;
    $after.after($msg);
  }

  function validate($form) {
    var firstInvalid = null;
    var seen = {};
    $form.find('[required]').each(function () {
      var el = this;
      if (el.type === 'checkbox') {
        if (seen[el.name]) return;
        seen[el.name] = true;
        var checked = $form.find('input[name="' + el.name + '"]:checked').length > 0;
        if (!checked) {
          showError(el, message(el, 'required'));
          firstInvalid = firstInvalid || el;
        } else clearError(el);
        return;
      }
      var v = $.trim($(el).val() || '');
      if (!v) {
        showError(el, message(el, 'required'));
        firstInvalid = firstInvalid || el;
      } else if (el.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) {
        showError(el, message(el, 'email'));
        firstInvalid = firstInvalid || el;
      } else clearError(el);
    });
    if (firstInvalid) firstInvalid.focus();
    return !firstInvalid;
  }

  function init(form) {
    var $form = $(form);
    if ($form.data('stubReady')) return;
    $form.data('stubReady', true);
    $form.attr('novalidate', 'novalidate');
    if ($form.data('token')) {
      $('<input type="hidden" name="wpforms[token]">').val($form.data('token')).appendTo($form);
    }
    $form.on('input change', 'input, select, textarea', function () {
      if ($(this).hasClass('wpforms-error') || $(this).closest('.wpforms-has-error').length) {
        var ok = this.type === 'checkbox' ? this.checked : $.trim($(this).val() || '') !== '';
        if (ok) clearError(this);
      }
    });
    $form.on('submit', function (e) {
      if (!validate($form)) {
        e.preventDefault();
        return;
      }
      $form.trigger('wpformsBeforeFormSubmit');
      if (!$form.hasClass('wpforms-ajax-form')) return; // plain POST
      e.preventDefault();
      var $btn = $form.find('.wpforms-submit');
      var $spinner = $form.find('.wpforms-submit-spinner');
      $btn.prop('disabled', true).text($btn.data('alt-text') || $btn.text());
      $spinner.show();
      var data = new FormData(form);
      data.append('action', 'wpforms_submit');
      fetch(settings.ajaxurl, { method: 'POST', body: data, credentials: 'same-origin' })
        .then(function (res) {
          return res.json();
        })
        .then(function (json) {
          if (!json || !json.success) {
            var errs = (json && json.data && json.data.errors) || {};
            $.each(errs.field || {}, function (fieldId, text) {
              var $input = $form.find('[name="wpforms[fields][' + fieldId + ']"], [name="wpforms[fields][' + fieldId + '][]"]').first();
              if ($input.length) showError($input[0], text);
            });
            if (errs.general && errs.general.header) {
              $form.find('.wpforms-error-container').remove();
              $('<div class="wpforms-error-container" role="alert"><p></p></div>').find('p').text(errs.general.header).end().prependTo($form);
            }
            $form.trigger('wpformsAjaxSubmitFailed', json);
            return;
          }
          var $container = $form.closest('.wpforms-container');
          $form.trigger('wpformsAjaxSubmitSuccess', json);
          if (json.data && json.data.confirmation) {
            $container.html(json.data.confirmation);
            $container.trigger('wpformsAjaxSubmitSuccessConfirmation', json);
            var $conf = $container.find('.wpforms-confirmation-scroll');
            if ($conf.length && $conf[0].scrollIntoView) $conf[0].scrollIntoView({ block: 'center' });
          }
        })
        .catch(function (err) {
          $form.trigger('wpformsAjaxSubmitError', [err]);
        })
        .then(function () {
          $btn.prop('disabled', false).text($btn.data('submit-text') || $btn.text());
          $spinner.hide();
        });
    });
  }

  $(function () {
    $('.wpforms-form').each(function () {
      init(this);
    });
    $(document).trigger('wpformsReady');
  });
})(window.jQuery, window.wpforms_settings);
