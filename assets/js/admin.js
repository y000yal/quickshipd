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
			// Help is documentation, not settings: nothing to save or preview.
			$('.quickshipd-save-bar, .quickshipd-layout-right').toggle( tab !== 'help' );
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
				if ( name.indexOf('[]') !== -1 ) {
					// Multi-value checkbox group (e.g. non-dispatch days): collect checked values.
					var cbName = name.replace('[]', '');
					if ( ! data[cbName] ) data[cbName] = [];
					if ( $(this).is(':checked') ) data[cbName].push( $(this).val() );
					delete data[name];
				} else {
					data[name] = $(this).is(':checked') ? 'yes' : 'no';
				}
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

	function qsPad2( n ) {
		return ( '0' + n ).slice( -2 );
	}

	function qsYmd( date ) {
		return date.getUTCFullYear() + '-' + qsPad2( date.getUTCMonth() + 1 ) + '-' + qsPad2( date.getUTCDate() );
	}

	function qsExpandHolidays( entries ) {
		var keys = [];
		if ( ! Array.isArray( entries ) ) return keys;

		entries.forEach( function ( entry ) {
			if ( ! entry || ! entry.start ) return;
			var type      = entry.type === 'range' ? 'range' : 'single';
			var start     = String( entry.start );
			var end       = String( entry.end || '' );
			var recurring = !! entry.recurring;

			if ( ! /^\d{4}-\d{2}-\d{2}$/.test( start ) ) return;

			if ( type === 'single' ) {
				keys.push( recurring ? ( 'XXXX-' + start.slice( 5 ) ) : start );
				return;
			}

			if ( ! /^\d{4}-\d{2}-\d{2}$/.test( end ) || end < start ) return;
			if ( start.slice( 0, 4 ) !== end.slice( 0, 4 ) ) return;

			var parts = start.split( '-' ).map( Number );
			var cursor = new Date( Date.UTC( parts[0], parts[1] - 1, parts[2] ) );
			var endParts = end.split( '-' ).map( Number );
			var last = new Date( Date.UTC( endParts[0], endParts[1] - 1, endParts[2] ) );
			var safety = 366;

			while ( cursor.getTime() <= last.getTime() && safety-- > 0 ) {
				var ymd = qsYmd( cursor );
				keys.push( recurring ? ( 'XXXX-' + ymd.slice( 5 ) ) : ymd );
				cursor.setUTCDate( cursor.getUTCDate() + 1 );
			}
		} );

		return keys.filter( function ( k, i, arr ) { return arr.indexOf( k ) === i; } );
	}

	function qsIsExcludedDay( date, excDows, holidayKeys ) {
		if ( excDows.indexOf( date.getUTCDay() ) !== -1 ) return true;
		var ymd = qsYmd( date );
		return holidayKeys.indexOf( ymd ) !== -1 || holidayKeys.indexOf( 'XXXX-' + ymd.slice( 5 ) ) !== -1;
	}

	function qsAddBizDays( startMs, days, excDows, holidayKeys ) {
		holidayKeys = holidayKeys || [];
		var date  = new Date( startMs );
		var added = 0;
		var max   = days + 365;
		while ( added < days && max-- > 0 ) {
			date.setUTCDate( date.getUTCDate() + 1 );
			if ( ! qsIsExcludedDay( date, excDows, holidayKeys ) ) added++;
		}
		return date;
	}

	function qsCountdownFmt( secs, showSecs ) {
		var h  = Math.floor( secs / 3600 );
		var m  = Math.floor( ( secs % 3600 ) / 60 );
		var sc = secs % 60;
		if ( showSecs ) {
			if ( h > 0 ) return h + 'h ' + m + 'm ' + sc + 's';
			if ( m > 0 ) return m + 'm ' + sc + 's';
			return sc + 's';
		}
		if ( h > 0 ) return h + 'h ' + m + 'm';
		return ( m > 0 ? m : 1 ) + 'm';
	}

	function field( selector, fallback ) {
		var v = $( selector ).val();
		return ( v !== undefined && v !== '' ) ? v : fallback;
	}

	function checkbox( selector ) {
		return $( selector ).is( ':checked' );
	}

	function getExcludedDows() {
		var excDows = [];
		$( 'input[name="quickshipd_excluded_days[]"]:checked' ).each( function () {
			excDows.push( parseInt( $( this ).val(), 10 ) );
		} );
		return excDows;
	}

	function getHolidayEntries() {
		try {
			var raw = $( '#quickshipd_holidays' ).val() || '[]';
			var parsed = JSON.parse( raw );
			return Array.isArray( parsed ) ? parsed : [];
		} catch ( e ) {
			return [];
		}
	}

	function buildPreviewHtml() {
		// ---- delivery settings ----
		var minDays    = parseInt( field( 'input[name="quickshipd_min_days"]',    '0' ), 10 );
		var maxDays    = parseInt( field( 'input[name="quickshipd_max_days"]',    '0' ), 10 );
		var cutoffRaw  = field( 'input[name="quickshipd_cutoff_time"]', '14:00' ).split( ':' );
		var cutoffH    = parseInt( cutoffRaw[0] || '14', 10 );
		var cutoffM    = parseInt( cutoffRaw[1] || '0',  10 );

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
		var showCdSecs      = checkbox( 'input[name="quickshipd_show_countdown_seconds"]' );

		// ---- current site time (adjusted UTC = UTC + siteUtcOffset) ----
		var nowMs      = ( cfg.nowTimestamp || Math.floor( Date.now() / 1000 ) ) * 1000;
		var offsetMs   = ( cfg.siteUtcOffset || 0 ) * 1000;
		var siteNow    = new Date( nowMs + offsetMs );   // use getUTC* for site-local values
		var todayStart = new Date( siteNow );
		todayStart.setUTCHours( 0, 0, 0, 0 );

		var pastCutoff = siteNow.getUTCHours() > cutoffH ||
			( siteNow.getUTCHours() === cutoffH && siteNow.getUTCMinutes() >= cutoffM );

		var excDows     = getExcludedDows();
		var holidayKeys = qsExpandHolidays( getHolidayEntries() );

		// Same roll-forward as the PHP calculator: counting starts on the
		// dispatch day, never on an excluded day.
		var startDate   = new Date( todayStart.getTime() + ( pastCutoff ? 86400000 : 0 ) );
		var startSafety = 365;
		while ( qsIsExcludedDay( startDate, excDows, holidayKeys ) && startSafety-- > 0 ) {
			startDate.setUTCDate( startDate.getUTCDate() + 1 );
		}
		var startMs       = startDate.getTime();
		var dispatchToday = startMs === todayStart.getTime();

		// Mirrors the PHP calculator exactly: no countdown once the cutoff has
		// passed, and none at all on a day nothing is dispatched.
		var countdownSecs = 0;
		if ( ! pastCutoff && dispatchToday ) {
			var cutoffMs = todayStart.getTime() + cutoffH * 3600000 + cutoffM * 60000;
			countdownSecs = Math.max( 0, Math.floor( ( cutoffMs - siteNow.getTime() ) / 1000 ) );
		}

		var minDate     = qsAddBizDays( startMs, minDays, excDows, holidayKeys );
		var maxDate     = qsAddBizDays( startMs, maxDays, excDows, holidayKeys );
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
			var cdFmt  = qsCountdownFmt( countdownSecs, showCdSecs );
			var cdText = textCountdown
				.replace( '{countdown}', '<strong style="color:' + primaryColor + '">' + cdFmt + '</strong>' )
				.replace( '{date}', maxFmt );
			var cdSecsAttr = showCdSecs ? ' data-show-seconds="1"' : '';
			html += '<div class="quickshipd-countdown" style="color:' + secondaryColor + '" data-seconds="' + countdownSecs + '"' + cdSecsAttr + '>' + cdText + '</div>';
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
		clearInterval( previewTickTimer );
		var $stage = $( '#quickshipd-live-preview .quickshipd-preview-stage' );
		if ( ! $stage.length ) return;
		$stage.html( buildPreviewHtml() );
		startPreviewTick();
	}

	/* ---------------------------------------------------------------- */
	/* Holidays row builder                                              */
	/* ---------------------------------------------------------------- */

	function initHolidaysBuilder() {
		var $root = $( '#qs-holidays-builder' );
		if ( ! $root.length ) return;

		var i18n = cfg.i18n || {};
		var $type       = $( '#qs-holiday-type' );
		var $start      = $( '#qs-holiday-start' );
		var $end        = $( '#qs-holiday-end' );
		var $recurring  = $( '#qs-holiday-recurring' );
		var $list       = $( '#qs-holidays-list' );
		var $empty      = $( '#qs-holidays-empty' );
		var $error      = $( '#qs-holidays-error' );
		var $hidden     = $( '#quickshipd_holidays' );

		function showError( msg ) {
			if ( ! msg ) {
				$error.prop( 'hidden', true ).text( '' );
				return;
			}
			$error.prop( 'hidden', false ).text( msg );
		}

		function syncType() {
			var isRange = $type.val() === 'range';
			$end.prop( 'hidden', ! isRange );
			if ( ! isRange ) $end.val( '' );
		}

		function readEntries() {
			return getHolidayEntries();
		}

		function writeEntries( entries ) {
			$hidden.val( JSON.stringify( entries ) );
			renderList( entries );
			schedulePreview();
		}

		function formatLabel( entry ) {
			var parts = [];
			if ( entry.type === 'range' ) {
				parts.push( ( i18n.holidayRange || 'Range' ) + ': ' + entry.start + ' \u2013 ' + entry.end );
			} else {
				parts.push( ( i18n.holidaySingle || 'Single' ) + ': ' + entry.start );
			}
			if ( entry.recurring ) {
				parts.push( '(' + ( i18n.holidayRecurring || 'every year' ) + ')' );
			}
			return parts.join( ' ' );
		}

		function renderList( entries ) {
			$list.empty();
			if ( ! entries.length ) {
				$empty.show();
				return;
			}
			$empty.hide();
			entries.forEach( function ( entry, index ) {
				var $li = $( '<li class="qs-holidays-item"/>' );
				$li.append( $( '<span class="qs-holidays-item__label"/>' ).text( formatLabel( entry ) ) );
				$li.append(
					$( '<button type="button" class="button-link qs-holidays-item__remove"/>' )
						.text( i18n.holidayRemove || 'Remove' )
						.attr( 'data-index', index )
				);
				$list.append( $li );
			} );
		}

		$type.on( 'change', syncType );

		$( '#qs-holiday-add' ).on( 'click', function () {
			showError( '' );
			var type  = $type.val() === 'range' ? 'range' : 'single';
			var start = ( $start.val() || '' ).trim();
			var end   = ( $end.val() || '' ).trim();
			var recurring = $recurring.is( ':checked' );

			if ( ! start ) {
				showError( i18n.holidayNeedStart || 'Choose a start date.' );
				return;
			}
			if ( type === 'range' ) {
				if ( ! end ) {
					showError( i18n.holidayNeedEnd || 'Choose an end date for the range.' );
					return;
				}
				if ( end < start ) {
					showError( i18n.holidayEndBefore || 'End date must be on or after the start date.' );
					return;
				}
				if ( start.slice( 0, 4 ) !== end.slice( 0, 4 ) ) {
					showError( i18n.holidayCrossYear || 'Ranges must stay within the same calendar year.' );
					return;
				}
			}

			var entries = readEntries();
			entries.push( {
				type: type,
				start: start,
				end: type === 'range' ? end : '',
				recurring: recurring
			} );
			writeEntries( entries );

			$start.val( '' );
			$end.val( '' );
			$recurring.prop( 'checked', false );
		} );

		$list.on( 'click', '.qs-holidays-item__remove', function () {
			var index = parseInt( $( this ).attr( 'data-index' ), 10 );
			var entries = readEntries();
			if ( isNaN( index ) || index < 0 || index >= entries.length ) return;
			entries.splice( index, 1 );
			writeEntries( entries );
		} );

		syncType();
		renderList( readEntries() );
	}

	/* ---------------------------------------------------------------- */
	/* Sub-setting visibility                                           */
	/* ---------------------------------------------------------------- */

	function initSubSettings() {
		var $secsRow = $( 'input[name="quickshipd_show_countdown_seconds"]' ).closest( 'tr' );
		$secsRow.addClass( 'qs-sub-setting' );

		function syncSecsRow() {
			if ( $( 'input[name="quickshipd_show_countdown"]' ).is( ':checked' ) ) {
				$secsRow.show();
			} else {
				$secsRow.hide();
			}
		}

		syncSecsRow();
		$( 'input[name="quickshipd_show_countdown"]' ).on( 'change', syncSecsRow );
	}

	/* ---------------------------------------------------------------- */
	/* Help tab: copy a shortcode to the clipboard                      */
	/* ---------------------------------------------------------------- */

	function initHelpCopy() {
		$( document ).on( 'click', '.qs-copy', function () {
			var $btn = $( this );
			var text = String( $btn.data( 'copy' ) || '' );
			if ( ! text || ! navigator.clipboard ) return;

			navigator.clipboard.writeText( text ).then( function () {
				var i18n = cfg.i18n || {};
				if ( ! $btn.data( 'label' ) ) $btn.data( 'label', $btn.text() );
				$btn.addClass( 'is-copied' ).text( i18n.copied || 'Copied' );
				setTimeout( function () {
					$btn.removeClass( 'is-copied' ).text( $btn.data( 'label' ) );
				}, 1600 );
			} );
		} );
	}

	/* ---------------------------------------------------------------- */
	/* Live preview countdown tick                                      */
	/* ---------------------------------------------------------------- */

	var previewTickTimer = null;

	function startPreviewTick() {
		clearInterval( previewTickTimer );
		previewTickTimer = setInterval( function () {
			var $cd = $( '#quickshipd-live-preview .quickshipd-countdown[data-seconds]' );
			if ( ! $cd.length ) {
				clearInterval( previewTickTimer );
				return;
			}
			$cd.each( function () {
				var $el  = $( this );
				var secs = parseInt( $el.attr( 'data-seconds' ), 10 ) - 1;
				if ( secs < 0 ) {
					clearInterval( previewTickTimer );
					return false;
				}
				$el.attr( 'data-seconds', secs );
				var showSecs = $el.attr( 'data-show-seconds' ) === '1';
				$el.find( 'strong' ).text( qsCountdownFmt( secs, showSecs ) );
			} );
		}, 1000 );
	}

	/* ---------------------------------------------------------------- */
	/* Boot                                                              */
	/* ---------------------------------------------------------------- */

	$(function () {
		initColorPickers();
		initTabs();
		initSubSettings();
		initHolidaysBuilder();
		initHelpCopy();

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
