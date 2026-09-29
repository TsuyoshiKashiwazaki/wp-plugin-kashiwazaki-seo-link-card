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

    // どのリンクか（カード / 文字リンク、ページ内の何番目か）。想定外の値は「不明」として記録する
    $link_type = isset( $_POST['link_type'] ) ? sanitize_key( wp_unslash( $_POST['link_type'] ) ) : '';
    $link_type = in_array( $link_type, array( 'card', 'text' ), true ) ? $link_type : '';
    $link_pos  = isset( $_POST['link_pos'] ) ? absint( wp_unslash( $_POST['link_pos'] ) ) : 0;
    $link_pos  = ( $link_pos >= 1 && $link_pos <= 9999 ) ? $link_pos : 0;

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
            'link_type' => $link_type,
            'link_pos' => $link_pos,
            'clicked_at' => current_time('mysql')
        ],
        ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s']
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
 * - target="_blank" のときは利用者指定の rel があっても noopener を必ず含める
 * - ショートコードに rel が無いときだけ、管理画面で登録したドメインへのリンクに sponsored / nofollow を自動付与する
 *
 * @param array $atts        ショートコード属性
 * @param bool  $is_external 外部リンクか
 * @param array $urls        rel 自動付与の照合に使う URL（元の URL と転送後の URL）
 */
function kslc_build_link_attrs( $atts, $is_external, $urls = array() ) {
    $values = kslc_link_attr_values( $atts, $is_external, $urls );

    $output = '';
    if ( '' !== $values['target'] ) {
        $output .= ' target="' . esc_attr( $values['target'] ) . '"';
    }
    if ( '' !== $values['rel'] ) {
        $output .= ' rel="' . esc_attr( $values['rel'] ) . '"';
    }
    return $output;
}

/**
 * target / rel の値を決める（kslc_build_link_attrs() と、ブロックエディターの目印付きリンクの書き換えで共通）
 *
 * @param array $atts        ショートコード属性（target / rel）
 * @param bool  $is_external 外部リンクか
 * @param array $urls        rel 自動付与の照合に使う URL
 * @return array [ 'target' => string, 'rel' => string ]（無いときは空文字）
 */
function kslc_link_attr_values( $atts, $is_external, $urls = array() ) {
    $target = ! empty( $atts['target'] ) ? $atts['target'] : ( $is_external ? '_blank' : '' );
    $rel    = ! empty( $atts['rel'] ) ? preg_split( '/\s+/', trim( $atts['rel'] ) ) : [];
    if ( empty( $rel ) && ! empty( $urls ) ) {
        $auto_rel = kslc_get_auto_rel_for_urls( $urls );
        if ( '' !== $auto_rel ) {
            $rel[] = $auto_rel;
        }
    }
    if ( '_blank' === $target && ! in_array( 'noopener', $rel, true ) ) {
        $rel[] = 'noopener';
    }

    return array(
        'target' => (string) $target,
        'rel'    => empty( $rel ) ? '' : implode( ' ', array_unique( $rel ) ),
    );
}

/**
 * 投稿に解決できた内部 URL を正規のパーマリンクにそろえる
 * - 元 URL のパスがパーマリンクのパスと一致する（末尾スラッシュの有無・http/https・www の揺れだけ）ときだけ置き換え、
 *   元 URL の ?query と #fragment はそのまま付け直す（url_to_postid() は # と ? を捨てて解決するため、ここで戻す）
 * - パスが一致しない（/page/2/ や /feed/ などの追加パス）ときは元 URL をそのまま返す
 * - プレーンなパーマリンク（?p=ID 形式）のサイトでは置き換えない
 *
 * @param string $url     元の URL（絶対 URL）
 * @param int    $post_id 解決できた投稿 ID
 * @return string
 */
function kslc_normalize_internal_url( $url, $post_id ) {
    $permalink = get_permalink( $post_id );
    if ( ! $permalink ) {
        return $url;
    }
    $url_parts  = wp_parse_url( $url );
    $perm_parts = wp_parse_url( $permalink );
    if ( ! is_array( $url_parts ) || ! is_array( $perm_parts ) || isset( $perm_parts['query'] ) || isset( $perm_parts['fragment'] ) ) {
        return $url;
    }
    $url_path  = rtrim( isset( $url_parts['path'] ) ? (string) $url_parts['path'] : '/', '/' );
    $perm_path = rtrim( isset( $perm_parts['path'] ) ? (string) $perm_parts['path'] : '/', '/' );
    if ( $url_path !== $perm_path ) {
        return $url;
    }
    $result = $permalink;
    if ( isset( $url_parts['query'] ) && '' !== $url_parts['query'] ) {
        $result .= '?' . $url_parts['query'];
    }
    if ( isset( $url_parts['fragment'] ) && '' !== $url_parts['fragment'] ) {
        $result .= '#' . $url_parts['fragment'];
    }
    return $result;
}

/**
 * カードの href に使う URL を決める（転送先への自動追随）
 * - 設定 OFF: ショートコードの URL をそのまま使う
 * - 内部リンク（投稿に解決できた）: get_permalink() の正規 URL（末尾スラッシュや http/https の揺れを正規化。?query と #fragment は保つ）
 * - 外部リンク: OGP 取得時に記録した恒久的な転送（301 / 308）後の最終 URL（キャッシュに保存されている）。元 URL の #fragment は引き継ぐ
 *
 * @param string      $url      ショートコードから得た URL
 * @param array|false $ogp_data OGP データ（final_url を含むことがある）
 * @param int         $post_id  内部リンクとして解決できた投稿 ID（無ければ 0）
 */
function kslc_resolve_output_url( $url, $ogp_data, $post_id = 0 ) {
    if ( ! kslc_follow_redirects_enabled() ) {
        return $url;
    }

    if ( $post_id > 0 && 'publish' === get_post_status( $post_id ) ) {
        return kslc_normalize_internal_url( $url, $post_id );
    }

    if ( is_array( $ogp_data ) && ! empty( $ogp_data['final_url'] ) ) {
        $final_url = (string) $ogp_data['final_url'];
        if ( kslc_is_http_url( $final_url ) && filter_var( $final_url, FILTER_VALIDATE_URL ) ) {
            return kslc_inherit_fragment( $final_url, $url );
        }
    }

    return $url;
}

/**
 * 転送後の URL に #fragment が無ければ、元の URL の #fragment を引き継ぐ
 * （RFC 9110 §10.2.2: Location に fragment が無い転送は、元の参照の fragment を引き継いで処理する。ブラウザと同じ扱い）
 *
 * @param string $final_url    転送後の URL
 * @param string $original_url ショートコードに書かれた元の URL
 * @return string
 */
function kslc_inherit_fragment( $final_url, $original_url ) {
    $fragment = wp_parse_url( $original_url, PHP_URL_FRAGMENT );
    if ( ! is_string( $fragment ) || '' === $fragment ) {
        return $final_url;
    }
    $final_fragment = wp_parse_url( $final_url, PHP_URL_FRAGMENT );
    if ( is_string( $final_fragment ) && '' !== $final_fragment ) {
        return $final_url;
    }
    return $final_url . '#' . $fragment;
}

/**
 * サムネイル <img> に付ける属性: 遅延読み込みと非同期デコード、設定のサムネイルサイズに合わせた width / height
 * （CSS 側は .kslc-thumbnail を同じ幅・高さの枠にし、img は枠いっぱいに object-fit: cover で表示するため矛盾しない）
 */
function kslc_thumbnail_img_attrs( $width, $height ) {
    return sprintf( ' width="%d" height="%d" loading="lazy" decoding="async"', (int) $width, (int) $height );
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

    // リンク先の決定（post_id 指定・URL 指定の解釈、削除済み投稿の記録）は文字リンクと共通
    $target = kslc_resolve_shortcode_target( $atts );
    if ( null === $target ) {
        return '';
    }
    $url     = $target['url'];
    $post_id = $target['post_id'];

    // post_id 指定でカスタムタイトルが指定されていない場合は投稿タイトルを使用
    if ( empty( $atts['title'] ) && '' !== $target['post_title'] ) {
        $atts['title'] = $target['post_title'];
    }

    $ogp_data = kslc_get_ogp_data( $url, $post_id );

    // 出力に使う URL: 転送先への自動追随が ON なら、内部リンクは投稿の正規パーマリンク、外部リンクはリダイレクト後の最終 URL
    // （ショートコードに書かれた URL 自体は変更しない）
    $href_url = kslc_resolve_output_url( $url, $ogp_data, $post_id );

    if ( ! $ogp_data ) {
        // スクレイピング失敗時は簡易的なデコレーションパネルを出力
        return kslc_render_fallback_card( $href_url, $atts );
    }

    // カスタムタイトルが指定されている場合はそれを使用
    $title = ! empty( $atts['title'] ) ? esc_html( $atts['title'] ) : ( ! empty( $ogp_data['title'] ) ? esc_html( $ogp_data['title'] ) : '' );
    $description = ! empty( $ogp_data['description'] ) ? esc_html( $ogp_data['description'] ) : '';
    $image = ! empty( $ogp_data['image'] ) ? esc_url( $ogp_data['image'] ) : '';
    $site_name = ! empty( $ogp_data['site_name'] ) ? esc_html( $ogp_data['site_name'] ) : '';

    // 外部／内部の判定は実際にリンクする URL（転送後）で行う
    $is_external = kslc_is_external_href( $href_url );

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
    $output .= '<blockquote cite="' . esc_attr($href_url) . '" class="kslc-blockquote">';
    $output .= '<div class="' . $card_class . '" style="' . esc_attr($inline_style) . '">';

    // サムネイルがない場合のみカード全体にバッジを配置
    if ($show_badge && (!$show_thumbnail || empty($image))) {
        if ($is_external) {
            $output .= '<span class="kslc-external-link-badge">外部リンク</span>';
        } else {
            $output .= '<span class="kslc-internal-link-badge">内部リンク</span>';
        }
    }

    // target属性とrel属性の処理（登録ドメインへのリンクには rel を自動付与。ショートコードの rel 指定が優先）
    $target_attr = kslc_build_link_attrs( $atts, $is_external, array( $url, $href_url ) );

    $output .= '<a href="' . esc_url($href_url) . '"' . $target_attr . ' class="kslc-link">';
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

        $output .= '<img src="' . $image . '" alt="' . $title . '"' . kslc_thumbnail_img_attrs( $thumbnail_width, $thumbnail_height ) . '>';
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

// 文中の文字リンク [linktext url="…" text="…"]（閉じタグなし）。カードとは別のタグにして、見た目の出力だけを分ける
add_shortcode( 'kashiwazaki_seo_link_text', 'kslc_link_text_shortcode' );
add_shortcode( 'linktext', 'kslc_link_text_shortcode' );

/**
 * カード・文字リンクで共通のショートコードのタグ名（リンク切れの定期チェックの収集対象）
 *
 * @return string[]
 */
function kslc_shortcode_tags() {
    return array( 'kashiwazaki_seo_link_card', 'linkcard', 'nlink', 'kashiwazaki_seo_link_text', 'linktext' );
}

/**
 * ショートコードの属性からリンク先を決める（カードと文字リンクで共通）
 * - post_id 指定: 投稿のパーマリンク。投稿が無ければリンク切れとして記録して null（表示のたびに記録しないよう一定時間に 1 回）
 * - url 指定: 相対 URL は絶対 URL に直し、同じホストなら投稿 ID を引く。URL として不正なら null
 *
 * @param array $atts shortcode_atts() 後の属性（post_id / url）
 * @return array|null [ 'url' => string, 'post_id' => int, 'post_title' => string ]（post_title は post_id 指定のときだけ）
 */
function kslc_resolve_shortcode_target( $atts ) {
    // 内部リンクの場合（post_idが指定されている）
    if ( ! empty( $atts['post_id'] ) && is_numeric( $atts['post_id'] ) ) {
        $post_id = intval( $atts['post_id'] );
        $post = get_post( $post_id );

        if ( ! $post ) {
            // 指定された投稿が存在しない（削除済みなど）→ リンク切れとして記録する
            // 表示のたびに option を書かないよう、URL ごとに KSLC_FAILURE_CACHE_SECONDS の間は記録を 1 回に抑える
            $missing_key = 'kslc_missing_post_' . $post_id;
            if ( false === get_transient( $missing_key ) ) {
                kslc_record_link_failure( kslc_post_reference_url( $post_id ), 404, '投稿が見つかりません（削除または非公開）', kslc_current_page_id() );
                set_transient( $missing_key, 1, KSLC_FAILURE_CACHE_SECONDS );
            }
            return null;
        }

        // 表示時に「投稿が見つからない」と記録した投稿が見つかるようになった（復元・再作成）→ 記録を外す
        $missing_key = 'kslc_missing_post_' . $post_id;
        if ( 'publish' === get_post_status( $post_id ) && false !== get_transient( $missing_key ) ) {
            kslc_record_link_ok( kslc_post_reference_url( $post_id ) );
            delete_transient( $missing_key );
        }

        return array(
            'url'        => get_permalink( $post_id ),
            'post_id'    => $post_id,
            'post_title' => (string) get_the_title( $post_id ),
        );
    }

    // URLが指定されている場合
    $url = isset( $atts['url'] ) ? (string) $atts['url'] : '';

    // 相対URLの場合は絶対URLに変換
    if ( ! empty( $url ) && strpos( $url, '/' ) === 0 && strpos( $url, '//' ) !== 0 ) {
        $url = home_url( $url );
    }

    $url = sanitize_url( $url );
    if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
        return null;
    }

    // 内部リンクの場合はpost_idを取得
    $post_id   = 0;
    $site_host = parse_url( home_url(), PHP_URL_HOST );
    $link_host = parse_url( $url, PHP_URL_HOST );
    if ( $site_host === $link_host ) {
        $post_id = (int) url_to_postid( $url );
    }

    return array(
        'url'        => $url,
        'post_id'    => $post_id,
        'post_title' => '',
    );
}

/**
 * 実際にリンクする URL が外部か（parse_url() が失敗した場合は外部として扱う）
 *
 * @param string $href_url 出力に使う URL（転送後）
 * @return bool
 */
function kslc_is_external_href( $href_url ) {
    $site_host = parse_url( home_url(), PHP_URL_HOST );
    $link_host = parse_url( $href_url, PHP_URL_HOST );
    return ( $site_host === false || $link_host === false || $site_host !== $link_host );
}

/**
 * 文字リンクの中身（リンク文字）に許すインライン要素。<a> は入れ子になるので許さない
 *
 * @return array wp_kses() の許可リスト
 */
function kslc_text_link_allowed_html() {
    $allowed = array(
        'strong' => array( 'class' => true ),
        'b'      => array( 'class' => true ),
        'em'     => array( 'class' => true ),
        'i'      => array( 'class' => true ),
        'span'   => array( 'class' => true ),
        'code'   => array( 'class' => true ),
        'mark'   => array( 'class' => true ),
        'small'  => array( 'class' => true ),
        'sub'    => array(),
        'sup'    => array(),
        'br'     => array(),
    );
    return apply_filters( 'kslc_text_link_allowed_html', $allowed );
}

/**
 * 文中の文字リンク [linktext url="…" text="リンク文字"]（閉じタグなし）
 * - リンク先の決定・転送先への自動追随・内部/外部の判定・target と rel（rel 自動付与を含む）はカードと同じ関数を通す
 * - 出力は段落を壊さないインラインの <a> だけ（div・blockquote を出さない）
 * - リンク文字: 閉じタグ付きで書かれたときの中身 → text 属性 → 投稿タイトル（post_id 指定）→ リンク先のタイトル（OGP・内部は記事タイトル）→ URL
 * - 閉じタグ付きで書かれても壊さない。公式の Shortcode API は同じタグの単独型と囲み型の混在を扱えず、
 *   [linktext a] 本文 [linktext b]x[/linktext] は「a が『 本文 [linktext b]x』を囲む」と解析される。
 *   中身に同じタグの開始が入っていたら、中身は本文の続き（誤って囲まれた部分）とみなし、末尾に閉じタグを補って解析し直してリンクの後ろに出す
 *   （b は x をリンク文字として正しく出る。文章は消えない）
 * - リンク先が決まらない（削除済みの投稿・不正な URL）ときは、リンクを外して文字だけを残す（文章が途中で欠けないように）
 *
 * @param array|string $atts    ショートコード属性（属性なしのときは空文字）
 * @param string|null  $content 囲まれた中身（閉じタグなしのときは null）
 * @param string       $tag     ショートコードのタグ名（linktext / kashiwazaki_seo_link_text）
 * @return string
 */
function kslc_link_text_shortcode( $atts, $content = null, $tag = 'linktext' ) {
    $atts = shortcode_atts(
        [
            'url'     => '',
            'post_id' => 0,
            'text'    => '',
            'target'  => '',
            'rel'     => '',
        ],
        $atts,
        'kashiwazaki_seo_link_text'
    );

    $tag = in_array( $tag, array( 'linktext', 'kashiwazaki_seo_link_text' ), true ) ? $tag : 'linktext';

    $label_html = '';
    $trailing   = '';
    if ( null !== $content && '' !== trim( (string) $content ) ) {
        if ( preg_match( '/\[' . preg_quote( $tag, '/' ) . '(?![\w-])/', (string) $content ) ) {
            // 同じタグの開始が中身に入っている = 前の閉じタグなしのショートコードが後ろの閉じタグまで囲んでしまった
            $trailing = do_shortcode( (string) $content . '[/' . $tag . ']' );
        } else {
            // 中身は投稿者が書いた HTML。インライン要素だけを残す（出力の安全はハンドラ側の責任: Shortcode API）
            $label_html = trim( wp_kses( (string) $content, kslc_text_link_allowed_html() ) );
            if ( '' === trim( wp_strip_all_tags( $label_html ) ) ) {
                $label_html = '';
            }
        }
    }
    if ( '' === $label_html && '' !== trim( (string) $atts['text'] ) ) {
        $label_html = esc_html( trim( (string) $atts['text'] ) );
    }

    $link = kslc_prepare_text_link( $atts );
    if ( null === $link ) {
        return $label_html . $trailing;
    }

    if ( '' === $label_html ) {
        $label_html = esc_html( '' !== $link['title'] ? $link['title'] : $link['href'] );
    }

    $link_attrs = '';
    if ( '' !== $link['target'] ) {
        $link_attrs .= ' target="' . esc_attr( $link['target'] ) . '"';
    }
    if ( '' !== $link['rel'] ) {
        $link_attrs .= ' rel="' . esc_attr( $link['rel'] ) . '"';
    }

    return '<a href="' . esc_url( $link['href'] ) . '"' . $link_attrs . ' class="' . esc_attr( $link['class'] ) . '">' . $label_html . '</a>' . $trailing;
}

/**
 * 文字リンクの中身の処理（[linktext] とブロックエディターの目印付きリンクで共通）
 * リンク先の決定・OGP 取得（リンク切れの記録を含む）・転送先への自動追随・内部/外部の判定・target と rel はカードと同じ関数を通す
 *
 * @param array $atts url / post_id / target / rel
 * @return array|null [ 'href', 'is_external', 'target', 'rel', 'title', 'class' ]。リンク先が決まらないときは null
 */
function kslc_prepare_text_link( $atts ) {
    $target = kslc_resolve_shortcode_target( $atts );
    if ( null === $target ) {
        return null;
    }

    $ogp_data    = kslc_get_ogp_data( $target['url'], $target['post_id'] );
    $href_url    = kslc_resolve_output_url( $target['url'], $ogp_data, $target['post_id'] );
    $is_external = kslc_is_external_href( $href_url );
    $values      = kslc_link_attr_values( $atts, $is_external, array( $target['url'], $href_url ) );

    // リンク文字を省いたときに使うタイトル: 投稿タイトル（post_id 指定）→ リンク先のタイトル（OGP・内部は記事タイトル）
    $title = '';
    if ( '' !== $target['post_title'] ) {
        $title = $target['post_title'];
    } elseif ( is_array( $ogp_data ) && ! empty( $ogp_data['title'] ) ) {
        $title = (string) $ogp_data['title'];
    }

    return array(
        'href'        => $href_url,
        'is_external' => $is_external,
        'target'      => $values['target'],
        'rel'         => $values['rel'],
        'title'       => $title,
        'class'       => 'kslc-text-link ' . ( $is_external ? 'kslc-text-link-external' : 'kslc-text-link-internal' ),
    );
}

/**
 * ブロックエディターの「SEO文字リンク」で入れた目印付きリンク <a class="kslc-textlink" href="…">…</a> を表示時に書き換える
 * - href は転送先への自動追随の結果に、rel はカードと同じ規則（rel 自動付与を含む）にそろえ、クリック計測のクラスを足す
 * - target はエディターで選んだとおり（「新しいタブで開く」がオフなら付けない = 同じタブ）。外部リンクでも既定の新しいタブにはしない
 * - リンク文字（中身）は変えない。本文に保存された HTML も変えない（表示のときだけ）
 * - WP_HTML_Tag_Processor（WordPress 6.2 以降）が無い環境では書き換えず、普通のリンクのまま出す
 *
 * @param string $content 本文
 * @return string
 */
function kslc_filter_text_link_markers( $content ) {
    if ( ! is_string( $content ) || false === strpos( $content, 'kslc-textlink' ) || ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
        return $content;
    }

    $tags = new WP_HTML_Tag_Processor( $content );
    while ( $tags->next_tag( array( 'tag_name' => 'a', 'class_name' => 'kslc-textlink' ) ) ) {
        $href = $tags->get_attribute( 'href' );
        if ( ! is_string( $href ) || '' === trim( $href ) ) {
            continue;
        }
        $saved_target = $tags->get_attribute( 'target' );
        $saved_rel    = $tags->get_attribute( 'rel' );

        // target は書き手がエディターで選んだとおりにする（「新しいタブで開く」がオフなら target は保存されない）。
        // カード・[linktext] の「外部リンクは既定で新しいタブ」をここで当てると、オフにした外部リンクも新しいタブになり、
        // エディターの「同じタブで開きます」と食い違う。target が無いときは同じタブ（HTML の既定 _self）として rel を決め、属性は付けない
        $has_saved_target = is_string( $saved_target ) && '' !== trim( $saved_target );

        $link = kslc_prepare_text_link( array(
            'url'     => trim( $href ),
            'post_id' => 0,
            'target'  => $has_saved_target ? trim( $saved_target ) : '_self',
            'rel'     => is_string( $saved_rel ) ? $saved_rel : '',
        ) );
        if ( null === $link ) {
            continue; // URL として不正（javascript: など）なら触らない
        }

        // set_attribute() は値をエスケープして書くので、ここでは HTML エスケープしない esc_url_raw() を使う
        $tags->set_attribute( 'href', esc_url_raw( $link['href'] ) );
        if ( '' !== $link['target'] && '_self' !== $link['target'] ) {
            $tags->set_attribute( 'target', $link['target'] );
        } else {
            $tags->remove_attribute( 'target' );
        }
        if ( '' !== $link['rel'] ) {
            $tags->set_attribute( 'rel', $link['rel'] );
        } else {
            $tags->remove_attribute( 'rel' );
        }
        foreach ( explode( ' ', $link['class'] ) as $class_name ) {
            $tags->add_class( $class_name );
        }
    }

    return $tags->get_updated_html();
}
// do_shortcode（11）と wp_filter_content_tags（12）の後
add_filter( 'the_content', 'kslc_filter_text_link_markers', 13 );

/**
 * ブロックエディターの中で目印付きリンクを普通のリンクと見分けられるようにする（エディターの中だけ）
 */
function kslc_enqueue_text_link_editor_style() {
    if ( ! is_admin() ) {
        return;
    }
    wp_register_style( 'kslc-text-link-editor', false, array(), KSLC_PLUGIN_VERSION );
    wp_enqueue_style( 'kslc-text-link-editor' );
    wp_add_inline_style( 'kslc-text-link-editor', '.kslc-textlink{text-decoration-style:dashed!important;text-underline-offset:3px;}.kslc-textlink::after{content:"\2197";font-size:.75em;margin-left:1px;opacity:.6;}' );
}
add_action( 'enqueue_block_assets', 'kslc_enqueue_text_link_editor_style' );

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

    // target属性とrel属性の処理（登録ドメインへのリンクには rel を自動付与。ショートコードの rel 指定が優先）
    $target_attr = kslc_build_link_attrs( $atts, $is_external, array( $url ) );

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

        $output .= '<img src="' . esc_url( $favicon_url ) . '" alt="' . esc_attr( $domain ) . '"' . kslc_thumbnail_img_attrs( $thumbnail_width, $thumbnail_height ) . '>';
        $output .= '</div>';
    }

    $output .= '</a>';
    $output .= '</div>';
    $output .= '</blockquote>';

    return $output;
}
