<?php
/**
 * ブロックパターン登録
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ブロックパターンカテゴリーの登録
 */
function kslc_register_block_pattern_category() {
    register_block_pattern_category(
        'kslc-patterns',
        array(
            'label' => __( 'SEOリンクカード', 'kashiwazaki-seo-link-card' ),
        )
    );
}
add_action( 'init', 'kslc_register_block_pattern_category' );

/**
 * ブロックパターンの登録
 */
function kslc_register_block_patterns() {
    // 基本のリンクカードパターン
    register_block_pattern(
        'kslc/basic-link-card',
        array(
            'title'       => __( '基本のリンクカード', 'kashiwazaki-seo-link-card' ),
            'description' => __( 'URLを入力してリンクカードを表示', 'kashiwazaki-seo-link-card' ),
            'categories'  => array( 'kslc-patterns', 'featured' ),
            'keywords'    => array( 'link', 'card', 'seo', 'url' ),
            'content'     => '<!-- wp:shortcode -->[kashiwazaki_seo_link_card url="https://example.com"]<!-- /wp:shortcode -->',
        )
    );

    // カスタムタイトル付きリンクカード
    register_block_pattern(
        'kslc/custom-title-link-card',
        array(
            'title'       => __( 'カスタムタイトル付きリンクカード', 'kashiwazaki-seo-link-card' ),
            'description' => __( 'タイトルを指定できるリンクカード', 'kashiwazaki-seo-link-card' ),
            'categories'  => array( 'kslc-patterns' ),
            'keywords'    => array( 'link', 'card', 'seo', 'title' ),
            'content'     => '<!-- wp:shortcode -->[kashiwazaki_seo_link_card url="https://example.com" title="カスタムタイトル"]<!-- /wp:shortcode -->',
        )
    );

    // 新規タブで開くリンクカード
    register_block_pattern(
        'kslc/blank-target-link-card',
        array(
            'title'       => __( '新規タブで開くリンクカード', 'kashiwazaki-seo-link-card' ),
            'description' => __( '新しいタブで開くリンクカード', 'kashiwazaki-seo-link-card' ),
            'categories'  => array( 'kslc-patterns' ),
            'keywords'    => array( 'link', 'card', 'seo', 'blank' ),
            'content'     => '<!-- wp:shortcode -->[kashiwazaki_seo_link_card url="https://example.com" target="_blank"]<!-- /wp:shortcode -->',
        )
    );

    // 複数のリンクカード
    register_block_pattern(
        'kslc/multiple-link-cards',
        array(
            'title'       => __( '複数のリンクカード', 'kashiwazaki-seo-link-card' ),
            'description' => __( '複数のリンクカードを並べて表示', 'kashiwazaki-seo-link-card' ),
            'categories'  => array( 'kslc-patterns' ),
            'keywords'    => array( 'link', 'card', 'seo', 'multiple' ),
            'content'     => '<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">
<!-- wp:heading -->
<h3>関連記事</h3>
<!-- /wp:heading -->

<!-- wp:shortcode -->
[kashiwazaki_seo_link_card url="https://example.com/article1"]
<!-- /wp:shortcode -->

<!-- wp:shortcode -->
[kashiwazaki_seo_link_card url="https://example.com/article2"]
<!-- /wp:shortcode -->

<!-- wp:shortcode -->
[kashiwazaki_seo_link_card url="https://example.com/article3"]
<!-- /wp:shortcode -->
</div>
<!-- /wp:group -->',
        )
    );

    // サイドバー用コンパクトリンクカード
    register_block_pattern(
        'kslc/sidebar-link-cards',
        array(
            'title'       => __( 'サイドバー用リンクカード', 'kashiwazaki-seo-link-card' ),
            'description' => __( 'サイドバー向けのコンパクトなリンクカード', 'kashiwazaki-seo-link-card' ),
            'categories'  => array( 'kslc-patterns' ),
            'keywords'    => array( 'link', 'card', 'sidebar', 'widget' ),
            'content'     => '<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">
<!-- wp:heading {"level":4} -->
<h4>人気記事</h4>
<!-- /wp:heading -->

<!-- wp:shortcode -->
[kashiwazaki_seo_link_card url="https://example.com/popular1"]
<!-- /wp:shortcode -->

<!-- wp:shortcode -->
[kashiwazaki_seo_link_card url="https://example.com/popular2"]
<!-- /wp:shortcode -->
</div>
<!-- /wp:group -->',
        )
    );
}
add_action( 'init', 'kslc_register_block_patterns' );

/**
 * ブロックエディタ用のスクリプト登録
 */
function kslc_enqueue_block_editor_assets() {
    wp_enqueue_script(
        'kslc-block-editor',
        plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/block-editor.js',
        array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n' ),
        KSLC_PLUGIN_VERSION,
        true
    );

    wp_localize_script(
        'kslc-block-editor',
        'kslcBlockEditor',
        array(
            'pluginUrl' => plugin_dir_url( dirname( __FILE__ ) ),
            'nonce'     => wp_create_nonce( 'wp_rest' ),
        )
    );
}
add_action( 'enqueue_block_editor_assets', 'kslc_enqueue_block_editor_assets' );