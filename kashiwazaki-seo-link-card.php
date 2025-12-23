<?php
/**
 * Plugin Name: Kashiwazaki SEO Link Card
 * Plugin URI: https://www.tsuyoshikashiwazaki.jp
 * Version: 1.0.5
 * Author: 柏崎剛 (Tsuyoshi Kashiwazaki)
 * Author URI: https://www.tsuyoshikashiwazaki.jp/profile/
 * Description: URLを記述するだけで、ページの情報を取得してカード形式で表示するプラグインです。
 * License: GPL2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'KSLC_PLUGIN_VERSION', '1.0.5' );
define( 'KSLC_PLUGIN_FILE', __FILE__ );

// User-Agent for external requests (can be filtered)
if ( ! defined( 'KSLC_USER_AGENT' ) ) {
    define( 'KSLC_USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36' );
}

// デバッグ用：出力バッファリングエラーの追跡（無効化）
function kslc_debug_output_buffer() {
    // プラグインは正常動作確認済みのため、デバッグログを無効化
    return;

    if (defined('WP_DEBUG') && WP_DEBUG && defined('KSLC_ENABLE_DEBUG_LOGS')) {
        error_log('KSLC Debug: Output buffer level at plugin init: ' . ob_get_level());
    }
}
add_action('init', 'kslc_debug_output_buffer', 1);

// プラグインのシャットダウン処理で出力バッファリングを安全に処理
function kslc_safe_shutdown() {
    // 完全無効化オプション
    if (defined('KSLC_DISABLE_OUTPUT_BUFFER_HANDLING') && KSLC_DISABLE_OUTPUT_BUFFER_HANDLING) {
        return;
    }

    // プラグインは正常動作確認済みのため、デバッグログを無効化
    if (defined('WP_DEBUG') && WP_DEBUG && defined('KSLC_ENABLE_DEBUG_LOGS')) {
        error_log('KSLC Debug: Output buffer level at shutdown: ' . ob_get_level());
    }

    // 安全な出力バッファリング処理（保守的なアプローチ）
    if (ob_get_level() > 1) { // 1つは残しておく
        try {
            while (ob_get_level() > 1) {
                if (!@ob_end_clean()) {
                    break;
                }
            }
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG && defined('KSLC_ENABLE_DEBUG_LOGS')) {
                error_log('KSLC Debug: Shutdown buffer handling error: ' . $e->getMessage());
            }
        }
    }
}

// 出力バッファリング処理を条件付きで登録
if (!defined('KSLC_DISABLE_OUTPUT_BUFFER_HANDLING') || !KSLC_DISABLE_OUTPUT_BUFFER_HANDLING) {
    register_shutdown_function('kslc_safe_shutdown');
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
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;

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
        'sanitize_callback' => 'sanitize_text_field',
        'default' => 'blue',
    ]);
    register_setting( 'kslc_options_group', 'kslc_external_show_thumbnail', [
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true,
    ]);
    register_setting( 'kslc_options_group', 'kslc_external_thumbnail_position', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
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
        'sanitize_callback' => 'sanitize_text_field',
        'default' => 'gray',
    ]);
    register_setting( 'kslc_options_group', 'kslc_internal_show_thumbnail', [
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true,
    ]);
    register_setting( 'kslc_options_group', 'kslc_internal_thumbnail_position', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
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
        'sanitize_callback' => 'absint',
        'default' => KSLC_DEFAULT_THUMBNAIL_WIDTH,
    ]);
    register_setting( 'kslc_options_group', 'kslc_thumbnail_height', [
        'type' => 'integer',
        'sanitize_callback' => 'absint',
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

function kslc_clear_all_transients() {
    if ( ! isset( $_POST['kslc_clear_cache_nonce'] ) || ! wp_verify_nonce( $_POST['kslc_clear_cache_nonce'], 'kslc_clear_cache_action' ) ) {
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    global $wpdb;
    $prefix = $wpdb->get_blog_prefix();

    // OGPキャッシュを削除
    $transient_name = '_transient_kslc_ogp_data_%';
    $sql = $wpdb->prepare(
        "DELETE FROM {$prefix}options WHERE option_name LIKE %s",
        $transient_name
    );
    $wpdb->query( $sql );

    // ページタイトルキャッシュも削除
    $page_title_transient = '_transient_kslc_page_title_%';
    $sql = $wpdb->prepare(
        "DELETE FROM {$prefix}options WHERE option_name LIKE %s",
        $page_title_transient
    );
    $wpdb->query( $sql );

    add_settings_error( 'kslc_messages', 'kslc_message', __( 'All link card caches have been cleared.', 'kashiwazaki-seo-link-card' ), 'updated' );
}
add_action('admin_init', 'kslc_clear_all_transients');
