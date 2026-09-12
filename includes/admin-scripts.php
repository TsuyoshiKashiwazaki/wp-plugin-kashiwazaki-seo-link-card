<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 管理画面でカラーピッカーとスクリプトをエンキュー
function kslc_enqueue_admin_scripts( $hook ) {
    // 自分のプラグインの設定ページでのみ読み込み
    if ( $hook !== 'toplevel_page_kashiwazaki-seo-link-card' ) {
        return;
    }
    
    // WordPressのカラーピッカーをエンキュー
    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_script( 'wp-color-picker' );
    
    // カスタムJavaScriptをエンキュー
    wp_enqueue_script(
        'kslc-admin-script',
        plugin_dir_url( __FILE__ ) . '../assets/js/admin.js',
        array( 'jquery', 'wp-color-picker' ),
        kslc_asset_version( plugin_dir_path( __FILE__ ) . '../assets/js/admin.js' ),
        true
    );
}
add_action( 'admin_enqueue_scripts', 'kslc_enqueue_admin_scripts' );

/**
 * アセットのバージョン文字列（プラグインバージョン + ファイル更新時刻）
 * 同じバージョン番号のまま中身を変えても CDN / ブラウザが旧ファイルを配信し続けないようにする
 */
function kslc_asset_version( $file_path ) {
    $mtime = file_exists( $file_path ) ? filemtime( $file_path ) : 0;
    return KSLC_PLUGIN_VERSION . '.' . $mtime;
}