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
        KSLC_PLUGIN_VERSION,
        true
    );
}
add_action( 'admin_enqueue_scripts', 'kslc_enqueue_admin_scripts' );