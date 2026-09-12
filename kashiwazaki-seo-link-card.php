<?php
/**
 * Plugin Name: Kashiwazaki SEO Link Card
 * Plugin URI: https://www.tsuyoshikashiwazaki.jp
 * Version: 1.0.9
 * Author: 柏崎剛 (Tsuyoshi Kashiwazaki)
 * Author URI: https://www.tsuyoshikashiwazaki.jp/profile/
 * Description: URLを記述するだけで、ページの情報を取得してカード形式で表示するプラグインです。
 * License: GPL2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'KSLC_PLUGIN_VERSION', '1.0.9' );
define( 'KSLC_PLUGIN_FILE', __FILE__ );

// User-Agent for external requests (can be filtered)
if ( ! defined( 'KSLC_USER_AGENT' ) ) {
    define( 'KSLC_USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36' );
}


// 設定ファイルを読み込み
require_once plugin_dir_path( __FILE__ ) . 'includes/config.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/color-utils.php';

// URL正規化関数（共通関数）
if (!function_exists('kslc_normalize_url')) {
    function kslc_normalize_url($url) {
        return preg_replace('/#.*$/', '', $url);
    }
}

// 出力バッファリングの問題を防ぐため、適切な順序でファイルを読み込み
if (!defined('KSLC_INCLUDES_LOADED')) {
    define('KSLC_INCLUDES_LOADED', true);

    require_once plugin_dir_path( __FILE__ ) . 'includes/ogp.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/shortcode.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/admin-menu.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/admin-scripts.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/block-patterns.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/rest-api.php';
}

// プラグイン有効化時にデータベーステーブルを作成
function kslc_create_analytics_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'kslc_analytics';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        url varchar(500) NOT NULL,
        page_url varchar(500) NOT NULL,
        ip_address varchar(45) NOT NULL,
        user_agent text,
        title varchar(500),
        clicked_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY url_index (url(191)),
        KEY page_url_index (page_url(191)),
        KEY clicked_at_index (clicked_at)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $result = dbDelta($sql);

    // テーブルが作成されたか確認
    $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;

    return $table_exists;
}
register_activation_hook(__FILE__, 'kslc_create_analytics_table');

function kslc_register_settings() {
    // 既存のキャッシュ設定（下位互換性のため残す）
    register_setting( 'kslc_options_group', 'kslc_cache_period', [
        'type' => 'integer',
        'sanitize_callback' => function($value) {
            $value = absint($value);
            return $value < 1 ? 24 : $value;
        },
        'default' => 24,
    ]);

    // 外部リンク用キャッシュ期間
    register_setting( 'kslc_options_group', 'kslc_external_cache_period', [
        'type' => 'integer',
        'sanitize_callback' => function($value) {
            $value = absint($value);
            return $value < 1 ? 6 : $value;
        },
        'default' => KSLC_DEFAULT_EXTERNAL_CACHE,
    ]);

    // 内部リンク用キャッシュ期間
    register_setting( 'kslc_options_group', 'kslc_internal_cache_period', [
        'type' => 'integer',
        'sanitize_callback' => function($value) {
            $value = absint($value);
            return $value < 1 ? 72 : $value;
        },
        'default' => KSLC_DEFAULT_INTERNAL_CACHE,
    ]);

    // 外部リンク用設定
    register_setting( 'kslc_options_group', 'kslc_external_color_theme', [
        'type' => 'string',
        'sanitize_callback' => function($value) {
            return kslc_sanitize_choice( $value, KSLC_ALLOWED_COLOR_THEMES, 'blue' );
        },
        'default' => 'blue',
    ]);
    register_setting( 'kslc_options_group', 'kslc_external_show_thumbnail', [
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true,
    ]);
    register_setting( 'kslc_options_group', 'kslc_external_thumbnail_position', [
        'type' => 'string',
        'sanitize_callback' => function($value) {
            return kslc_sanitize_choice( $value, KSLC_ALLOWED_THUMBNAIL_POSITIONS, 'right' );
        },
        'default' => 'right',
    ]);
    register_setting( 'kslc_options_group', 'kslc_external_show_badge', [
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true,
    ]);

    // 内部リンク用設定
    register_setting( 'kslc_options_group', 'kslc_internal_color_theme', [
        'type' => 'string',
        'sanitize_callback' => function($value) {
            return kslc_sanitize_choice( $value, KSLC_ALLOWED_COLOR_THEMES, 'gray' );
        },
        'default' => 'gray',
    ]);
    register_setting( 'kslc_options_group', 'kslc_internal_show_thumbnail', [
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true,
    ]);
    register_setting( 'kslc_options_group', 'kslc_internal_thumbnail_position', [
        'type' => 'string',
        'sanitize_callback' => function($value) {
            return kslc_sanitize_choice( $value, KSLC_ALLOWED_THUMBNAIL_POSITIONS, 'right' );
        },
        'default' => 'right',
    ]);
    register_setting( 'kslc_options_group', 'kslc_internal_show_badge', [
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true,
    ]);
    
    // サムネイルサイズ設定
    register_setting( 'kslc_options_group', 'kslc_thumbnail_width', [
        'type' => 'integer',
        'sanitize_callback' => function($value) {
            return kslc_sanitize_int_range( $value, KSLC_THUMBNAIL_WIDTH_MIN, KSLC_THUMBNAIL_WIDTH_MAX, KSLC_DEFAULT_THUMBNAIL_WIDTH );
        },
        'default' => KSLC_DEFAULT_THUMBNAIL_WIDTH,
    ]);
    register_setting( 'kslc_options_group', 'kslc_thumbnail_height', [
        'type' => 'integer',
        'sanitize_callback' => function($value) {
            return kslc_sanitize_int_range( $value, KSLC_THUMBNAIL_HEIGHT_MIN, KSLC_THUMBNAIL_HEIGHT_MAX, KSLC_DEFAULT_THUMBNAIL_HEIGHT );
        },
        'default' => KSLC_DEFAULT_THUMBNAIL_HEIGHT,
    ]);
    
    // カスタムカラー設定
    register_setting( 'kslc_options_group', 'kslc_external_custom_color', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_hex_color',
        'default' => KSLC_DEFAULT_EXTERNAL_COLOR,
    ]);
    register_setting( 'kslc_options_group', 'kslc_internal_custom_color', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_hex_color',
        'default' => KSLC_DEFAULT_INTERNAL_COLOR,
    ]);
}
add_action('admin_init', 'kslc_register_settings');

/**
 * 設定値が選択肢に含まれていればそれを、含まれていなければ既定値を返す
 */
function kslc_sanitize_choice( $value, $allowed, $default ) {
    $value = sanitize_text_field( (string) $value );
    return in_array( $value, $allowed, true ) ? $value : $default;
}

/**
 * 整数を [min, max] の範囲に丸める（数値でなければ既定値）
 */
function kslc_sanitize_int_range( $value, $min, $max, $default ) {
    if ( ! is_numeric( $value ) ) {
        return $default;
    }
    return max( $min, min( $max, (int) $value ) );
}

/**
 * このプラグインが保存した全キャッシュ（OGP データ・ページタイトル）を削除する
 *
 * @return int 削除したキャッシュ件数
 */
function kslc_clear_cache() {
    global $wpdb;

    // 本体行（_transient_kslc_*）と有効期限行（_transient_timeout_kslc_*）の両方を消す
    $deleted = (int) $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like( '_transient_kslc_' ) . '%',
            $wpdb->esc_like( '_transient_timeout_kslc_' ) . '%'
        )
    );

    // 外部オブジェクトキャッシュ利用時は transient が options テーブルに無いのでキャッシュ側も破棄する
    if ( wp_using_ext_object_cache() ) {
        wp_cache_flush();
    }

    return (int) ( $deleted / 2 );
}
