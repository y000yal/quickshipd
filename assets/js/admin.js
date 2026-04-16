/**
 * QuickShipD Delivery Date — Admin JS
 *
 * - jQuery tab switching (no page reload)
 * - AJAX save (only saves the active tab's fields)
 * - Pure-JS live preview (no AJAX — uses form values directly)
 * - WordPress color picker init
 */

(function ($) {
	'use strict';

	var cfg = window.quickshipdAdmin || {};

	/* ---------------------------------------------------------------- */
	/* Color picker                                                      */
	/* ---------------------------------------------------------------- */

	function initColorPickers() {
		$('.quickshipd-color-picker').wpColorPicker({
			change: function () { schedulePreview(); },
			clear:  function () { schedulePreview(); }
		});
	}

	/* ---------------------------------------------------------------- */
	/* Tab switching                                                     */
	/* ---------------------------------------------------------------- */

	function initTabs() {
		$(document).on('click', '.quickshipd-tab-btn', function () {
			var tab = $(this).data('tab');
			$('.quickshipd-tab-btn').removeClass('is-active');
			$(this).addClass('is-active');
			$('.quickshipd-tab-pane').removeClass('is-active');
			$('#quickshipd-tab-' + tab).addClass('is-active');
		});
	}

	/* ---------------------------------------------------------------- */
	/* AJAX save                                                         */
	/* ---------------------------------------------------------------- */

	var SPINNER = '<span class="qs-btn-spinner"></span>';

	function btnLoading( $btn, label ) {
		$btn.prop('disabled', true).data('label', $btn.html()).html( SPINNER + label );
	}

	function btnReset( $btn ) {
		$btn.prop('disabled', false).html( $btn.data('label') || $btn.html() );
	}

	function saveSettings() {
		var $btn    = $('#quickshipd-save-btn');
		var $status = $('#quickshipd-save-status');
		var tab     = $('.quickshipd-tab-pane.is-active').data('tab');

		btnLoading( $btn, ' Saving…' );
		$status.text('').removeClass('is-success is-error');

		var data = {
			action: 'quickshipd_save_settings',
			nonce:  cfg.saveNonce || '',
			tab:    tab
		};

		$('#quickshipd-tab-' + tab).find('input, select, textarea').each(function () {
			var name = $(this).attr('name');
			if ( ! name ) return;

			if ( $(this).is('[type=checkbox]') ) {
				data[name] = $(this).is(':checked') ? 'yes' : 'no';
			} else if ( $(this).is('[type=radio]') ) {
				if ( $(this).is(':checked') ) data[name] = $(this).val();
			} else {
				var cleanName = name.replace('[]', '');
				if ( name.indexOf('[]') !== -1 ) {
					if ( ! data[cleanName] ) data[cleanName] = [];
					data[cleanName].push( $(this).val() );
					delete data[name];
				} else {
					data[name] = $(this).val();
				}
			}
		});

		var postData = { action: data.action, nonce: data.nonce, tab: data.tab };
		$.each(data, function (k, v) {
			if ( k === 'action' || k === 'nonce' || k === 'tab' ) return;
			postData[k] = v;
		});

		$.post(cfg.ajaxUrl || ajaxurl, postData, function (response) {
			btnReset( $btn );
			if ( response && response.success ) {
				$status.text( cfg.savedText || 'Saved.' ).addClass('is-success');
				setTimeout(function () { $status.text('').removeClass('is-success'); }, 3000);
			} else {
				$status.text( cfg.errorText || 'Error.' ).addClass('is-error');
			}
		}).fail(function () {
			btnReset( $btn );
			$status.text( cfg.errorText || 'Error.' ).addClass('is-error');
		});
	}

	/* ---------------------------------------------------------------- */
	/* Restore defaults                                                  */
	/* ---------------------------------------------------------------- */

	function restoreDefaults() {
		if ( ! window.confirm( cfg.confirmText || 'Reset all settings to defaults?' ) ) return;

		var $btn    = $('#quickshipd-restore-btn');
		var $status = $('#quickshipd-save-status');

		btnLoading( $btn, ' Restoring…' );
		$status.text('').removeClass('is-success is-error');

		$.post(cfg.ajaxUrl || ajaxurl, {
			action: 'quickshipd_restore_defaults',
			nonce:  cfg.restoreNonce || ''
		}, function (response) {
			btnReset( $btn );
			if ( response && response.success ) {
				$status.text( cfg.restoredText || 'Defaults restored.' ).addClass('is-success');
				setTimeout(function () { window.location.reload(); }, 800);
			} else {
				$status.text( cfg.errorText || 'Error.' ).addClass('is-error');
			}
		}).fail(function () {
			btnReset( $btn );
			$status.text( cfg.errorText || 'Error.' ).addClass('is-error');
		});
	}

	/* ---------------------------------------------------------------- */
	/* Pure-JS live preview — no AJAX                                   */
	/* ---------------------------------------------------------------- */

	var QS_ICONS = {
		truck: '<svg class="quickshipd-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M1 3h15v13H1V3z" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M16 8h4l3 4v5h-7V8z" stroke="currentColor" stroke-width="1.5" fill="none"/><circle cx="5.5" cy="18.5" r="2" stroke="currentColor" stroke-width="1.5" fill="none"/><circle cx="18.5" cy="18.5" r="2" stroke="currentColor" stroke-width="1.5" fill="none"/></svg>',
		box:   '<svg class="quickshipd-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" stroke="currentColor" stroke-width="1.5" fill="none"/><polyline points="3.27 6.96 12 12.01 20.73 6.96" stroke="currentColor" stroke-width="1.5" fill="none"/><line x1="12" y1="22.08" x2="12" y2="12" stroke="currentColor" stroke-width="1.5"/></svg>',
		none:  ''
	};

	var QS_DAYS_S   = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
	var QS_DAYS_L   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
	var QS_MONTHS_S = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
	var QS_MONTHS_L = ['January','February','March','April','May','June','July','August','September','October','November','December'];

	function qsDateFmt( date, fmt ) {
		var d   = date.getUTCDate();
		var mo  = date.getUTCMonth();
		var y   = date.getUTCFullYear();
		var dow = date.getUTCDay();
		return fmt.replace( /[A-Za-z]/g, function ( c ) {
			switch ( c ) {
				case 'D': return QS_DAYS_S[ dow ];
				case 'l': return QS_DAYS_L[ dow ];
				case 'd': return ( '0' + d ).slice( -2 );
				case 'j': return d;
				case 'M': return QS_MONTHS_S[ mo ];
				case 'F': return QS_MONTHS_L[ mo ];
				case 'm': return ( '0' + ( mo + 1 ) ).slice( -2 );
				case 'n': return mo + 1;
				case 'Y': return y;
				case 'y': return String( y ).slice( -2 );
				default:  return c;
			}
		} );
	}

	function qsAddBizDays( startMs, days, excDows ) {
		var date  = new Date( startMs );
		var added = 0;
		var max   = days + 365;
		while ( added < days && max-- > 0 ) {
			date.setUTCDate( date.getUTCDate() + 1 );
			if ( excDows.indexOf( date.getUTCDay() ) === -1 ) added++;
		}
		if ( days === 0 ) {
			var safety = 365;
			while ( excDows.indexOf( date.getUTCDay() ) !== -1 && safety-- > 0 ) {
				date.setUTCDate( date.getUTCDate() + 1 );
			}
		}
		return date;
	}

	function qsCountdownFmt( secs ) {
		var h = Math.floor( secs / 3600 );
		var m = Math.floor( ( secs % 3600 ) / 60 );
		return h > 0 ? ( h + 'h ' + m + 'm' ) : ( m + 'm' );
	}

	function field( selector, fallback ) {
		var v = $( selector ).val();
		return ( v !== undefined && v !== '' ) ? v : fallback;
	}

	function checkbox( selector ) {
		return $( selector ).is( ':checked' );
	}

	function buildPreviewHtml() {
		// ---- delivery settings ----
		var minDays    = parseInt( field( 'input[name="quickshipd_min_days"]',    '0' ), 10 );
		var maxDays    = parseInt( field( 'input[name="quickshipd_max_days"]',    '0' ), 10 );
		var cutoffH    = parseInt( field( 'select[name="quickshipd_cutoff_hour"]','0' ), 10 );
		var cutoffM    = parseInt( field( 'select[name="quickshipd_cutoff_min"]', '0' ), 10 );
		var excWeekend = checkbox( 'input[name="quickshipd_exclude_weekends"]' );

		// ---- style settings ----
		var textSingle      = field( 'input[name="quickshipd_text_single"]',    'Get it by {date}' );
		var textRange       = field( 'input[name="quickshipd_text_range"]',     'Get it {start} \u2013 {end}' );
		var textCountdown   = field( 'input[name="quickshipd_text_countdown"]', 'Order within {countdown} to get it by {date}' );
		var dateFmt         = field( 'select[name="quickshipd_date_format"]',   'D, M j' );
		var icon            = field( 'select[name="quickshipd_icon"]',          'truck' );
		var primaryColor    = field( 'input[name="quickshipd_text_color"]',     '#16a34a' );
		var secondaryColor  = field( 'input[name="quickshipd_secondary_color"]','#6b7280' );
		var bgColor         = field( 'input[name="quickshipd_bg_color"]',       '#f0fdf4' );
		var borderRadius    = parseInt( field( 'input[name="quickshipd_border_radius"]', '8' ), 10 );
		var padding         = parseInt( field( 'input[name="quickshipd_padding"]', '10' ), 10 );
		var showCd          = checkbox( 'input[name="quickshipd_show_countdown"]' );

		// ---- current site time (adjusted UTC = UTC + siteUtcOffset) ----
		var nowMs      = ( cfg.nowTimestamp || Math.floor( Date.now() / 1000 ) ) * 1000;
		var offsetMs   = ( cfg.siteUtcOffset || 0 ) * 1000;
		var siteNow    = new Date( nowMs + offsetMs );   // use getUTC* for site-local values
		var todayStart = new Date( siteNow );
		todayStart.setUTCHours( 0, 0, 0, 0 );

		var pastCutoff = siteNow.getUTCHours() > cutoffH ||
			( siteNow.getUTCHours() === cutoffH && siteNow.getUTCMinutes() >= cutoffM );

		var startMs = todayStart.getTime() + ( pastCutoff ? 86400000 : 0 );

		var countdownSecs = 0;
		if ( ! pastCutoff ) {
			var cutoffMs = todayStart.getTime() + cutoffH * 3600000 + cutoffM * 60000;
			countdownSecs = Math.max( 0, Math.floor( ( cutoffMs - siteNow.getTime() ) / 1000 ) );
		}
		// Preview: always demo the countdown when enabled, even if past cutoff.
		if ( showCd && countdownSecs === 0 ) {
			countdownSecs = 9000; // 2h 30m demo
		}

		var excDows  = excWeekend ? [ 0, 6 ] : [];
		var minDate  = qsAddBizDays( startMs, minDays, excDows );
		var maxDate  = qsAddBizDays( startMs, maxDays, excDows );
		var isRange  = minDate.getTime() !== maxDate.getTime();
		var minFmt   = qsDateFmt( minDate, dateFmt );
		var maxFmt   = qsDateFmt( maxDate, dateFmt );

		var dateLabel = isRange
			? textRange.replace( '{start}', minFmt ).replace( '{end}', maxFmt )
			: textSingle.replace( '{date}', maxFmt );

		var containerStyle = '';
		if ( bgColor ) {
			containerStyle = 'background-color:' + bgColor + ';padding:' + padding + 'px ' + ( padding + 4 ) + 'px;border-radius:' + borderRadius + 'px';
		}

		var iconSvg = QS_ICONS[ icon ] !== undefined ? QS_ICONS[ icon ] : QS_ICONS.truck;

		var html  = '<div class="quickshipd-delivery quickshipd-context-product"' + ( containerStyle ? ' style="' + containerStyle + '"' : '' ) + '>';
		html     += '<div class="quickshipd-estimate" style="color:' + primaryColor + '">' + iconSvg;
		html     += '<span class="quickshipd-date-text">' + dateLabel + '</span>';
		html     += '</div>';

		if ( showCd && countdownSecs > 0 ) {
			var cdFmt  = qsCountdownFmt( countdownSecs );
			var cdText = textCountdown
				.replace( '{countdown}', '<strong style="color:' + primaryColor + '">' + cdFmt + '</strong>' )
				.replace( '{date}', maxFmt );
			html += '<div class="quickshipd-countdown" style="color:' + secondaryColor + '" data-seconds="' + countdownSecs + '">' + cdText + '</div>';
		}

		html += '</div>';
		return html;
	}

	var previewTimer = null;

	function schedulePreview() {
		clearTimeout( previewTimer );
		previewTimer = setTimeout( refreshPreview, 150 );
	}

	function refreshPreview() {
		var $stage = $( '#quickshipd-live-preview .quickshipd-preview-stage' );
		if ( ! $stage.length ) return;
		$stage.html( buildPreviewHtml() );
	}

	/* ---------------------------------------------------------------- */
	/* Boot                                                              */
	/* ---------------------------------------------------------------- */

	$(function () {
		initColorPickers();
		initTabs();

		$('#quickshipd-save-btn').on('click', saveSettings);
		$('#quickshipd-restore-btn').on('click', restoreDefaults);

		$(document).on(
			'change input',
			'.quickshipd-tab-pane input, .quickshipd-tab-pane select, .quickshipd-tab-pane textarea',
			schedulePreview
		);

		refreshPreview();
	});

}(jQuery));
