<?php
/**
 * Plugin Name: Kashiwazaki SEO Link Card
 * Plugin URI: https://www.tsuyoshikashiwazaki.jp
 * Version: 1.0.11
 * Author: 柏崎剛 (Tsuyoshi Kashiwazaki)
 * Author URI: https://www.tsuyoshikashiwazaki.jp/profile/
 * Description: URLを記述するだけで、ページの情報を取得してカード形式で表示するプラグインです。
 * License: GPL2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'KSLC_PLUGIN_VERSION', '1.0.11' );
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
    require_once plugin_dir_path( __FILE__ ) . 'includes/link-check.php';
}

// クリック統計テーブルの版（列を増やしたら上げる）。2: どのリンクかを示す link_type（card / text）と link_pos（ページ内の何番目か）を追加
define( 'KSLC_DB_VERSION', '2' );

// プラグイン有効化時・表の版が古いときにデータベーステーブルを作成／更新（dbDelta は足りない列を足す）
function kslc_create_analytics_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'kslc_analytics';

    $charset_collate = $wpdb->get_charset_collate();

    // dbDelta の書式: 1 行 1 列、PRIMARY KEY の後は空白 2 つ、KEY を使う（公式 Plugin Handbook「Creating Tables with Plugins」）
    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        url varchar(500) NOT NULL,
        page_url varchar(500) NOT NULL,
        ip_address varchar(45) NOT NULL,
        user_agent text,
        title varchar(500),
        link_type varchar(10) DEFAULT '' NOT NULL,
        link_pos smallint(5) unsigned DEFAULT 0 NOT NULL,
        clicked_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY url_index (url(191)),
        KEY page_url_index (page_url(191)),
        KEY clicked_at_index (clicked_at)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // テーブルが作成されたか確認
    $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
    if ( $table_exists ) {
        update_option( 'kslc_db_version', KSLC_DB_VERSION );
    }

    return $table_exists;
}
register_activation_hook(__FILE__, 'kslc_create_analytics_table');

/**
 * プラグインを更新したとき（有効化フックは呼ばれない）に表の版を確かめ、古ければ列を足す
 */
function kslc_maybe_upgrade_analytics_table() {
    if ( KSLC_DB_VERSION !== get_option( 'kslc_db_version' ) ) {
        kslc_create_analytics_table();
    }
}
add_action( 'plugins_loaded', 'kslc_maybe_upgrade_analytics_table' );

// リンク切れの定期チェック（WP-Cron）: 有効化時に登録し、無効化時に必ず解除する
register_activation_hook(__FILE__, 'kslc_schedule_link_check');
register_deactivation_hook(__FILE__, 'kslc_unschedule_link_check');

function kslc_register_settings() {
    // ---- リンク先の扱い（1.1.0）----

    // 転送先への自動追随（既定 ON）
    register_setting( 'kslc_links_group', 'kslc_follow_redirects', [
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true,
    ]);

    // rel 自動付与: 対象ドメイン（改行区切り）と付与する値
    register_setting( 'kslc_links_group', 'kslc_auto_rel_domains', [
        'type' => 'string',
        'sanitize_callback' => 'kslc_sanitize_domain_list',
        'default' => '',
    ]);
    register_setting( 'kslc_links_group', 'kslc_auto_rel_value', [
        'type' => 'string',
        'sanitize_callback' => function($value) {
            return kslc_sanitize_choice( $value, KSLC_ALLOWED_AUTO_REL_VALUES, 'sponsored' );
        },
        'default' => 'sponsored',
    ]);

    // リンク切れの定期チェック: 有効 / 間隔（時間）
    register_setting( 'kslc_links_group', 'kslc_link_check_enabled', [
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true,
    ]);
    register_setting( 'kslc_links_group', 'kslc_link_check_interval', [
        'type' => 'integer',
        'sanitize_callback' => function($value) {
            return kslc_sanitize_int_range( $value, KSLC_LINK_CHECK_INTERVAL_MIN, KSLC_LINK_CHECK_INTERVAL_MAX, KSLC_DEFAULT_LINK_CHECK_INTERVAL );
        },
        'default' => KSLC_DEFAULT_LINK_CHECK_INTERVAL,
    ]);

    // 既存のキャッシュ設定（下位互換性のため残す）
    register_setting( 'kslc_cache_group', 'kslc_cache_period', [
        'type' => 'integer',
        'sanitize_callback' => function($value) {
            $value = absint($value);
            return $value < 1 ? 24 : $value;
        },
        'default' => 24,
    ]);

    // 外部リンク用キャッシュ期間
    register_setting( 'kslc_cache_group', 'kslc_external_cache_period', [
        'type' => 'integer',
        'sanitize_callback' => function($value) {
            $value = absint($value);
            return $value < 1 ? 6 : $value;
        },
        'default' => KSLC_DEFAULT_EXTERNAL_CACHE,
    ]);

    // 内部リンク用キャッシュ期間
    register_setting( 'kslc_cache_group', 'kslc_internal_cache_period', [
        'type' => 'integer',
        'sanitize_callback' => function($value) {
            $value = absint($value);
            return $value < 1 ? 72 : $value;
        },
        'default' => KSLC_DEFAULT_INTERNAL_CACHE,
    ]);

    // 外部リンク用設定
    register_setting( 'kslc_design_group', 'kslc_external_color_theme', [
        'type' => 'string',
        'sanitize_callback' => function($value) {
            return kslc_sanitize_choice( $value, KSLC_ALLOWED_COLOR_THEMES, 'blue' );
        },
        'default' => 'blue',
    ]);
    register_setting( 'kslc_design_group', 'kslc_external_show_thumbnail', [
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true,
    ]);
    register_setting( 'kslc_design_group', 'kslc_external_thumbnail_position', [
        'type' => 'string',
        'sanitize_callback' => function($value) {
            return kslc_sanitize_choice( $value, KSLC_ALLOWED_THUMBNAIL_POSITIONS, 'right' );
        },
        'default' => 'right',
    ]);
    register_setting( 'kslc_design_group', 'kslc_external_show_badge', [
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true,
    ]);

    // 内部リンク用設定
    register_setting( 'kslc_design_group', 'kslc_internal_color_theme', [
        'type' => 'string',
        'sanitize_callback' => function($value) {
            return kslc_sanitize_choice( $value, KSLC_ALLOWED_COLOR_THEMES, 'gray' );
        },
        'default' => 'gray',
    ]);
    register_setting( 'kslc_design_group', 'kslc_internal_show_thumbnail', [
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true,
    ]);
    register_setting( 'kslc_design_group', 'kslc_internal_thumbnail_position', [
        'type' => 'string',
        'sanitize_callback' => function($value) {
            return kslc_sanitize_choice( $value, KSLC_ALLOWED_THUMBNAIL_POSITIONS, 'right' );
        },
        'default' => 'right',
    ]);
    register_setting( 'kslc_design_group', 'kslc_internal_show_badge', [
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true,
    ]);
    
    // サムネイルサイズ設定
    register_setting( 'kslc_design_group', 'kslc_thumbnail_width', [
        'type' => 'integer',
        'sanitize_callback' => function($value) {
            return kslc_sanitize_int_range( $value, KSLC_THUMBNAIL_WIDTH_MIN, KSLC_THUMBNAIL_WIDTH_MAX, KSLC_DEFAULT_THUMBNAIL_WIDTH );
        },
        'default' => KSLC_DEFAULT_THUMBNAIL_WIDTH,
    ]);
    register_setting( 'kslc_design_group', 'kslc_thumbnail_height', [
        'type' => 'integer',
        'sanitize_callback' => function($value) {
            return kslc_sanitize_int_range( $value, KSLC_THUMBNAIL_HEIGHT_MIN, KSLC_THUMBNAIL_HEIGHT_MAX, KSLC_DEFAULT_THUMBNAIL_HEIGHT );
        },
        'default' => KSLC_DEFAULT_THUMBNAIL_HEIGHT,
    ]);
    
    // カスタムカラー設定
    register_setting( 'kslc_design_group', 'kslc_external_custom_color', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_hex_color',
        'default' => KSLC_DEFAULT_EXTERNAL_COLOR,
    ]);
    register_setting( 'kslc_design_group', 'kslc_internal_custom_color', [
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
 * ドメイン一覧（改行・カンマ・空白区切り）を正規化して改行区切りで返す
 * - URL で書かれていればホスト名だけを取り出す
 * - 小文字化し、先頭の "www." は外す（照合側も同じ規則で比較する）
 * - ホスト名として妥当なものだけ残す
 */
function kslc_sanitize_domain_list( $value ) {
    $items = preg_split( '/[\s,]+/u', (string) $value );
    $domains = [];
    foreach ( $items as $item ) {
        $host = kslc_normalize_host( $item );
        if ( '' !== $host && preg_match( '/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9-]{2,63}$/', $host ) ) {
            $domains[ $host ] = true;
        }
    }
    return implode( "\n", array_keys( $domains ) );
}

/**
 * ホスト名を照合用に正規化する（URL ならホスト部を取り出し、小文字化し、先頭の www. を外す）
 */
function kslc_normalize_host( $value ) {
    $value = trim( (string) $value );
    if ( '' === $value ) {
        return '';
    }
    if ( ! preg_match( '#^[a-z][a-z0-9+.\-]*://#i', $value ) ) {
        $value = 'http://' . ltrim( $value, '/' );
    }
    $host = strtolower( (string) parse_url( $value, PHP_URL_HOST ) );
    return preg_replace( '/^www\./', '', $host );
}

/**
 * 転送先への自動追随が有効か（既定 ON）
 */
function kslc_follow_redirects_enabled() {
    return (bool) get_option( 'kslc_follow_redirects', true );
}

/**
 * 登録ドメインに該当する URL があれば、自動付与する rel 値（sponsored / nofollow）を返す。該当しなければ空文字
 * "example.com" は example.com とそのサブドメイン（sub.example.com）に一致する
 */
function kslc_get_auto_rel_for_urls( $urls ) {
    $raw = (string) get_option( 'kslc_auto_rel_domains', '' );
    if ( '' === trim( $raw ) ) {
        return '';
    }
    $domains = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
    if ( empty( $domains ) ) {
        return '';
    }
    $rel_value = kslc_sanitize_choice( get_option( 'kslc_auto_rel_value', 'sponsored' ), KSLC_ALLOWED_AUTO_REL_VALUES, 'sponsored' );

    foreach ( (array) $urls as $url ) {
        $host = kslc_normalize_host( $url );
        if ( '' === $host ) {
            continue;
        }
        foreach ( $domains as $domain ) {
            if ( $host === $domain || substr( $host, - ( strlen( $domain ) + 1 ) ) === '.' . $domain ) {
                return $rel_value;
            }
        }
    }
    return '';
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
