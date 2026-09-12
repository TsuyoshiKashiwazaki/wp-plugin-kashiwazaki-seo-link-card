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

    wp_enqueue_script(
        'kslc-analytics',
        $js_file_url,
        [],
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

    // ノンス検証
    $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
    if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'kslc_analytics_nonce' ) ) {
        wp_send_json_error(['message' => 'Security check failed'], 403);
    }

    // 未ログインでも叩ける入口なので、IP あたりの受付件数を制限する（テーブル肥大化対策）
    $ip_address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
    $rate_key   = 'kslc_click_rate_' . md5( $ip_address );
    $rate_count = (int) get_transient( $rate_key );
    if ( $rate_count >= KSLC_CLICK_RATE_LIMIT ) {
        wp_send_json_error(['message' => 'Too many requests'], 429);
    }
    set_transient( $rate_key, $rate_count + 1, MINUTE_IN_SECONDS );

    $url      = isset( $_POST['url'] ) ? sanitize_url( wp_unslash( $_POST['url'] ) ) : '';
    $title    = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
    $page_url = isset( $_POST['page_url'] ) ? sanitize_url( wp_unslash( $_POST['page_url'] ) ) : '';

    // リンク先は http(s) の URL に限る
    if ( '' === $url || ! filter_var( $url, FILTER_VALIDATE_URL ) || ! kslc_is_http_url( $url ) ) {
        wp_send_json_error(['message' => 'Invalid url'], 400);
    }

    // カードが置かれているページは必ずこのサイト上にある。外部 URL は受け付けない
    if ( '' === $page_url || ! filter_var( $page_url, FILTER_VALIDATE_URL ) || ! kslc_is_same_site_url( $page_url ) ) {
        wp_send_json_error(['message' => 'Invalid page_url'], 400);
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'kslc_analytics';

    // テーブルの存在確認
    $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
    if (!$table_exists) {
        wp_send_json_error(['message' => 'Analytics table does not exist']);
    }

    $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

    // URL正規化（#部分を削除）して統計では統合
    $normalized_url = kslc_normalize_url($url);
    $normalized_page_url = kslc_normalize_url($page_url); // ページURLも正規化

    // カラム長（varchar(500)）を超えると STRICT モードで INSERT が失敗するため切り詰める
    $normalized_url      = mb_substr( $normalized_url, 0, 500 );
    $normalized_page_url = mb_substr( $normalized_page_url, 0, 500 );
    $title               = mb_substr( $title, 0, 500 );

    // データベースに保存（正規化されたURLを使用）
    $result = $wpdb->insert(
        $table_name,
        [
            'url' => $normalized_url,
            'title' => $title,
            'page_url' => $normalized_page_url, // 正規化されたページURL
            'ip_address' => mb_substr( $ip_address, 0, 45 ),
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

/**
 * http / https の URL か
 */
function kslc_is_http_url( $url ) {
    $scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );
    return in_array( $scheme, [ 'http', 'https' ], true );
}

/**
 * このサイト上の URL か（www. の有無は同一視する）
 */
function kslc_is_same_site_url( $url ) {
    $strip = function ( $host ) {
        return preg_replace( '/^www\./i', '', strtolower( (string) $host ) );
    };
    $link_host = $strip( parse_url( $url, PHP_URL_HOST ) );
    if ( '' === $link_host ) {
        return false;
    }
    $site_hosts = array_unique( array_filter( [
        $strip( parse_url( home_url(), PHP_URL_HOST ) ),
        $strip( parse_url( site_url(), PHP_URL_HOST ) ),
    ] ) );
    return in_array( $link_host, $site_hosts, true );
}

/**
 * target / rel 属性を組み立てる
 * target="_blank" のときは利用者指定の rel があっても noopener を必ず含める
 */
function kslc_build_link_attrs( $atts, $is_external ) {
    $target = ! empty( $atts['target'] ) ? $atts['target'] : ( $is_external ? '_blank' : '' );
    $rel    = ! empty( $atts['rel'] ) ? preg_split( '/\s+/', trim( $atts['rel'] ) ) : [];
    if ( '_blank' === $target && ! in_array( 'noopener', $rel, true ) ) {
        $rel[] = 'noopener';
    }

    $output = '';
    if ( '' !== $target ) {
        $output .= ' target="' . esc_attr( $target ) . '"';
    }
    if ( ! empty( $rel ) ) {
        $output .= ' rel="' . esc_attr( implode( ' ', array_unique( $rel ) ) ) . '"';
    }
    return $output;
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
        // URLが指定されている場合
        $url = $atts['url'];

        // 相対URLの場合は絶対URLに変換
        if ( ! empty( $url ) && strpos( $url, '/' ) === 0 && strpos( $url, '//' ) !== 0 ) {
            $url = home_url( $url );
        }

        $url = sanitize_url( $url );
        if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return '';
        }

        // 内部リンクの場合はpost_idを取得
        $site_host = parse_url( home_url(), PHP_URL_HOST );
        $link_host = parse_url( $url, PHP_URL_HOST );
        if ( $site_host === $link_host ) {
            $post_id = url_to_postid( $url );
        }
    }

    $ogp_data = kslc_get_ogp_data( $url, isset( $post_id ) ? $post_id : 0 );

    if ( ! $ogp_data ) {
        // スクレイピング失敗時は簡易的なデコレーションパネルを出力
        return kslc_render_fallback_card( $url, $atts );
    }

    // カスタムタイトルが指定されている場合はそれを使用
    $title = ! empty( $atts['title'] ) ? esc_html( $atts['title'] ) : ( ! empty( $ogp_data['title'] ) ? esc_html( $ogp_data['title'] ) : '' );
    $description = ! empty( $ogp_data['description'] ) ? esc_html( $ogp_data['description'] ) : '';
    $image = ! empty( $ogp_data['image'] ) ? esc_url( $ogp_data['image'] ) : '';
    $site_name = ! empty( $ogp_data['site_name'] ) ? esc_html( $ogp_data['site_name'] ) : '';

    $site_host = parse_url( home_url(), PHP_URL_HOST );
    $link_host = parse_url( $url, PHP_URL_HOST );

    // parse_url()が失敗した場合は外部リンクとして扱う
    $is_external = ( $site_host === false || $link_host === false || $site_host !== $link_host );

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
    $target_attr = kslc_build_link_attrs( $atts, $is_external );

    $output .= '<a href="' . esc_url($url) . '"' . $target_attr . ' class="kslc-link">';
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

/**
 * スクレイピング失敗時の簡易デコレーションパネルを出力
 *
 * @param string $url リンク先URL
 * @param array $atts ショートコード属性
 * @return string HTMLカード
 */
function kslc_render_fallback_card( $url, $atts = [] ) {
    // URLからドメインを取得
    $parsed_url = parse_url( $url );
    $domain = isset( $parsed_url['host'] ) ? $parsed_url['host'] : '';

    // カスタムタイトルが指定されていればそれを使用、なければドメイン名
    $title = ! empty( $atts['title'] ) ? esc_html( $atts['title'] ) : esc_html( $domain );

    // 外部リンクとして扱う（スクレイピング失敗は外部リンクが多い）
    $site_host = parse_url( home_url(), PHP_URL_HOST );
    $is_external = ( $site_host !== $domain );

    // 外部リンク用の設定を取得
    if ( $is_external ) {
        $color_theme = get_option( 'kslc_external_color_theme', 'blue' );
        $show_thumbnail = get_option( 'kslc_external_show_thumbnail', true );
        $thumbnail_position = get_option( 'kslc_external_thumbnail_position', 'right' );
        $show_badge = get_option( 'kslc_external_show_badge', true );
    } else {
        $color_theme = get_option( 'kslc_internal_color_theme', 'gray' );
        $show_thumbnail = get_option( 'kslc_internal_show_thumbnail', true );
        $thumbnail_position = get_option( 'kslc_internal_thumbnail_position', 'right' );
        $show_badge = get_option( 'kslc_internal_show_badge', true );
    }

    // Google Favicon APIでfaviconを取得
    $favicon_url = 'https://www.google.com/s2/favicons?domain=' . urlencode( $domain ) . '&sz=128';

    // カードクラスを構築
    $card_class = 'kslc-card kslc-fallback-card';
    $card_class .= $is_external ? ' kslc-external-link' : ' kslc-internal-link';
    $card_class .= ' kslc-theme-' . esc_attr( $color_theme );
    $card_class .= ' kslc-thumb-' . esc_attr( $thumbnail_position );

    if ( ! $show_thumbnail ) {
        $card_class .= ' kslc-no-thumbnail';
    }

    // サムネイルサイズを取得
    $thumbnail_width = get_option( 'kslc_thumbnail_width', KSLC_DEFAULT_THUMBNAIL_WIDTH );
    $thumbnail_height = get_option( 'kslc_thumbnail_height', KSLC_DEFAULT_THUMBNAIL_HEIGHT );

    $inline_style = sprintf(
        '--kslc-thumbnail-width: %dpx; --kslc-thumbnail-height: %dpx;',
        $thumbnail_width,
        $thumbnail_height
    );

    // カスタムカラーの場合
    if ( $color_theme === 'custom' ) {
        $custom_color = $is_external ?
            get_option( 'kslc_external_custom_color', KSLC_DEFAULT_EXTERNAL_COLOR ) :
            get_option( 'kslc_internal_custom_color', KSLC_DEFAULT_INTERNAL_COLOR );

        $color_scheme = kslc_generate_color_scheme( $custom_color );

        $inline_style .= sprintf(
            ' --kslc-primary-color: %s; --kslc-text-color: %s; --kslc-meta-color: %s; --kslc-bg-color: %s; --kslc-border-color: %s;',
            esc_attr( $color_scheme['primary'] ),
            esc_attr( $color_scheme['text'] ),
            esc_attr( $color_scheme['meta'] ),
            esc_attr( $color_scheme['bg'] ),
            esc_attr( $color_scheme['border'] )
        );
    }

    // target属性とrel属性の処理
    $target_attr = kslc_build_link_attrs( $atts, $is_external );

    // HTML出力を構築
    $output = '<blockquote cite="' . esc_attr( $url ) . '" class="kslc-blockquote">';
    $output .= '<div class="' . esc_attr( $card_class ) . '" style="' . esc_attr( $inline_style ) . '">';

    // サムネイルがない場合のバッジ
    if ( $show_badge && ! $show_thumbnail ) {
        $badge_class = $is_external ? 'kslc-external-link-badge' : 'kslc-internal-link-badge';
        $badge_text = $is_external ? '外部リンク' : '内部リンク';
        $output .= '<span class="' . $badge_class . '">' . $badge_text . '</span>';
    }

    $output .= '<a href="' . esc_url( $url ) . '"' . $target_attr . ' class="kslc-link">';
    $output .= '<div class="kslc-content">';
    $output .= '<div class="kslc-title">' . $title . '</div>';
    $output .= '<div class="kslc-description kslc-fallback-url">' . esc_html( $url ) . '</div>';
    $output .= '<div class="kslc-site-name">' . esc_html( $domain ) . '</div>';
    $output .= '</div>';

    if ( $show_thumbnail ) {
        $output .= '<div class="kslc-thumbnail kslc-fallback-thumbnail">';

        // サムネイル上のバッジ
        if ( $show_badge ) {
            $badge_class = $is_external ? 'kslc-external-link-badge' : 'kslc-internal-link-badge';
            $badge_text = $is_external ? '外部リンク' : '内部リンク';
            $output .= '<span class="' . $badge_class . '">' . $badge_text . '</span>';
        }

        $output .= '<img src="' . esc_url( $favicon_url ) . '" alt="' . esc_attr( $domain ) . '">';
        $output .= '</div>';
    }

    $output .= '</a>';
    $output .= '</div>';
    $output .= '</blockquote>';

    return $output;
}
