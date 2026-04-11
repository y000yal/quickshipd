/**
 * Build a WordPress.org–style installable zip:
 * quickship-delivery-date.zip → contains folder quickship-delivery-date/
 *
 * Run from this directory: pnpm install && pnpm release (regenerates .pot + zip)
 * Output: release/quickship-delivery-date.zip
 */

const path = require( 'path' );
const { src, dest } = require( 'gulp' );
const zip = require( 'gulp-zip' );

const PLUGIN_SLUG = path.basename( __dirname );
const PLUGINS_DIR = path.join( __dirname, '..' );
const RELEASE_DIR = path.join( __dirname, 'release' );

function dist() {
	return src(
		[
			'**/*',
			'!node_modules/**',
			'!release/**',
			'!tests/**',
			'!**/*.zip',
			'!package.json',
			'!package-lock.json',
			'!pnpm-lock.yaml',
			'!yarn.lock',
			'!gulpfile.js',
		],
		{
			cwd: __dirname,
			base: PLUGINS_DIR,
			dot: false,
		}
	)
		.pipe( zip( `${ PLUGIN_SLUG }.zip` ) )
		.pipe( dest( RELEASE_DIR ) );
}

exports.dist = dist;
exports.default = dist;
