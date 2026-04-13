/**
 * QuickShipD Delivery Date — Frontend
 * Countdown timers + variable-product AJAX. No jQuery. ES5-compatible.
 */
(function () {
	'use strict';

	// -- Countdown ----------------------------------------------------------

	function fmt(s) {
		var h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60);
		return h > 0 ? h + 'h ' + m + 'm' : m + 'm';
	}

	function tickAll() {
		document.querySelectorAll('.quickshipd-countdown[data-seconds]').forEach(function (el) {
			var s = parseInt(el.getAttribute('data-seconds'), 10) - 1;
			if (s <= 0) { el.style.display = 'none'; return; }
			el.setAttribute('data-seconds', s);
			var strong = el.querySelector('strong');
			if (strong) strong.textContent = fmt(s);
		});
	}

	function startCountdown() {
		if (document.querySelector('.quickshipd-countdown[data-seconds]')) {
			setInterval(tickAll, 1000);
		}
	}

	// -- Variation AJAX -----------------------------------------------------

	function updateVariation(variationId) {
		var el = document.querySelector('.quickshipd-variable');
		if (!el) return;

		if (!variationId) { el.style.display = 'none'; el.innerHTML = ''; return; }

		var url   = el.getAttribute('data-ajax') || (window.quickshipdData && window.quickshipdData.ajaxUrl) || '';
		var nonce = el.getAttribute('data-nonce') || (window.quickshipdData && window.quickshipdData.nonce) || '';
		if (!url) return;

		var body = 'action=quickshipd_variation_date&variation_id=' + variationId + '&nonce=' + encodeURIComponent(nonce);

		fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
			.then(function (r) { return r.json(); })
			.then(function (d) {
				if (d && d.success && d.data && d.data.html) {
					el.innerHTML = d.data.html;
					el.style.display = '';
					startCountdown();
				} else {
					el.style.display = 'none';
					el.innerHTML = '';
				}
			})
			.catch(function () { el.style.display = 'none'; });
	}

	function initVariation() {
		var form = document.querySelector('form.variations_form');
		if (!form) return;

		var inp = form.querySelector('input[name="variation_id"]');
		if (inp) {
			var last = inp.value;
			new MutationObserver(function () {
				if (inp.value !== last) { last = inp.value; updateVariation(last ? +last : 0); }
			}).observe(inp, { attributes: true, attributeFilter: ['value'] });
		}

		if (window.jQuery) {
			jQuery(form)
				.on('found_variation', function (e, v) { updateVariation(v ? v.variation_id : 0); })
				.on('reset_data', function () { updateVariation(0); });
		}
	}

	// -- Boot ---------------------------------------------------------------

	function init() { startCountdown(); initVariation(); }

	document.readyState === 'loading'
		? document.addEventListener('DOMContentLoaded', init)
		: init();
}());
