=== Kashiwazaki SEO Link Card ===
Contributors: Tsuyoshi Kashiwazaki
Donate link: https://tsuyoshikashiwazaki.jp/
Tags: link, card, seo, shortcode, ogp
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A plugin to display a link as a card by fetching OGP data.

== Description ==

This plugin allows you to display a link in a card format by simply using a shortcode. It fetches the OGP (Open Graph Protocol) data from the specified URL and displays the title, description, and thumbnail image in a clean and modern card.

Usage: `[linkcard url="https://example.com"]`

== Installation ==

1. Upload the `kashiwazaki-seo-link-card` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Use the shortcode `[linkcard url="YOUR_URL_HERE"]` in your posts or pages.

== Frequently Asked Questions ==

= Does this plugin cache the data? =

Yes, it caches the fetched OGP data for 24 hours using WordPress transients to improve performance and avoid making requests on every page load.

== Screenshots ==

1. Example of a link card.

== Changelog ==

= 1.0.1 =
* Fixed: URL encoding handling for special characters
* Fixed: parse_url() error handling for invalid URLs
* Fixed: Cache clear functionality implementation
* Improved: External site request headers for better compatibility

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.0.0 =
* Initial version of the plugin.
