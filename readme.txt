=== Kashiwazaki SEO Link Card ===
Contributors: Tsuyoshi Kashiwazaki
Donate link: https://tsuyoshikashiwazaki.jp/
Tags: link, card, seo, shortcode, ogp
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 1.0.9
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

= 1.0.9 =
* 修正: 相対パスの画像URLを RFC 3986 準拠で絶対URLに変換するように修正（末尾が / のURLや短いディレクトリ名で誤ったURLになり、サムネイルが404になっていた）
* 修正: <base href> とリダイレクト後の最終URLを相対URL解決の基準として考慮
* 修正: 代替サムネイルも実在確認（HTTP 2xx かつ image/*）を通してから使用
* 修正: PHP 8 で相手ページの charset 名が不正な場合に致命的エラーになる問題
* 修正: 外部リンクのクリック計測が setTimeout + window.open に依存し、ポップアップブロックで遷移しないことがあった問題
* 修正: キャッシュクリアが二重実行され件数表示が狂う問題、外部オブジェクトキャッシュ環境で効かない問題
* セキュリティ: 外部取得を wp_safe_remote_get / wp_safe_remote_head に変更（SSRF対策）し、応答サイズに上限を設定
* セキュリティ: クリック計測エンドポイントにレート制限と url / page_url の検証を追加
* セキュリティ: 統計画面が計測データ中の任意の外部URLへ取得に行かないように修正
* 変更: 全公開投稿タイプの show_in_rest を強制的に有効化する処理を削除（プラグイン独自の /kslc/v1/post-types を使用）
* 変更: 他プラグインの出力バッファを破棄しうる shutdown 処理を削除

= 1.0.8 =
* Added: ID selection tab for directly entering post ID in block editor
* Added: URL search feature to search posts by URL/slug in block editor
* Fixed: Post type dropdown now shows all custom post types (not just "All")
* Fixed: ID selection now correctly reflects in shortcode preview
* Fixed: JavaScript cache issue - version parameter now uses plugin version

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
