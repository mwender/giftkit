/**
 * Builds the GiftKit website into _site/ for GitHub Pages:
 *
 *   /                        site/index.html
 *   /docs/                   docs/README.md
 *   /docs/<name>/            docs/<name>.md
 *   /changelog/              the Changelog section of readme.txt
 *
 * The markdown in docs/ stays the only copy of the documentation; links
 * between .md files are rewritten to the site's pages. Every URL is
 * relative, so the site works at any address (a custom domain or
 * mwender.github.io/giftkit).
 *
 * Usage: npm run site
 */
const fs = require( 'fs' );
const path = require( 'path' );
const { marked } = require( 'marked' );

const root = path.resolve( __dirname, '..' );
const out = path.join( root, '_site' );
const layout = fs.readFileSync( path.join( root, 'site', 'layout.html' ), 'utf8' );
const repo = 'https://github.com/mwender/giftkit/blob/main/';

// The site's own address, for links that must be absolute (canonical,
// Open Graph). It comes from site/CNAME.
const cnameFile = path.join( root, 'site', 'CNAME' );
const siteUrl = fs.existsSync( cnameFile ) ? 'https://' + fs.readFileSync( cnameFile, 'utf8' ).trim() : 'https://mwender.github.io/giftkit';

const fill = ( template, values ) => template.replace( /{{(\w+)}}/g, ( match, key ) => ( key in values ? values[ key ] : match ) );

function write( file, html ) {
	const target = path.join( out, file );
	fs.mkdirSync( path.dirname( target ), { recursive: true } );
	fs.writeFileSync( target, html );
}

function copy( from, to ) {
	fs.mkdirSync( path.dirname( path.join( out, to ) ), { recursive: true } );
	fs.copyFileSync( path.join( root, from ), path.join( out, to ) );
}

function page( file, values ) {
	const depth = file.split( '/' ).length - 1;
	const rootPath = depth ? '../'.repeat( depth ) : './';
	const content = fill( values.content, { root: rootPath } );
	const url = siteUrl + '/' + file.replace( /index\.html$/, '' );
	write( file, fill( layout, {
		...values,
		content,
		root: rootPath,
		url,
		site_url: siteUrl,
		og_title: values.og_title || values.title,
		description: escape( values.description ),
		docs_current: values.docs_current || '',
		changelog_current: values.changelog_current || '',
		scripts: values.scripts || '',
	} ) );
}

const escape = ( text ) => text.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( /"/g, '&quot;' );

fs.rmSync( out, { recursive: true, force: true } );

// Assets.
for ( const name of fs.readdirSync( path.join( root, 'site', 'assets' ), { recursive: true } ) ) {
	const from = path.join( 'site', 'assets', name );
	if ( fs.statSync( path.join( root, from ) ).isFile() ) {
		copy( from, path.join( 'assets', name ) );
	}
}
// The plugin's page illustrations, in the site's coral instead of
// Gravity Forms' default blue.
for ( const step of [ 'step-1.svg', 'step-2.svg', 'step-3.svg' ] ) {
	const svg = fs.readFileSync( path.join( root, 'assets', 'images', step ), 'utf8' ).replace( /#204ce5/gi, '#d23a40' );
	write( path.join( 'assets', 'images', step ), svg );
}
copy( path.join( '.wordpress-org', 'icon.svg' ), path.join( 'assets', 'images', 'icon.svg' ) );

// Landing page.
page( 'index.html', {
	title: 'GiftKit: a donation form for Gravity Forms',
	og_title: 'GiftKit: Donation form? Already built.',
	description: 'GiftKit adds a complete donation form to Gravity Forms: one-time and monthly gifts, preset amounts, an optional processing fee and Stripe payments.',
	content: fs.readFileSync( path.join( root, 'site', 'index.html' ), 'utf8' ).replace( /^<!--.*?-->\n/, '' ),
	scripts: '<script src="./assets/demo.js" defer></script>',
} );

// Docs.
const docsDir = path.join( root, 'docs' );
// Sidebar order: the overview, then these in reading order, then any other
// page alphabetically.
const docsOrder = [ 'README.md', 'getting-started.md', 'donation-settings.md', 'stripe-customer-portal.md' ];
const rank = ( name ) => ( docsOrder.includes( name ) ? docsOrder.indexOf( name ) : docsOrder.length );
const docs = fs.readdirSync( docsDir ).filter( ( name ) => name.endsWith( '.md' ) ).sort( ( a, b ) => rank( a ) - rank( b ) || a.localeCompare( b ) );
const titleOf = ( markdown ) => ( markdown.match( /^#\s+(.+)$/m ) || [ '', 'GiftKit' ] )[ 1 ].trim();
const slugOf = ( name ) => ( name === 'README.md' ? '' : name.replace( /\.md$/, '' ) );

// A page's description: its first paragraph after the title, as plain text.
const descriptionOf = ( markdown ) => {
	const paragraph = markdown.replace( /^#.*$/m, '' ).trim().split( /\n\s*\n/ )[ 0 ] || '';
	const text = paragraph.replace( /\[([^\]]+)\]\([^)]*\)/g, '$1' ).replace( /[*_`]/g, '' ).replace( /\s+/g, ' ' ).trim();
	return text.length > 160 ? text.slice( 0, 157 ).replace( /\s+\S*$/, '' ) + '…' : text;
};

for ( const name of docs ) {
	const markdown = fs.readFileSync( path.join( docsDir, name ), 'utf8' );
	const slug = slugOf( name );
	const here = slug ? `docs/${ slug }/index.html` : 'docs/index.html';
	const toDocs = slug ? '../' : './';

	// Links between docs become site pages; links out of docs/ go to GitHub.
	const body = marked.parse( markdown )
		.replace( /href="(?!https?:|#|mailto:)([^"]+?)\.md(#[^"]*)?"/g, ( m, target, hash = '' ) => `href="${ toDocs }${ target === 'README' ? '' : target + '/' }${ hash }"` )
		.replace( /href="\.\.\/([^"]+)"/g, ( m, target ) => `href="${ repo }${ target }"` );

	const nav = docs.map( ( other ) => {
		const otherSlug = slugOf( other );
		const href = toDocs + ( otherSlug ? otherSlug + '/' : '' );
		const label = otherSlug ? titleOf( fs.readFileSync( path.join( docsDir, other ), 'utf8' ) ) : 'Overview';
		return `<li><a href="${ href }"${ other === name ? ' aria-current="page"' : '' }>${ escape( label ) }</a></li>`;
	} ).join( '\n\t\t\t\t' );

	page( here, {
		title: `${ titleOf( markdown ) } · GiftKit docs`,
		description: descriptionOf( markdown ),
		docs_current: ' aria-current="page"',
		content: `<div class="docs">
	<div class="wrap">
		<nav class="docs-nav" aria-label="Documentation">
			<h2>Documentation</h2>
			<ul>
				${ nav }
			</ul>
		</nav>
		<article class="article">
${ body }
		</article>
	</div>
</div>`,
	} );
}

// Changelog, from readme.txt, so there's one copy. Versions newer than the
// readme's Stable tag haven't been released yet, and are marked as such.
{
	const readme = fs.readFileSync( path.join( root, 'readme.txt' ), 'utf8' );
	const stable = ( readme.match( /^Stable tag:\s*(\S+)/m ) || [] )[ 1 ] || '0';
	const newer = ( a, b ) => {
		const pa = a.split( '.' ).map( Number );
		const pb = b.split( '.' ).map( Number );
		for ( let i = 0; i < Math.max( pa.length, pb.length ); i++ ) {
			if ( ( pa[ i ] || 0 ) !== ( pb[ i ] || 0 ) ) {
				return ( pa[ i ] || 0 ) > ( pb[ i ] || 0 );
			}
		}
		return false;
	};
	const section = ( readme.split( /^== Changelog ==\s*$/m )[ 1 ] || '' ).split( /^== /m )[ 0 ];
	const markdown = section
		.replace( /^= (.+?) =\s*$/gm, ( m, version ) => `## ${ version }${ newer( version, stable ) ? ' (upcoming)' : '' }` )
		.replace( /^\* (New|Improved|Fixed):/gm, '* **$1:**' );

	page( 'changelog/index.html', {
		title: 'Changelog · GiftKit',
		description: 'What has changed in each version of GiftKit for Gravity Forms.',
		changelog_current: ' aria-current="page"',
		content: `<div class="docs docs--single">
	<div class="wrap">
		<article class="article">
<h1>Changelog</h1>
<p>What's changed in each version of GiftKit. Versions marked upcoming are finished but not released yet.</p>
${ marked.parse( markdown ) }
		</article>
	</div>
</div>`,
	} );
}

// GitHub Pages: serve files as they are, and keep the custom domain if set.
write( '.nojekyll', '' );
if ( fs.existsSync( path.join( root, 'site', 'CNAME' ) ) ) {
	copy( path.join( 'site', 'CNAME' ), 'CNAME' );
}

console.log( 'Built the site into _site/' );
