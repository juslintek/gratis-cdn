=== Gratis CDN ===
Contributors: juslintek
Tags: cdn, performance, cache busting, cloudflare, assets
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Asset URL rewriting, content-hash cache busting, and a one-click CDN purge API.

== Description ==

Asset URL rewriting, content-hash cache busting, and a one-click CDN purge API.

Part of the Gratis suite — a collection of free, no-upsell WordPress plugins.

**Features:**

* Rewrite asset URLs to your CDN host
* Content-hash fingerprinting for cache busting
* One-click purge for Cloudflare, BunnyCDN, KeyCDN or a generic webhook
* Authenticated purge REST endpoint
* Bridges the suite cache-purge action to flush the edge

**Philosophy:**

Everything is free. No "Pro" version, no locked features, no nag screens. Optional managed cloud services may be offered separately to cover infrastructure costs.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Configure it from its settings screen in the WordPress admin.

== Frequently Asked Questions ==

= Is this plugin really free? =

Yes. Everything is free — no "Pro" version, no locked features, and no nag screens. Optional managed cloud services may be offered separately to cover infrastructure costs.

= Does this plugin make external HTTP requests? =

No. Unless you explicitly configure an external service (such as a CDN or SMTP host), the plugin does not phone home or send analytics.

= Where do I get support? =

Open an issue on the plugin's GitHub repository.

== Third-Party Services ==

This plugin connects to CDN provider APIs when you configure purge credentials and trigger a cache purge.

= Cloudflare =

Used for zone purge API calls when configured as your CDN provider.
* [Terms of Service](https://www.cloudflare.com/terms/)
* [Privacy Policy](https://www.cloudflare.com/privacypolicy/)

= BunnyCDN =

Used for pull zone purge API calls when configured as your CDN provider.
* [Terms of Service](https://bunny.net/tos/)
* [Privacy Policy](https://bunny.net/privacy/)

= KeyCDN =

Used for zone purge API calls when configured as your CDN provider.
* [Terms of Service](https://www.keycdn.com/terms)
* [Privacy Policy](https://www.keycdn.com/privacy)

No data is sent to any external service unless you explicitly configure a CDN provider and credentials in the plugin settings.

== Changelog ==

= 1.0.0 =
* Initial public release.

== Upgrade Notice ==

= 1.0.0 =
Initial public release.
