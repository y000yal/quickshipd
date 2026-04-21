/**
 * QuickShipD Delivery Date — Frontend
 * Countdown timers + variable-product AJAX. No jQuery. ES5-compatible.
 */
(function () {
	'use strict';

	// -- Countdown ----------------------------------------------------------

	function fmt(s, showSecs) {
		var h  = Math.floor(s / 3600);
		var m  = Math.floor((s % 3600) / 60);
		var sc = s % 60;
		if (showSecs) {
			if (h > 0) return h + 'h ' + m + 'm ' + sc + 's';
			if (m > 0) return m + 'm ' + sc + 's';
			return sc + 's';
		}
		if (h > 0) return h + 'h ' + m + 'm';
		return (m > 0 ? m : 1) + 'm';
	}

	function tickAll() {
		document.querySelectorAll('.quickshipd-countdown[data-seconds]').forEach(function (el) {
			var s        = parseInt(el.getAttribute('data-seconds'), 10) - 1;
			if (s <= 0) { el.style.display = 'none'; return; }
			el.setAttribute('data-seconds', s);
			var showSecs = el.getAttribute('data-show-seconds') === '1';
			var strong   = el.querySelector('strong');
			if (strong) strong.textContent = fmt(s, showSecs);
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

	// -- Checkout shipping skeleton -----------------------------------------
	// Block checkout: capture click on shipping radios BEFORE React processes
	// it, swap est-delivery content with skeleton. React re-render replaces
	// the element entirely, clearing the skeleton naturally.
	// Classic checkout: jQuery update_checkout event.

	var SKELETON_HTML =
		'<div class="quickshipd-checkout-skeleton">' +
			'<span class="quickshipd-skel-line quickshipd-skel-line--lg"></span>' +
			'<span class="quickshipd-skel-line quickshipd-skel-line--sm"></span>' +
		'</div>';

	function showBlockSkeletons() {
		var els = document.querySelectorAll( '.wc-block-components-product-details__est-delivery' );
		els.forEach( function ( el ) {
			el.innerHTML = SKELETON_HTML;
		} );
	}

	function initBlockCheckoutSkeleton() {
		// Use capturing phase so we fire BEFORE React's synthetic event system.
		document.addEventListener( 'click', function ( e ) {
			// Match shipping rate radio inputs inside the shipping section.
			var radio = e.target.closest && e.target.closest( '.wc-block-components-radio-control__input' );
			if ( !radio ) return;

			// Only care about radios inside the shipping rates section.
			var shippingSection = radio.closest( '.wc-block-components-shipping-rates-control' );
			if ( !shippingSection ) return;

			// Small delay lets the click register, then we swap before React re-renders.
			// requestAnimationFrame fires before the next paint — perfect timing.
			requestAnimationFrame( function () {
				showBlockSkeletons();
			} );
		}, true ); // <-- capturing phase
	}

	function initClassicCheckoutSkeleton() {
		// Classic checkout: jQuery update_checkout event.
		if ( !window.jQuery ) return;
		var wrap = document.getElementById( 'quickshipd-checkout-delivery' );
		if ( !wrap ) return;
		jQuery( document.body ).on( 'update_checkout', function () {
			var td = wrap.querySelector( 'td' );
			if ( td && !td.querySelector( '.quickshipd-checkout-skeleton' ) ) {
				td.setAttribute( 'data-qs-saved', td.innerHTML );
				td.innerHTML = SKELETON_HTML;
			}
		} );
	}

	function initCheckoutSkeleton() {
		// Block checkout.
		if ( document.querySelector( '.wp-block-woocommerce-checkout' ) ) {
			initBlockCheckoutSkeleton();
		}
		// Classic checkout.
		initClassicCheckoutSkeleton();
	}

	// -- Boot ---------------------------------------------------------------

	function init() { startCountdown(); initVariation(); initCheckoutSkeleton(); }

	document.readyState === 'loading'
		? document.addEventListener('DOMContentLoaded', init)
		: init();
}());
