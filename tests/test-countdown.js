// Frontend countdown + variation AJAX checks. No framework, no DOM library.
// Run from the plugin root:
//   node tests/test-countdown.js                     (source)
//   node tests/test-countdown.js assets/js/frontend.min.js
const fs = require( 'fs' ), assert = require( 'assert' );

const file = process.argv[2] || 'assets/js/frontend.js';
const code = fs.readFileSync( file, 'utf8' );

function harness( options ) {
	const opts = options || {};
	const state = { intervals: 0, fetches: [], handlers: {} };

	const label = { textContent: '' };
	const countdownEl = {
		style: {}, attrs: { 'data-seconds': '120', 'data-show-seconds': '1' },
		getAttribute( k ) { return this.attrs[k]; },
		setAttribute( k, v ) { this.attrs[k] = String( v ); },
		querySelector() { return label; },
	};
	const variableEl = {
		style: {}, innerHTML: '',
		getAttribute( k ) { return 'data-ajax' === k ? 'http://x/admin-ajax.php' : 'nonce'; },
	};
	// A simple product renders its countdown server side, with no variation form.
	const simple = !! opts.simpleProduct;
	const hasCountdown = () => simple || !! variableEl.innerHTML;

	const sandbox = {
		setInterval: ( fn ) => { state.tick = fn; return ++state.intervals; },
		setTimeout: ( fn, ms ) => global.setTimeout( fn, ms ),
		requestAnimationFrame: () => {},
		MutationObserver: class { observe() {} },
		fetch( url, init ) {
			const id = /variation_id=(\d+)/.exec( init.body )[1];
			state.fetches.push( id );
			if ( opts.failFirst && 1 === state.fetches.length ) return Promise.reject( new Error( 'network' ) );
			const delay = opts.delayFor ? opts.delayFor( id ) : 0;
			return new Promise( ( resolve ) => global.setTimeout( () => resolve( {
				json: () => Promise.resolve( {
					success: true,
					data: { html: '<div class="quickshipd-countdown" data-seconds="120">' + id + '</div>' },
				} ),
			} ), delay ) );
		},
		jQuery: () => ( { on( ev, fn ) { state.handlers[ev] = fn; return this; } } ),
		document: {
			readyState: 'complete',
			addEventListener() {},
			querySelector( sel ) {
				if ( sel.indexOf( 'quickshipd-countdown' ) > -1 ) return hasCountdown() ? countdownEl : null;
				if ( sel.indexOf( 'quickshipd-variable' ) > -1 ) return simple ? null : variableEl;
				if ( sel.indexOf( 'variations_form' ) > -1 ) return simple ? null : { querySelector: () => null };
				return null;
			},
			querySelectorAll( sel ) {
				return sel.indexOf( 'quickshipd-countdown' ) > -1 && hasCountdown() ? [ countdownEl ] : [];
			},
			getElementById() { return null; },
			body: {},
		},
	};
	sandbox.window = sandbox;

	const keys = Object.keys( sandbox );
	new Function( ...keys, code )( ...keys.map( ( k ) => sandbox[k] ) );

	state.select = ( id ) => state.handlers.found_variation( null, { variation_id: id } );
	state.reset = () => state.handlers.reset_data();
	state.html = () => variableEl.innerHTML;
	state.seconds = () => parseInt( countdownEl.attrs['data-seconds'], 10 );
	state.label = () => label.textContent;
	state.hidden = () => 'none' === variableEl.style.display;
	return state;
}

const settle = ( ms ) => new Promise( ( r ) => setTimeout( r, ms || 10 ) );

( async () => {
	// One countdown interval no matter how many variations are selected.
	const a = harness();
	assert.strictEqual( a.intervals, 0, 'variable product starts with no countdown' );
	for ( const id of [ 101, 102, 103 ] ) { a.select( id ); await settle(); }
	assert.strictEqual( a.intervals, 1, 'expected 1 interval after 3 selections, got ' + a.intervals );

	// Re-selecting the same variation does not re-request it.
	const b = harness();
	b.select( 201 ); await settle();
	b.select( 201 ); await settle();
	assert.deepStrictEqual( b.fetches, [ '201' ], 'duplicate selection refetched: ' + b.fetches );

	// A slow first response must not overwrite a newer selection.
	const c = harness( { delayFor: ( id ) => ( '301' === id ? 60 : 5 ) } );
	c.select( 301 );
	c.select( 302 );
	await settle( 120 );
	assert.ok( c.html().indexOf( '302' ) > -1, 'stale response won, showing: ' + c.html() );

	// --- Regression cover for behaviour that already worked -----------------

	// Simple product: countdown starts at load and actually counts down by one.
	const d = harness( { simpleProduct: true } );
	assert.strictEqual( d.intervals, 1, 'simple product should start one interval' );
	d.tick();
	assert.strictEqual( d.seconds(), 119, 'one tick should drop one second, got ' + d.seconds() );
	d.tick();
	assert.strictEqual( d.seconds(), 118, 'second tick should drop one more, got ' + d.seconds() );
	assert.strictEqual( d.label(), '1m 58s', 'label should render, got ' + d.label() );

	// Clearing the selection hides the estimate, and re-picking it loads again.
	const e = harness();
	e.select( 401 ); await settle();
	e.reset(); await settle();
	assert.ok( e.hidden(), 'reset_data should hide the estimate' );
	e.select( 401 ); await settle();
	assert.deepStrictEqual( e.fetches, [ '401', '401' ], 're-picking after reset must refetch: ' + e.fetches );
	assert.ok( ! e.hidden(), 'estimate should be visible again' );

	// Switching away and back refetches each time.
	const f = harness();
	for ( const id of [ 501, 502, 501 ] ) { f.select( id ); await settle(); }
	assert.deepStrictEqual( f.fetches, [ '501', '502', '501' ], 'A/B/A should refetch: ' + f.fetches );
	assert.ok( f.html().indexOf( '501' ) > -1, 'last selection should win, showing: ' + f.html() );

	// A response landing after the shopper cleared the selection stays hidden.
	const g = harness( { delayFor: () => 40 } );
	g.select( 601 );
	g.reset();
	await settle( 100 );
	assert.ok( g.hidden(), 'late response must not un-hide a cleared selection' );

	// A failed request can be retried by picking the same variation again.
	const h = harness( { failFirst: true } );
	h.select( 701 ); await settle();
	assert.ok( h.hidden(), 'failed request should hide the estimate' );
	h.select( 701 ); await settle();
	assert.deepStrictEqual( h.fetches, [ '701', '701' ], 'retry after failure must refetch: ' + h.fetches );
	assert.ok( ! h.hidden(), 'retry should show the estimate' );

	console.log( 'OK', file );
} )();
