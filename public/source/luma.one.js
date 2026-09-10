/*Luma.one.js - A lightweight JS utilities library for luma.one.css framework */

(function () {
	// ES5-compatible utilities and graceful fallbacks for older browsers
	// Detect whether the environment supports ES6+ features. Only when ES6
	// support is missing should we fallback native alert/prompt/confirm.
	var supportsES6 = (function () {
		try {
			return typeof Promise === 'function' && typeof Symbol === 'function' && typeof Map === 'function' && typeof Set === 'function' && typeof Object.assign === 'function';
		} catch (e) {
			return false;
		}
	})();
	// expose flag for other modules that may run in separate IIFEs
	try { window.lmo_supportsES6 = !!supportsES6; } catch (e) { /* ignore */ }

	var raf = window.requestAnimationFrame || function (fn) { return setTimeout(fn, 16); };

	function _create(name, className) {
		var el = document.createElement(name);
		if (className) el.className = className;
		return el;
	}

	function extend(dst, src) {
		dst = dst || {};
		if (!src) return dst;
		for (var k in src) {
			if (Object.prototype.hasOwnProperty.call(src, k)) dst[k] = src[k];
		}
		return dst;
	}

	function showModal(message, options) {
		options = extend({ title: 'Thông báo', cancel: false }, options);

		// NOTE: We do NOT force native dialogs for low-performance devices.
		// Low-performance devices only disable heavy effects; dialogs should
		// remain the custom modals unless the environment lacks ES6 support.

		// If ES6 is not available, fallback to native dialogs and return
		// a thenable-like object so older environments still work.
		if (!supportsES6) {
			if (options.cancel) {
				var r = window.confirm(message);
				return { then: function (cb) { cb(!!r); } };
			}
			window.alert(message);
			return { then: function (cb) { if (cb) cb(true); } };
		}

		return new Promise(function (resolve) {
			var backdrop = _create('div', 'lmo-modal-backdrop');
			var modalWrap = _create('div', 'lmo-modal');
			var panel = _create('div', 'lmo-modal-panel');

			var titleEl = _create('h3');
			titleEl.textContent = options.title;

			var body = _create('div', 'lmo-modal-body');
			if (typeof message === 'string') body.textContent = message;
			else if (message && message.nodeType) body.appendChild(message);
			else body.textContent = String(message);

			var actions = _create('div', 'lmo-modal-actions');

			var okBtn = _create('button', 'lmo-btn lmo-btn-primary');
			okBtn.type = 'button';
			okBtn.textContent = 'OK';

			actions.appendChild(okBtn);

			var cancelBtn = null;
			if (options.cancel) {
				cancelBtn = _create('button', 'lmo-btn');
				cancelBtn.type = 'button';
				cancelBtn.textContent = 'Hủy';
				actions.insertBefore(cancelBtn, okBtn);
			}

			panel.appendChild(titleEl);
			panel.appendChild(body);
			panel.appendChild(actions);
			modalWrap.appendChild(panel);
			backdrop.appendChild(modalWrap);

			function cleanup() {
				if (backdrop && backdrop.parentNode) document.body.removeChild(backdrop);
				document.removeEventListener('keydown', onKey);
			}

			function onKey(e) {
				var key = e.key || e.keyIdentifier || e.keyCode;
				if (key === 'Escape' || key === 'Esc' || key === 27) {
					cleanup();
					resolve(false);
				}
				if (key === 'Enter' || key === 13) {
					cleanup();
					resolve(true);
				}
			}

			okBtn.addEventListener('click', function () {
				cleanup();
				resolve(true);
			});

			if (cancelBtn) {
				cancelBtn.addEventListener('click', function () {
					cleanup();
					resolve(false);
				});
			}

			backdrop.addEventListener('click', function (e) {
				if (e.target === backdrop) {
					cleanup();
					resolve(false);
				}
			});

			document.body.appendChild(backdrop);

			// trigger open states after appended so transitions apply
			raf(function () {
				if (backdrop.classList) backdrop.classList.add('lmo-open');
				if (modalWrap.classList) modalWrap.classList.add('lmo-open');
				try { okBtn.focus(); } catch (err) { }
			});

			document.addEventListener('keydown', onKey);
		});
	}

	// Expose API
	window.lmoAlert = function (message, options) {
		return showModal(message, options || {});
	};
})();

/* Toasts and helpers (ES5) */
(function () {
	var TOAST_CONTAINER_ID = 'lmo-toast-container';

	// local extend helper (ES5) — used by lmoPrompt
	function extend(dst, src) {
		dst = dst || {};
		if (!src) return dst;
		for (var k in src) {
			if (Object.prototype.hasOwnProperty.call(src, k)) dst[k] = src[k];
		}
		return dst;
	}

	function _ensureContainer() {
		var c = document.getElementById(TOAST_CONTAINER_ID);
		if (!c) {
			c = document.createElement('div');
			c.id = TOAST_CONTAINER_ID;
			c.className = 'lmo-toast-container';
			document.body.appendChild(c);
		}
		return c;
	}

	// detect low-performance / reduced-motion and add class to root to disable heavy effects
	try {
		var lowPerf = false;
		if (window.matchMedia) {
			if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) lowPerf = true;
			if (window.matchMedia('(pointer: coarse)').matches) lowPerf = true;
		}
		if (/MSIE|Trident/.test(navigator.userAgent)) lowPerf = true;
		if (lowPerf && document.documentElement) document.documentElement.className += ' lmo-lowperf';
		// expose flag for other modules (so modal can fallback too)
		try { window.lmo_lowperf = !!lowPerf; } catch (e) { /* ignore */ }
	} catch (e) { /* ignore */ }

	function lmoToast(message, opts) {
		opts = opts || {};
		var type = opts.type || 'info';
		var duration = typeof opts.duration === 'number' ? opts.duration : 3500;
		var container = _ensureContainer();

		var toast = document.createElement('div');
		toast.className = 'lmo-toast lmo-toast--' + type;

		var body = document.createElement('div');
		body.className = 'lmo-toast-body';
		body.textContent = String(message);

		// apply color to text according to toast type (no icon)
		var typeColors = { info: '#1d4ed8', success: '#059669', error: '#b91c1c', warn: '#9c9824' };
		if (typeColors[type]) body.style.color = typeColors[type];

		var close = document.createElement('button');
		close.className = 'lmo-toast-close';
		close.innerHTML = '✕';
		close.type = 'button';

		toast.appendChild(body);
		toast.appendChild(close);

		container.appendChild(toast);

		// show (use raf fallback)
		(window.requestAnimationFrame || function (fn) { return setTimeout(fn, 16); })(function () {
			if (toast.classList) toast.classList.add('lmo-show');
			else toast.className += ' lmo-show';
		});

		var removed = false;
		function remove() {
			if (removed) return;
			removed = true;
			if (toast.classList) toast.classList.remove('lmo-show');
			else toast.className = toast.className.replace(/\blmo-show\b/, '');
			setTimeout(function () {
				if (toast.parentNode) toast.parentNode.removeChild(toast);
			}, 220);
		}

		close.addEventListener('click', remove);

		if (duration > 0) setTimeout(remove, duration);

		return { remove: remove };
	}

	function lmoConfirm(message, options) {
		options = options || {};
		options.cancel = true;
		// rely on lmoAlert which already gracefully falls back when Promise is missing
		return window.lmoAlert(message, options);
	}

	function lmoPrompt(message, options) {
		options = extend({ title: 'Nhập giá trị', cancel: true, placeholder: '' }, options);
		// If ES6 (Promise/etc) not supported, use native prompt as fallback
		if (typeof window.lmo_supportsES6 === 'undefined' || !window.lmo_supportsES6) {
			var res = window.prompt(message, options.placeholder || '');
			return { then: function (cb) { if (cb) cb(res); } };
		}

		return new Promise(function (resolve) {
			var backdrop = document.createElement('div');
			backdrop.className = 'lmo-modal-backdrop';

			var modalWrap = document.createElement('div');
			modalWrap.className = 'lmo-modal';

			var panel = document.createElement('div');
			panel.className = 'lmo-modal-panel';

			var titleEl = document.createElement('h3');
			titleEl.textContent = options.title;

			var body = document.createElement('div');
			body.className = 'lmo-modal-body';

			var label = document.createElement('div');
			label.textContent = message;
			label.style.marginBottom = '8px';

			var input = document.createElement('input');
			input.type = 'text';
			input.placeholder = options.placeholder || '';
			input.style.width = '100%';
			input.style.padding = '10px';
			input.style.borderRadius = '8px';
			input.style.border = '1px solid rgba(0,0,0,0.08)';
			input.style.boxSizing = 'border-box';

			var actions = document.createElement('div');
			actions.className = 'lmo-modal-actions';

			var okBtn = document.createElement('button');
			okBtn.className = 'lmo-btn lmo-btn-primary';
			okBtn.type = 'button';
			okBtn.textContent = 'OK';

			var cancelBtn = document.createElement('button');
			cancelBtn.className = 'lmo-btn';
			cancelBtn.type = 'button';
			cancelBtn.textContent = 'Hủy';

			actions.appendChild(cancelBtn);
			actions.appendChild(okBtn);

			body.appendChild(label);
			body.appendChild(input);
			panel.appendChild(titleEl);
			panel.appendChild(body);
			panel.appendChild(actions);
			modalWrap.appendChild(panel);
			backdrop.appendChild(modalWrap);

			function cleanup() {
				if (backdrop.parentNode) backdrop.parentNode.removeChild(backdrop);
				document.removeEventListener('keydown', onKey);
			}

			function onKey(e) {
				var key = e.key || e.keyIdentifier || e.keyCode;
				if (key === 'Escape' || key === 'Esc' || key === 27) {
					cleanup();
					resolve(null);
				}
				if (key === 'Enter' || key === 13) {
					cleanup();
					resolve(input.value);
				}
			}

			okBtn.addEventListener('click', function () {
				cleanup();
				resolve(input.value);
			});

			cancelBtn.addEventListener('click', function () {
				cleanup();
				resolve(null);
			});

			backdrop.addEventListener('click', function (e) {
				if (e.target === backdrop) {
					cleanup();
					resolve(null);
				}
			});

			document.body.appendChild(backdrop);
			(window.requestAnimationFrame || function (fn) { return setTimeout(fn, 16); })(function () {
				if (backdrop.classList) backdrop.classList.add('lmo-open');
				if (modalWrap.classList) modalWrap.classList.add('lmo-open');
				try { input.focus(); } catch (err) { }
			});

			document.addEventListener('keydown', onKey);
		});
	}

	// expose
	window.lmoToast = lmoToast;
	window.lmoConfirm = lmoConfirm;
	window.lmoPrompt = lmoPrompt;

})();

(function () {
	function addClass(el, name) {
		if (!el) return;
		if (el.classList) el.classList.add(name);
		else if (el.className.indexOf(name) === -1) el.className += ' ' + name;
	}

	function removeClass(el, name) {
		if (!el) return;
		if (el.classList) el.classList.remove(name);
		else el.className = el.className.replace(new RegExp('\\b' + name + '\\b', 'g'), '');
	}

	function closeAlert(alertEl) {
		if (!alertEl) return;
		addClass(alertEl, 'lmo-hidden');
		alertEl.setAttribute('aria-hidden', 'true');
	}

	function initAlerts() {
		var triggers = document.querySelectorAll && document.querySelectorAll('[data-lmo-dismiss="alert"]');
		if (!triggers || !triggers.length) return;
		for (var i = 0; i < triggers.length; i++) {
			triggers[i].addEventListener('click', function (e) {
				e.preventDefault();
				var parent = this.parentNode;
				while (parent && parent !== document.body) {
					if (parent.className && parent.className.indexOf('lmo-alert') !== -1) {
						closeAlert(parent);
						break;
					}
					parent = parent.parentNode;
				}
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAlerts);
	} else {
		initAlerts();
	}
})();

	/* Navbar behavior: init auto-hide navbars with class `lmo-navbar--autohide` */
	(function () {
		function addClass(el, name) {
			if (!el) return;
			if (el.classList) el.classList.add(name);
			else if (el.className.indexOf(name) === -1) el.className += ' ' + name;
		}

		function removeClass(el, name) {
			if (!el) return;
			if (el.classList) el.classList.remove(name);
			else el.className = el.className.replace(new RegExp('\\b' + name + '\\b', 'g'), '');
		}

		function attachAutoHide(nav) {
			if (!nav) return;
			var lastY = (window.pageYOffset !== undefined) ? window.pageYOffset : (document.documentElement.scrollTop || document.body.scrollTop);
			var ticking = false;
			var threshold = 10;

			function onScroll() {
				var y = (window.pageYOffset !== undefined) ? window.pageYOffset : (document.documentElement.scrollTop || document.body.scrollTop);
				if (!ticking) {
					(window.requestAnimationFrame || function (fn) { return setTimeout(fn, 16); })(function () {
						var diff = y - lastY;
						if (Math.abs(diff) > threshold) {
							if (diff > 0 && y > 80) {
								addClass(nav, 'lmo-hidden');
							} else {
								removeClass(nav, 'lmo-hidden');
							}
							lastY = y;
						}
						ticking = false;
					});
					ticking = true;
				}
			}

			// when focus or hovering near top, ensure nav visible
			function showNow() { removeClass(nav, 'lmo-hidden'); }

			window.addEventListener('scroll', onScroll, false);
			window.addEventListener('resize', showNow, false);
			nav.addEventListener('mouseenter', showNow, false);
			nav.addEventListener('focusin', showNow, false);
		}

		// Initialize on DOM ready
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', function () {
				var nodes = document.querySelectorAll && document.querySelectorAll('.lmo-navbar--autohide');
				if (nodes && nodes.length) {
					for (var i = 0; i < nodes.length; i++) attachAutoHide(nodes[i]);
				}
			});
		} else {
			var nodes = document.querySelectorAll && document.querySelectorAll('.lmo-navbar--autohide');
			if (nodes && nodes.length) {
				for (var j = 0; j < nodes.length; j++) attachAutoHide(nodes[j]);
			}
		}
	})();
