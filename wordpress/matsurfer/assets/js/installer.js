/**
 * Setup wizard (MatSurfer → Puesta en marcha).
 *
 * Progressive enhancement of the single form of the screen: each button
 * (name="step") runs through admin-ajax.php instead of reloading the page,
 * ONE request per step — a plugin activated in one request is only loaded
 * in the next one, so "Hacerlo todo" chains the steps. After running, the
 * ✓/✗ state of every card is refreshed from the server.
 * Without fetch() (or JS) the form simply posts to admin-post.php.
 */
(function () {
	'use strict';

	var cfg = window.matsInstaller;
	var form = document.getElementById('mats-setup-form');
	if (!cfg || !form || !window.fetch || !window.URLSearchParams) {
		return;
	}

	var running = false;
	var lastButton = null;
	var live = form.querySelector('.mats-setup__live');
	var overwriteBox = form.querySelector('.mats-setup__overwrite');
	var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	function card(step) {
		return document.getElementById('mats-step-' + step);
	}

	function announce(text) {
		if (!live) {
			return;
		}
		live.textContent = '';
		var p = document.createElement('p');
		p.textContent = text;
		live.appendChild(p);
	}

	function setBusy(on) {
		form.classList.toggle('is-busy', on);
		form.setAttribute('aria-busy', on ? 'true' : 'false');
		Array.prototype.forEach.call(form.querySelectorAll('button, input'), function (el) {
			el.disabled = on;
		});
	}

	function setRunning(step, on) {
		var el = card(step);
		if (!el) {
			return;
		}
		el.classList.toggle('is-running', on);
		var spinner = el.querySelector('.mats-step__spinner');
		if (spinner) {
			spinner.classList.toggle('is-active', on);
		}
		if (on && el.scrollIntoView) {
			el.scrollIntoView({ block: 'nearest', behavior: reduceMotion ? 'auto' : 'smooth' });
		}
	}

	/** Message list built with textContent (never raw HTML from the client). */
	function messageBox(type, text) {
		var wrap = document.createElement('div');
		wrap.className = 'mats-step__report';
		var ul = document.createElement('ul');
		ul.className = 'mats-step__messages';
		var li = document.createElement('li');
		li.className = 'is-' + type;
		var icon = document.createElement('span');
		icon.className = 'mats-step__icon';
		icon.setAttribute('aria-hidden', 'true');
		icon.textContent = type === 'error' ? '✗' : 'i';
		var span = document.createElement('span');
		span.className = 'mats-step__text';
		span.textContent = text;
		li.appendChild(icon);
		li.appendChild(span);
		ul.appendChild(li);
		wrap.appendChild(ul);
		return wrap;
	}

	function post(params) {
		var body = new URLSearchParams();
		Object.keys(params).forEach(function (key) {
			body.append(key, params[key]);
		});
		body.append('_ajax_nonce', cfg.nonce);
		/* Polylang loads its admin side for AJAX only with this flag */
		body.append('pll_ajax_backend', '1');
		return fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { Accept: 'application/json' },
			body: body
		}).then(function (response) {
			return response.text().then(function (text) {
				var json = null;
				try {
					json = JSON.parse(text);
				} catch (e) {
					/* hosts that print PHP notices before the answer: keep the JSON part */
					var start = text.indexOf('{"success"');
					try {
						json = start >= 0 ? JSON.parse(text.slice(start)) : null;
					} catch (e2) {
						json = null;
					}
				}
				if (!json || !json.success) {
					var msg = json && json.data && json.data.message ? json.data.message : 'HTTP ' + response.status;
					throw new Error(msg);
				}
				return json.data;
			});
		});
	}

	/* the server renders the messages with every value escaped (esc_html / esc_url) */
	function setHtml(el, html) {
		if (el) {
			el.innerHTML = html;
		}
	}

	function runStep(step, overwrite) {
		var target = step === 'smtp' ? 'plugins' : step;
		var el = card(target);
		var result = el ? el.querySelector('.mats-step__result') : null;
		setRunning(target, true);
		announce(cfg.text.stepStart.replace('%s', cfg.titles[target] || target));
		if (result) {
			result.textContent = '';
			var p = document.createElement('p');
			p.className = 'mats-step__working';
			p.textContent = cfg.text.working;
			result.appendChild(p);
		}
		/* overwrite is only sent once the person has accepted the confirmation dialog */
		return post({ action: 'mats_installer_step', step: step, overwrite: overwrite ? '1' : '0', confirm: overwrite ? '1' : '0' })
			.then(function (data) {
				setHtml(result, data.html || '');
				return data;
			})
			.catch(function (err) {
				if (result) {
					result.textContent = '';
					result.appendChild(messageBox('error', cfg.text.failed + (err && err.message ? ' (' + err.message + ')' : '')));
				}
				return { ok: false };
			})
			.then(function (data) {
				setRunning(target, false);
				return data;
			});
	}

	function refresh() {
		return post({ action: 'mats_installer_status' })
			.then(function (data) {
				Object.keys(data.steps || {}).forEach(function (key) {
					var s = data.steps[key];
					var el = card(key);
					if (!el) {
						return;
					}
					el.setAttribute('data-state', s.state);
					setHtml(el.querySelector('.mats-step__status'), s.pill);
					setHtml(el.querySelector('.mats-step__state'), s.checks);
					var button = el.querySelector('button[value="' + key + '"]');
					if (button && s.button) {
						button.textContent = s.button;
					}
				});
				if (data.smtp) {
					var smtp = form.querySelector('button[value="smtp"]');
					if (smtp) {
						smtp.parentNode.removeChild(smtp);
					}
				}
				setHtml(form.querySelector('.mats-setup__summary'), data.summary || '');
			})
			.catch(function () {
				/* the step results are already on screen; a reload shows the state */
			});
	}

	form.addEventListener('click', function (ev) {
		var button = ev.target && ev.target.closest ? ev.target.closest('button[name="step"]') : null;
		if (button) {
			lastButton = button;
		}
	});

	form.addEventListener('submit', function (ev) {
		var button = ev.submitter || lastButton;
		var step = button && button.name === 'step' ? button.value : '';
		if (!step) {
			return; // let the browser post the form
		}
		ev.preventDefault();
		if (running) {
			return;
		}
		var steps = step === 'all' ? cfg.steps.slice() : [step];
		/* only step 3 (also inside "Hacerlo todo") can overwrite the page texts */
		var overwrite = !!(overwriteBox && overwriteBox.checked) && steps.indexOf('content') !== -1;
		if (overwrite && !window.confirm(cfg.text.confirm)) {
			return;
		}

		running = true;
		setBusy(true);
		var allOk = true;
		var chain = Promise.resolve();
		steps.forEach(function (s) {
			chain = chain.then(function () {
				return runStep(s, overwrite && s === 'content').then(function (data) {
					if (!data || !data.ok) {
						allOk = false;
					}
				});
			});
		});
		chain
			.then(refresh)
			.then(function () {
				running = false;
				setBusy(false);
				if (overwriteBox && steps.indexOf('content') !== -1) {
					overwriteBox.checked = false; // never keep it armed for the next click
				}
				announce(step === 'all' ? (allOk ? cfg.text.allDone : cfg.text.allWarn) : cfg.text.stepDone);
				if (step === 'all') {
					var review = card('review');
					if (review && review.scrollIntoView) {
						review.scrollIntoView({ block: 'start', behavior: reduceMotion ? 'auto' : 'smooth' });
					}
				}
			});
	});
})();
