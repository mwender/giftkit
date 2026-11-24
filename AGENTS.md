# AGENTS.md

Context for AI coding agents working on GiftKit. `CLAUDE.md` is a symlink to this file.

## What GiftKit is

**GiftKit for Gravity Forms** adds a ready-made, opinionated donation form to Gravity Forms: one-time and monthly gifts, preset amount tiles, an optional processing fee, and Stripe payments through the official Gravity Forms Stripe Add-On. The value is the setup and the donor experience. A site owner creates the form in one step and then only touches a short list of settings on the form's **Donation** settings tab; GiftKit manages the pages, sections, fields and Stripe feeds.

It's built by Michael Wender (Wenmark Digital Solutions). The repo is public at `mwender/giftkit`, and the plan is to publish it on WordPress.org, as `localdev-switcher` is.

## Rules that shape every change

- **Generic, for any site or brand.** Nothing about a particular client belongs in the code: no names, copy, colours, currencies or URLs. Anything brand-specific is a setting with a neutral default. The first site to use it is The Maker City (`~/webdev/bedrock/themakercity.org`), but that site is a consumer, not the spec.
- **Every prefix comes from the name "GiftKit".** PHP namespace `GiftKit\`, global functions and constants `giftkit_` / `GIFTKIT_`, hooks, options and field types `giftkit_`, CSS classes `giftkit-`, script handles `giftkit`, text domain `giftkit`. Never `tmc_` or any other site's prefix.
- **WordPress.org-ready from the start.** GPL2+, every user-facing string translatable with the `giftkit` text domain, input sanitized and output escaped, nothing loaded from external servers, and `readme.txt` kept current. Files that shouldn't ship go in `.distignore`.
- **Build on Gravity Forms rather than around it.** Use the add-on framework (`GFAddOn`), native fields, GF's pricing and the Stripe add-on's feeds. Avoid relying on GF internals that aren't public APIs; if something can only be done that way, ask Michael first. (The Form Templates screen was ruled out for that reason.)

## How it works

- `giftkit.php` registers the add-on on `gform_loaded`.
- `includes/class-form-builder.php` defines the form and keeps it in step with its settings. Each field GiftKit manages carries a `giftkitRole` property (`frequency`, `presets`, `amount`, `fee`, `total` and so on). Settings, feeds and front-end code find fields **by role**, never by field ID or CSS class, so the form survives someone reordering or adding fields.
- Settings live in the form object under `$form['giftkit']` (the add-on framework's per-form settings), along with the two Stripe feed IDs.
- Pricing is native GF: two calculation products (the gift, and the fee shown only when the donor opts in) read the amount field, and the Stripe feeds charge the Form Total. One feed handles one-time gifts and the other monthly subscriptions, chosen by the Gift Type field's value (`one-time` / `monthly`), so its labels can be reworded freely.

## The development site

This checkout lives in a dedicated plain-WordPress site, deliberately not Bedrock, because that's what WordPress.org users run:

- Site root: `~/webdev/wordpress-plugins/giftkit`, served by Valet as `https://giftkit.test` (PHP 8.4, `valet isolate`), database `wp_giftkit` under the Mini's `macmini` MariaDB login. Mail is caught by Mailpit.
- `wp-content/plugins/localdev-giftkit` is this working checkout. `wp-content/plugins/giftkit` is the copy installed from GitHub. **LocalDev Switcher** toggles which one is active (on the Plugins screen), so a release can be checked against the working copy on the same site. WP-CLI works from inside this folder.
- Gravity Forms and the Gravity Forms Stripe Add-On were copied from the Maker City site's Composer install; they're commercial plugins and aren't in this repo.
- Stripe runs in test mode, connected under Forms → Settings → Stripe. The site has no public URL, so Stripe can't deliver webhooks to it. One-time test payments work without them; for subscription events, forward webhooks with the Stripe CLI (`stripe listen --forward-to https://giftkit.test/?callback=gravityformsstripe`).

Sibling plugins may join it later under `~/webdev/wordpress-plugins/` (e.g. `localdev-switcher`).

## Packaging and releases

GiftKit follows the same setup as `mwender/localdev-switcher`, which is already on WordPress.org.

- **`readme.txt` is the source** for the WordPress.org listing. `README.md` is generated from it with `npm run readme`, which also puts the banner back at the top (`bin/readme-banner.js`). Edit `readme.txt`, never `README.md`, and regenerate before committing.
- **`.wordpress-org/`** holds the listing's assets: `banner-772x250.png` and `banner-1544x500.png`, `icon.svg` plus `icon-128x128.png` and `icon-256x256.png`, and `screenshot-N.png` matching the numbered captions under `== Screenshots ==` in `readme.txt`. The banner's source is `bin/assets/banner.html`; `npm run assets` re-renders the banners and PNG icons with Playwright (set `CHROMIUM_PATH` to use the Mini's cached Chromium).
- **`.distignore`** decides what ships. `npm run zip` (`bin/build-zip.sh`) builds `dist/giftkit.zip` the same way the deploy action does, with rsync and `.distignore`. Check a release by running WordPress.org's Plugin Check on that build, in a folder named `giftkit` (in this checkout's folder it reports a false text-domain mismatch, because it expects the folder name).
- **Deploying:** publishing a GitHub release runs `.github/workflows/deploy.yml` (10up's deploy action), which commits the release to WordPress.org SVN and attaches the zip to the release. `update-assets.yml`, run by hand, pushes only `readme.txt` and `.wordpress-org/`, which is how a "Tested up to" bump reaches WordPress.org without a release. Both need the repository secrets `SVN_USERNAME`, `SVN_PASSWORD` and `GH_RELEASE_TOKEN`, which can only be set once WordPress.org has approved the plugin.
- **The first submission** is a manual upload of the zip at https://wordpress.org/plugins/developers/add/. SVN access, and so the workflows, follow approval.
- A release bumps the version in three places: `giftkit.php` (header and `GIFTKIT_VERSION`), `readme.txt` (`Stable tag` and the changelog), then `npm run readme`.

## Working conventions

- Commit on a branch and open a pull request for anything beyond small fixes; `main` is what sites install from GitHub.
- Check live state rather than trusting notes: `wp plugin list` for what's active, `git status` for the checkout, `gh repo view mwender/giftkit` for the remote.
