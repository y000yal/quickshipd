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

	const countdownEl = {
		style: {}, attrs: { 'data-seconds': '120' },
		getAttribute( k ) { return this.attrs[k]; },
		setAttribute( k, v ) { this.attrs[k] = String( v ); },
		querySelector() { return { textContent: '' }; },
	};
	const variableEl = {
		style: {}, innerHTML: '',
		getAttribute( k ) { return 'data-ajax' === k ? 'http://x/admin-ajax.php' : 'nonce'; },
	};

	const sandbox = {
		setInterval: () => ++state.intervals,
		setTimeout: ( fn, ms ) => global.setTimeout( fn, ms ),
		requestAnimationFrame: () => {},
		MutationObserver: class { observe() {} },
		fetch( url, init ) {
			const id = /variation_id=(\d+)/.exec( init.body )[1];
			state.fetches.push( id );
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
				if ( sel.indexOf( 'quickshipd-countdown' ) > -1 ) return variableEl.innerHTML ? countdownEl : null;
				if ( sel.indexOf( 'quickshipd-variable' ) > -1 ) return variableEl;
				if ( sel.indexOf( 'variations_form' ) > -1 ) return { querySelector: () => null };
				return null;
			},
			querySelectorAll( sel ) {
				return sel.indexOf( 'quickshipd-countdown' ) > -1 && variableEl.innerHTML ? [ countdownEl ] : [];
			},
			getElementById() { return null; },
			body: {},
		},
	};
	sandbox.window = sandbox;

	const keys = Object.keys( sandbox );
	new Function( ...keys, code )( ...keys.map( ( k ) => sandbox[k] ) );

	state.select = ( id ) => state.handlers.found_variation( null, { variation_id: id } );
	state.html = () => variableEl.innerHTML;
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

	console.log( 'OK', file );
} )();
