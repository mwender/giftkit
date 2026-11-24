/**
 * Puts the WordPress.org banner at the top of README.md, which
 * wp-readme-to-md regenerates from readme.txt without it.
 */
const fs = require( 'fs' );
const path = require( 'path' );

const file = path.resolve( __dirname, '..', 'README.md' );
const banner = '![GiftKit for Gravity Forms](.wordpress-org/banner-1544x500.png)\n\n';
const readme = fs.readFileSync( file, 'utf8' );

if ( ! readme.startsWith( banner ) ) {
	fs.writeFileSync( file, banner + readme );
}
