/**
 * Build a WordPress.org–style installable zip:
 * quickshipd.zip → contains folder quickshipd/
 *
 * Tasks:
 *   build   — minify JS + CSS into assets/js/*.min.js and assets/css/*.min.css
 *   dist    — zip everything (run `build` first)
 *   release — makepot + build + dist  (via package.json script)
 *
 * Run: pnpm release
 * Output: release/quickshipd.zip
 */

const path      = require( 'path' );
const { src, dest, series } = require( 'gulp' );
const zip       = require( 'gulp-zip' );
const terser    = require( 'gulp-terser' );
const cleanCSS  = require( 'gulp-clean-css' );
const rename    = require( 'gulp-rename' );

const PLUGIN_SLUG = path.basename( __dirname );
const PLUGINS_DIR = path.join( __dirname, '..' );
const RELEASE_DIR = path.join( __dirname, 'release' );

// ── Build: write *.min.js / *.min.css into assets/ ───────────────────────────

function buildJS() {
	return src( 'assets/js/*.js', { cwd: __dirname, ignore: 'assets/js/*.min.js' } )
		.pipe( terser( { compress: true, mangle: true } ) )
		.pipe( rename( { suffix: '.min' } ) )
		.pipe( dest( 'assets/js', { cwd: __dirname } ) );
}

function buildCSS() {
	return src( 'assets/css/*.css', { cwd: __dirname, ignore: 'assets/css/*.min.css' } )
		.pipe( cleanCSS( { level: 2 } ) )
		.pipe( rename( { suffix: '.min' } ) )
		.pipe( dest( 'assets/css', { cwd: __dirname } ) );
}

const build = series( buildJS, buildCSS );

// ── Dist: zip everything (minified files already in assets/) ─────────────────

const EXCLUDE = [
	'!node_modules/**',
	'!release/**',
	'!tests/**',
	'!**/*.zip',
	'!package.json',
	'!package-lock.json',
	'!pnpm-lock.yaml',
	'!yarn.lock',
	'!gulpfile.js',
];

function dist() {
	return src( [ '**/*', ...EXCLUDE ], { cwd: __dirname, base: PLUGINS_DIR, dot: false } )
		.pipe( zip( `${ PLUGIN_SLUG }.zip` ) )
		.pipe( dest( RELEASE_DIR ) );
}

exports.buildJS  = buildJS;
exports.buildCSS = buildCSS;
exports.build    = build;
exports.dist     = dist;
exports.release  = series( build, dist );
exports.default  = series( build, dist );
