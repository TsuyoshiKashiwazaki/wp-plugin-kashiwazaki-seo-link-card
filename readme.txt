=== Kashiwazaki SEO Link Card ===
Contributors: Tsuyoshi Kashiwazaki
Donate link: https://tsuyoshikashiwazaki.jp/
Tags: link, card, seo, shortcode, ogp
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 1.0.7
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

= 1.0.7 =
* Fixed: Character encoding issue for non-UTF-8 pages (Shift_JIS, EUC-JP, ISO-2022-JP)

= 1.0.6 =
* Added: Fallback decoration panel when scraping fails

= 1.0.5 =
* Added: OGP image URL validation (fallback if non-200 response)
* Fixed: Block editor post search now matches title substring correctly

= 1.0.4 =
* Added: Google Favicon API fallback when OGP image is not available

= 1.0.3 =
* Fixed: Internal link data not retrieved when post_id is specified
* Fixed: Relative URLs (/path/to/page/) not being processed correctly
* Fixed: Data being cleared when switching link types
* Added: "Clear Settings" button in block editor

= 1.0.2 =
* Fixed: Resolved relative OGP image URLs not being converted to absolute URLs.

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
