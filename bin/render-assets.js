/**
 * Renders the WordPress.org assets in .wordpress-org/ from their sources:
 * the banners from bin/assets/banner.html, the PNG icons from icon.svg.
 * Also renders the website's social image, site/assets/images/og-image.png,
 * from bin/assets/og-image.html.
 *
 * Needs Playwright with Chromium: `npm install` in this repo, then
 * `npm run assets`. Set CHROMIUM_PATH to use an existing Chromium binary.
 */
const path = require( 'path' );
const { chromium } = require( 'playwright' );

const root = path.resolve( __dirname, '..' );
const out = ( file ) => path.join( root, '.wordpress-org', file );

( async () => {
	const browser = await chromium.launch( process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {} );

	// Banners: the same 772x250 layout at 1x and 2x.
	for ( const scale of [ 1, 2 ] ) {
		const page = await browser.newPage( { viewport: { width: 772, height: 250 }, deviceScaleFactor: scale } );
		await page.goto( 'file://' + path.join( __dirname, 'assets', 'banner.html' ) );
		await page.waitForLoadState( 'networkidle' );
		await page.screenshot( { path: out( scale === 1 ? 'banner-772x250.png' : 'banner-1544x500.png' ) } );
		await page.close();
	}

	// Social sharing image for the website: 1200x630.
	{
		const page = await browser.newPage( { viewport: { width: 1200, height: 630 } } );
		await page.goto( 'file://' + path.join( __dirname, 'assets', 'og-image.html' ) );
		await page.waitForLoadState( 'networkidle' );
		await page.screenshot( { path: path.join( root, 'site', 'assets', 'images', 'og-image.png' ) } );
		await page.close();
	}

	// Icons: icon.svg at 128 and 256.
	for ( const size of [ 128, 256 ] ) {
		const page = await browser.newPage( { viewport: { width: size, height: size } } );
		await page.goto( 'file://' + out( 'icon.svg' ) );
		await page.waitForLoadState( 'networkidle' );
		await page.screenshot( { path: out( `icon-${ size }x${ size }.png` ) } );
		await page.close();
	}

	await browser.close();
	console.log( 'Rendered banners and icons into .wordpress-org/' );
} )();
