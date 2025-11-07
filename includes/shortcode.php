<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function kslc_enqueue_styles() {
    wp_enqueue_style(
        'kashiwazaki-seo-link-card-style',
        plugin_dir_url( __FILE__ ) . '../assets/css/style.css',
        [],
        filemtime( plugin_dir_path( __FILE__ ) . '../assets/css/style.css' )
    );
}
add_action( 'wp_enqueue_scripts', 'kslc_enqueue_styles' );

function kslc_enqueue_analytics_script() {
    // JavaScriptファイルのパスを確認
    $js_file_path = plugin_dir_path( __FILE__ ) . '../assets/js/analytics.js';
    $js_file_url = plugin_dir_url( __FILE__ ) . '../assets/js/analytics.js';

    // ファイルが存在するかチェック
    if (!file_exists($js_file_path)) {
        error_log('KSLC Analytics: JavaScript file not found at: ' . $js_file_path);
        return;
    }

    // スクリプトがすでにエンキューされているかチェック
    if (wp_script_is('kslc-analytics', 'enqueued') || wp_script_is('kslc-analytics', 'done')) {
        return; // すでにエンキューされている場合は何もしない
    }

    wp_enqueue_script('jquery');
    wp_enqueue_script(
        'kslc-analytics',
        $js_file_url,
        ['jquery'],
        filemtime($js_file_path),
        true
    );

    // AJAX用のデータを渡す
    wp_localize_script('kslc-analytics', 'kslc_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('kslc_analytics_nonce')
    ]);

    // スクリプトエンキューのログは削除済み（不要なため）
}
add_action( 'wp_enqueue_scripts', 'kslc_enqueue_analytics_script' );



// AJAX処理でクリックデータを保存
function kslc_handle_click_tracking() {
    // 出力バッファリングをクリア（エラー防止）
    if (ob_get_level()) {
        ob_clean();
    }

    // POSTデータの存在確認
    if (empty($_POST['nonce'])) {
        wp_send_json_error(['message' => 'No nonce provided']);
    }

    // ノンス検証
    if (!wp_verify_nonce($_POST['nonce'], 'kslc_analytics_nonce')) {
        wp_send_json_error(['message' => 'Security check failed']);
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'kslc_analytics';

    // テーブルの存在確認
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
    if (!$table_exists) {
        wp_send_json_error(['message' => 'Analytics table does not exist']);
    }

    $url = sanitize_url($_POST['url']);
    $title = sanitize_text_field($_POST['title']);
    $page_url = sanitize_url($_POST['page_url']);
    $is_external = (bool) $_POST['is_external'];
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT']);

    // URL正規化（#部分を削除）して統計では統合
    $normalized_url = kslc_normalize_url($url);
    $normalized_page_url = kslc_normalize_url($page_url); // ページURLも正規化

    // データベースに保存（正規化されたURLを使用）
    $result = $wpdb->insert(
        $table_name,
        [
            'url' => $normalized_url,
            'title' => $title,
            'page_url' => $normalized_page_url, // 正規化されたページURL
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'clicked_at' => current_time('mysql')
        ],
        ['%s', '%s', '%s', '%s', '%s', '%s']
    );

    if ($result !== false) {
        wp_send_json_success(['message' => 'Click tracked successfully', 'id' => $wpdb->insert_id]);
    } else {
        // エラー時のみログ出力（重要なエラーのため残す）
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('KSLC Analytics: Insert failed, last error: ' . $wpdb->last_error);
        }
        wp_send_json_error(['message' => 'Failed to track click']);
    }
}
add_action('wp_ajax_kslc_track_click', 'kslc_handle_click_tracking');
add_action('wp_ajax_nopriv_kslc_track_click', 'kslc_handle_click_tracking');


function kslc_link_card_shortcode( $atts ) {
    $atts = shortcode_atts(
        [
            'url' => '',
            'post_id' => 0,
            'title' => '',
            'target' => '',
            'rel' => '',
        ],
        $atts,
        'kashiwazaki_seo_link_card'
    );

    // 内部リンクの場合（post_idが指定されている）
    if ( ! empty( $atts['post_id'] ) && is_numeric( $atts['post_id'] ) ) {
        $post_id = intval( $atts['post_id'] );
        $post = get_post( $post_id );
        
        if ( ! $post ) {
            return '';
        }
        
        $url = get_permalink( $post_id );
        
        // カスタムタイトルが指定されていない場合は投稿タイトルを使用
        if ( empty( $atts['title'] ) ) {
            $atts['title'] = get_the_title( $post_id );
        }
    } else {
        // 外部リンクの場合（URLが指定されている）
        $url = esc_url( $atts['url'] );
        if ( empty( $url ) ) {
            return '';
        }
    }

    $ogp_data = kslc_get_ogp_data( $url );

    if ( ! $ogp_data ) {
        return '<a href="' . $url . '" target="_blank" rel="noopener">' . $url . '</a>';
    }

    // カスタムタイトルが指定されている場合はそれを使用
    $title = ! empty( $atts['title'] ) ? esc_html( $atts['title'] ) : ( ! empty( $ogp_data['title'] ) ? esc_html( $ogp_data['title'] ) : '' );
    $description = ! empty( $ogp_data['description'] ) ? esc_html( $ogp_data['description'] ) : '';
    $image = ! empty( $ogp_data['image'] ) ? esc_url( $ogp_data['image'] ) : '';
    $site_name = ! empty( $ogp_data['site_name'] ) ? esc_html( $ogp_data['site_name'] ) : '';

    $site_host = parse_url( home_url(), PHP_URL_HOST );
    $link_host = parse_url( $url, PHP_URL_HOST );

    $is_external = $site_host !== $link_host;

    // 外部リンクと内部リンクで異なる設定を取得
    if ($is_external) {
        $color_theme = get_option('kslc_external_color_theme', 'blue');
        $show_thumbnail = get_option('kslc_external_show_thumbnail', true);
        $thumbnail_position = get_option('kslc_external_thumbnail_position', 'right');
        $show_badge = get_option('kslc_external_show_badge', true);
    } else {
        $color_theme = get_option('kslc_internal_color_theme', 'gray');
        $show_thumbnail = get_option('kslc_internal_show_thumbnail', true);
        $thumbnail_position = get_option('kslc_internal_thumbnail_position', 'right');
        $show_badge = get_option('kslc_internal_show_badge', true);
    }

    $card_class = 'kslc-card';
    if ($is_external) {
        $card_class .= ' kslc-external-link';
    } else {
        $card_class .= ' kslc-internal-link';
    }
    
    // カラーテーマクラスを適用
    $card_class .= ' kslc-theme-' . esc_attr($color_theme);
    
    $card_class .= ' kslc-thumb-' . esc_attr($thumbnail_position);
    if (!$show_thumbnail || empty($image)) {
        $card_class .= ' kslc-no-thumbnail';
    }

    $output = '';
    
    // 管理画面で設定されたサムネイルサイズを取得
    $thumbnail_width = get_option('kslc_thumbnail_width', KSLC_DEFAULT_THUMBNAIL_WIDTH);
    $thumbnail_height = get_option('kslc_thumbnail_height', KSLC_DEFAULT_THUMBNAIL_HEIGHT);
    
    // CSS変数をインラインスタイルで上書き
    $inline_style = sprintf(
        '--kslc-thumbnail-width: %dpx; --kslc-thumbnail-height: %dpx;',
        $thumbnail_width,
        $thumbnail_height
    );
    
    // カスタムカラーが設定されている場合、プリセットと同じように全ての色変数を設定
    if ($color_theme === 'custom') {
        $custom_color = $is_external ? 
            get_option('kslc_external_custom_color', KSLC_DEFAULT_EXTERNAL_COLOR) : 
            get_option('kslc_internal_custom_color', KSLC_DEFAULT_INTERNAL_COLOR);
        
        // カスタムカラーから配色を生成（プリセットと同じアルゴリズム）
        $color_scheme = kslc_generate_color_scheme($custom_color);
        
        // プリセットと完全に同じCSS変数を設定
        $inline_style .= sprintf(
            ' --kslc-primary-color: %s; --kslc-text-color: %s; --kslc-meta-color: %s; --kslc-bg-color: %s; --kslc-border-color: %s;',
            esc_attr($color_scheme['primary']),
            esc_attr($color_scheme['text']),
            esc_attr($color_scheme['meta']),
            esc_attr($color_scheme['bg']),
            esc_attr($color_scheme['border'])
        );
    }

    // 引用タグで囲んで引用であることを明示（SEO対策）
    $output .= '<blockquote cite="' . esc_attr($url) . '" class="kslc-blockquote">';
    $output .= '<div class="' . $card_class . '" style="' . esc_attr($inline_style) . '">';

    // サムネイルがない場合のみカード全体にバッジを配置
    if ($show_badge && (!$show_thumbnail || empty($image))) {
        if ($is_external) {
            $output .= '<span class="kslc-external-link-badge">外部リンク</span>';
        } else {
            $output .= '<span class="kslc-internal-link-badge">内部リンク</span>';
        }
    }

    // target属性とrel属性の処理
    $target_attr = '';
    if ( ! empty( $atts['target'] ) ) {
        $target_attr = ' target="' . esc_attr( $atts['target'] ) . '"';
        if ( $atts['target'] === '_blank' && empty( $atts['rel'] ) ) {
            $target_attr .= ' rel="noopener"';
        }
    } elseif ( $is_external ) {
        $target_attr = ' target="_blank" rel="noopener"';
    }
    
    if ( ! empty( $atts['rel'] ) ) {
        $target_attr .= ' rel="' . esc_attr( $atts['rel'] ) . '"';
    }

    $output .= '<a href="' . $url . '"' . $target_attr . ' class="kslc-link">';
    $output .= '<div class="kslc-content">';
    $output .= '<div class="kslc-title">' . $title . '</div>';
    $output .= '<div class="kslc-description">' . $description . '</div>';
    $output .= '<div class="kslc-site-name">' . $site_name . '</div>';
    $output .= '</div>';
    if ( $show_thumbnail && ! empty( $image ) ) {
        $output .= '<div class="kslc-thumbnail">';

        // サムネイルがある場合は画像の上にバッジを配置
        if ($show_badge) {
            if ($is_external) {
                $output .= '<span class="kslc-external-link-badge">外部リンク</span>';
            } else {
                $output .= '<span class="kslc-internal-link-badge">内部リンク</span>';
            }
        }

        $output .= '<img src="' . $image . '" alt="' . $title . '">';
        $output .= '</div>';
    }
    $output .= '</a>';
    $output .= '</div>';
    $output .= '</blockquote>';

    return $output;
}
add_shortcode( 'kashiwazaki_seo_link_card', 'kslc_link_card_shortcode' );
add_shortcode( 'linkcard', 'kslc_link_card_shortcode' );
add_shortcode( 'nlink', 'kslc_link_card_shortcode' );
