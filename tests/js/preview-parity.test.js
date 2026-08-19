/**
 * The admin live preview reimplements the delivery date maths in JavaScript.
 * This runs the real code out of assets/js/admin.js against the same cases the
 * PHP suite covers, so the preview and the storefront cannot drift apart.
 *
 * Plain node, no framework: node tests/js/preview-parity.test.js
 */
const fs = require( 'fs' );
const path = require( 'path' );

const SRC = fs
	.readFileSync( path.join( __dirname, '..', '..', 'assets', 'js', 'admin.js' ), 'utf8' )
	.replace( /\r\n/g, '\n' );

function grabFn( name ) {
	const start = SRC.indexOf( '\tfunction ' + name + '(' );
	const end = SRC.indexOf( '\n\t}\n', start );
	if ( start === -1 || end === -1 ) {
		throw new Error( 'could not extract function: ' + name );
	}
	return SRC.slice( start, end + 3 );
}

// The start-date block, lifted verbatim out of buildPreviewHtml().
const BLOCK_START = SRC.indexOf( '\t\tvar excDows     = getExcludedDows();' );
const BLOCK_END = SRC.indexOf( '\t\tvar isRange  =' );
if ( BLOCK_START === -1 || BLOCK_END === -1 ) {
	throw new Error( 'could not locate the preview date block in admin.js' );
}
const BLOCK = SRC.slice( BLOCK_START, BLOCK_END );

const HELPERS = [ 'qsPad2', 'qsYmd', 'qsIsExcludedDay', 'qsAddBizDays' ]
	.map( grabFn )
	.join( '\n' );

const preview = new Function(
	'excDowsIn',
	'holidayKeysIn',
	'nowIso',
	'minDays',
	'maxDays',
	'cutoffH',
	'cutoffM',
	'showCd',
	HELPERS +
		`
	function getExcludedDows() { return excDowsIn; }
	function getHolidayEntries() { return []; }
	function qsExpandHolidays() { return holidayKeysIn; }

	var siteNow    = new Date( nowIso );
	var todayStart = new Date( siteNow );
	todayStart.setUTCHours( 0, 0, 0, 0 );

	var pastCutoff = siteNow.getUTCHours() > cutoffH ||
		( siteNow.getUTCHours() === cutoffH && siteNow.getUTCMinutes() >= cutoffM );

` +
		BLOCK +
		`
	return {
		min: minDate.toISOString().slice( 0, 10 ),
		max: maxDate.toISOString().slice( 0, 10 ),
		countdown: countdownSecs
	};`
);

let failures = 0;

function check( label, expected, actual ) {
	const ok = expected === actual;
	if ( ! ok ) {
		failures++;
	}
	console.log(
		`${ ok ? 'ok  ' : 'FAIL' } ${ label.padEnd( 52 ) } expected ${ String( expected ).padEnd( 12 ) } got ${ actual }`
	);
}

const WEEKENDS_OFF = [ 0, 6 ];
const run = ( min, max, excluded, holidays, when, showCd = false ) =>
	preview( excluded, holidays, when, min, max, 14, 0, showCd );

// Counting starts on the dispatch day, never on an excluded one.
let r = run( 1, 2, WEEKENDS_OFF, [], '2024-01-06T09:00:00Z' ); // Saturday.
check( 'saturday order min', '2024-01-09', r.min );
check( 'saturday order max', '2024-01-10', r.max );
check( 'saturday order has no countdown', 0, r.countdown );

check( 'sunday order min', '2024-01-09', run( 1, 2, WEEKENDS_OFF, [], '2024-01-07T09:00:00Z' ).min );

r = run( 1, 2, WEEKENDS_OFF, [], '2024-01-05T09:00:00Z' ); // Friday, before cutoff.
check( 'friday before cutoff min', '2024-01-08', r.min );
check( 'friday before cutoff max', '2024-01-09', r.max );
check( 'friday before cutoff countdown', 5 * 3600, r.countdown );

r = run( 1, 2, WEEKENDS_OFF, [], '2024-01-05T16:00:00Z' ); // Friday, after cutoff.
check( 'friday after cutoff min', '2024-01-09', r.min );
check( 'friday after cutoff countdown', 0, r.countdown );

r = run( 3, 5, WEEKENDS_OFF, [], '2024-01-08T09:00:00Z' ); // Monday.
check( 'monday 3-5 min', '2024-01-11', r.min );
check( 'monday 3-5 max', '2024-01-15', r.max );
check( 'monday countdown', 5 * 3600, r.countdown );

// Same-day delivery.
check( 'saturday same-day rolls to monday', '2024-01-08', run( 0, 0, WEEKENDS_OFF, [], '2024-01-06T09:00:00Z' ).max );
check( 'weekday same-day is today', '2024-01-08', run( 0, 0, WEEKENDS_OFF, [], '2024-01-08T09:00:00Z' ).max );

// Holidays behave like non-dispatch days.
r = run( 1, 1, [], [ '2024-01-08' ], '2024-01-08T09:00:00Z' );
check( 'holiday on order date min', '2024-01-10', r.min );
check( 'holiday on order date countdown', 0, r.countdown );

// The preview must not invent a countdown the storefront never renders.
check( 'no countdown past cutoff', 0, run( 1, 2, WEEKENDS_OFF, [], '2024-01-08T16:00:00Z', true ).countdown );
check( 'no countdown on a non-dispatch day', 0, run( 1, 2, WEEKENDS_OFF, [], '2024-01-06T16:00:00Z', true ).countdown );
check( 'countdown before cutoff', 5 * 3600, run( 1, 2, WEEKENDS_OFF, [], '2024-01-08T09:00:00Z', true ).countdown );

console.log( failures ? `\n${ failures } failed` : '\nall passed' );
process.exit( failures ? 1 : 0 );
