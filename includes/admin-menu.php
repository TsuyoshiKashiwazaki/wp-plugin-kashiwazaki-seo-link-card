<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function kslc_add_admin_menu() {
    add_menu_page(
        'Kashiwazaki SEO Link Card',
        'Kashiwazaki SEO Link Card',
        'manage_options',
        'kashiwazaki-seo-link-card',
        'kslc_options_page_html',
        'dashicons-admin-links',
        81
    );

    // 統計ページを追加
    add_submenu_page(
        'kashiwazaki-seo-link-card',
        'リンク統計',
        'リンク統計',
        'manage_options',
        'kslc-analytics',
        'kslc_analytics_page_html'
    );
}
add_action( 'admin_menu', 'kslc_add_admin_menu' );

function kslc_add_settings_link( $links ) {
    $settings_link = '<a href="admin.php?page=kashiwazaki-seo-link-card">' . __( 'Settings' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}

// 定数が定義されている場合のみフィルターを追加
if ( defined( 'KSLC_PLUGIN_FILE' ) ) {
    add_filter( 'plugin_action_links_' . plugin_basename( KSLC_PLUGIN_FILE ), 'kslc_add_settings_link' );
}


function kslc_options_page_html() {
    // キャッシュクリア処理
    if ( isset( $_POST['kslc_clear_cache'] ) &&
         check_admin_referer( 'kslc_clear_cache_action', 'kslc_clear_cache_nonce' ) ) {
        global $wpdb;

        // kslc_ogp_data_ で始まる全てのトランジェントを削除
        $deleted_count = $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_kslc_ogp_data_%'
             OR option_name LIKE '_transient_timeout_kslc_ogp_data_%'
             OR option_name LIKE '_transient_kslc_page_title_%'
             OR option_name LIKE '_transient_timeout_kslc_page_title_%'"
        );

        add_settings_error(
            'kslc_messages',
            'kslc_cache_cleared',
            sprintf( '%d 個のキャッシュを削除しました。', $deleted_count ),
            'updated'
        );
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <?php settings_errors( 'kslc_messages' ); ?>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'kslc_options_group' );
            ?>
            <h2>デザイン設定</h2>

            <h3>外部リンク設定</h3>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="kslc_external_color_theme">外部リンクのカラーテーマ</label></th>
                    <td>
                        <select id="kslc_external_color_theme" name="kslc_external_color_theme" class="kslc-color-select">
                            <?php
                            $themes = [
                                '赤' => 'red', 
                                '青' => 'blue', 
                                '緑' => 'green', 
                                '紫' => 'purple', 
                                'オレンジ' => 'orange',
                                '灰色' => 'gray', 
                                '白' => 'white', 
                                '黒' => 'black',
                                'カスタム' => 'custom'
                            ];
                            $current_theme = get_option( 'kslc_external_color_theme', 'blue' );
                            foreach ( $themes as $name => $value ) {
                                echo '<option value="' . esc_attr( $value ) . '"' . selected( $current_theme, $value, false ) . '>' . esc_html( $name ) . '</option>';
                            }
                            ?>
                        </select>
                        <div id="kslc_external_custom_color_wrapper" style="margin-top: 10px; <?php echo get_option( 'kslc_external_color_theme', 'blue' ) === 'custom' ? '' : 'display: none;'; ?>">
                            <input type="text" id="kslc_external_custom_color" name="kslc_external_custom_color" value="<?php echo esc_attr( get_option( 'kslc_external_custom_color', KSLC_DEFAULT_EXTERNAL_COLOR ) ); ?>" class="kslc-color-picker" />
                            <p class="description">カスタムカラーを選択してください。</p>
                        </div>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">外部リンクの画像表示</th>
                    <td>
                        <label>
                            <input type="checkbox" name="kslc_external_show_thumbnail" value="1" <?php checked( get_option( 'kslc_external_show_thumbnail', true ) ); ?> />
                            外部リンクのサムネイル画像を表示する
                        </label>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="kslc_external_thumbnail_position">外部リンクの画像位置</label></th>
                    <td>
                        <select id="kslc_external_thumbnail_position" name="kslc_external_thumbnail_position">
                            <?php
                            $positions = ['右' => 'right', '左' => 'left'];
                            $current_position = get_option( 'kslc_external_thumbnail_position', 'right' );
                            foreach ( $positions as $name => $value ) {
                                echo '<option value="' . esc_attr( $value ) . '"' . selected( $current_position, $value, false ) . '>' . esc_html( $name ) . '</option>';
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">外部リンクバッジ表示</th>
                    <td>
                        <label>
                            <input type="checkbox" name="kslc_external_show_badge" value="1" <?php checked( get_option( 'kslc_external_show_badge', true ) ); ?> />
                            外部リンクバッジを表示する
                        </label>
                    </td>
                </tr>
            </table>

            <h3>内部リンク設定</h3>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="kslc_internal_color_theme">内部リンクのカラーテーマ</label></th>
                    <td>
                        <select id="kslc_internal_color_theme" name="kslc_internal_color_theme" class="kslc-color-select">
                            <?php
                            $themes = [
                                '赤' => 'red', 
                                '青' => 'blue', 
                                '緑' => 'green', 
                                '紫' => 'purple', 
                                'オレンジ' => 'orange',
                                '灰色' => 'gray', 
                                '白' => 'white', 
                                '黒' => 'black',
                                'カスタム' => 'custom'
                            ];
                            $current_theme = get_option( 'kslc_internal_color_theme', 'gray' );
                            foreach ( $themes as $name => $value ) {
                                echo '<option value="' . esc_attr( $value ) . '"' . selected( $current_theme, $value, false ) . '>' . esc_html( $name ) . '</option>';
                            }
                            ?>
                        </select>
                        <div id="kslc_internal_custom_color_wrapper" style="margin-top: 10px; <?php echo get_option( 'kslc_internal_color_theme', 'gray' ) === 'custom' ? '' : 'display: none;'; ?>">
                            <input type="text" id="kslc_internal_custom_color" name="kslc_internal_custom_color" value="<?php echo esc_attr( get_option( 'kslc_internal_custom_color', KSLC_DEFAULT_INTERNAL_COLOR ) ); ?>" class="kslc-color-picker" />
                            <p class="description">カスタムカラーを選択してください。</p>
                        </div>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">内部リンクの画像表示</th>
                    <td>
                        <label>
                            <input type="checkbox" name="kslc_internal_show_thumbnail" value="1" <?php checked( get_option( 'kslc_internal_show_thumbnail', true ) ); ?> />
                            内部リンクのサムネイル画像を表示する
                        </label>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="kslc_internal_thumbnail_position">内部リンクの画像位置</label></th>
                    <td>
                        <select id="kslc_internal_thumbnail_position" name="kslc_internal_thumbnail_position">
                            <?php
                            $positions = ['右' => 'right', '左' => 'left'];
                            $current_position = get_option( 'kslc_internal_thumbnail_position', 'right' );
                            foreach ( $positions as $name => $value ) {
                                echo '<option value="' . esc_attr( $value ) . '"' . selected( $current_position, $value, false ) . '>' . esc_html( $name ) . '</option>';
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">内部リンクバッジ表示</th>
                    <td>
                        <label>
                            <input type="checkbox" name="kslc_internal_show_badge" value="1" <?php checked( get_option( 'kslc_internal_show_badge', true ) ); ?> />
                            内部リンクバッジを表示する
                        </label>
                    </td>
                </tr>
            </table>
            
            <h2>サムネイルサイズ設定</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="kslc_thumbnail_width">サムネイルの幅 (px)</label></th>
                    <td>
                        <input type="number" id="kslc_thumbnail_width" name="kslc_thumbnail_width" value="<?php echo esc_attr( get_option( 'kslc_thumbnail_width', 200 ) ); ?>" min="100" max="400" step="10" required />
                        <p class="description">サムネイル画像の幅をピクセル単位で指定します。（推奨: 160-240px）</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="kslc_thumbnail_height">サムネイルの高さ (px)</label></th>
                    <td>
                        <input type="number" id="kslc_thumbnail_height" name="kslc_thumbnail_height" value="<?php echo esc_attr( get_option( 'kslc_thumbnail_height', 140 ) ); ?>" min="80" max="300" step="10" required />
                        <p class="description">サムネイル画像の高さをピクセル単位で指定します。（推奨: 120-180px）</p>
                    </td>
                </tr>
            </table>

            <h2>キャッシュ設定</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="kslc_external_cache_period">外部リンクのキャッシュ保持期間 (時間)</label></th>
                    <td>
                        <input type="number" id="kslc_external_cache_period" name="kslc_external_cache_period" value="<?php echo esc_attr( get_option( 'kslc_external_cache_period', 6 ) ); ?>" min="1" step="1" required />
                        <p class="description">外部サイトの情報を再度取得するまでの時間です。短めに設定することで外部サイトの変更を早く反映できます。（推奨: 6-24時間）</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="kslc_internal_cache_period">内部リンクのキャッシュ保持期間 (時間)</label></th>
                    <td>
                        <input type="number" id="kslc_internal_cache_period" name="kslc_internal_cache_period" value="<?php echo esc_attr( get_option( 'kslc_internal_cache_period', 72 ) ); ?>" min="1" step="1" required />
                        <p class="description">自サイト内の記事情報のキャッシュ期間です。長めに設定してもデータベースから高速取得されるため問題ありません。（推奨: 48-168時間）</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="kslc_cache_period">共通キャッシュ保持期間 (時間)</label></th>
                    <td>
                        <input type="number" id="kslc_cache_period" name="kslc_cache_period" value="<?php echo esc_attr( get_option( 'kslc_cache_period', 24 ) ); ?>" min="1" step="1" required />
                        <p class="description">上記設定が未設定の場合のフォールバック値です。通常は上記の個別設定を使用してください。</p>
                    </td>
                </tr>
            </table>

            <?php submit_button( '設定を保存' ); ?>
        </form>

        <hr>

        <h2>キャッシュのクリア</h2>
        <p>カードの表示が更新されない場合や、問題が発生した場合は、以下のボタンをクリックして全てのキャッシュを削除してください。</p>
        <form action="" method="post">
            <?php wp_nonce_field( 'kslc_clear_cache_action', 'kslc_clear_cache_nonce' ); ?>
            <?php submit_button( 'キャッシュをすべてクリア', 'delete', 'kslc_clear_cache', false ); ?>
        </form>

        <hr>

        <h2>このプラグインについて</h2>
        <p>このプラグインは、投稿や固定ページにURLを記述するだけで、そのページの情報を自動で取得し、見栄えの良いカード形式で表示するためのものです。</p>
        <p>外部リンクと内部リンクを自動で判別し、それぞれ異なるデザイン設定を適用できます。</p>

        <h2>使い方</h2>
        <p>投稿や固定ページの編集画面で、以下のどちらかのショートコードを使用してください：</p>

        <h3>ショートコード</h3>
        <p><code>[linkcard url="ここに表示したいページのURL"]</code></p>
        <p><code>[nlink url="ここに表示したいページのURL"]</code></p>
        <p><small>※ どちらを使用しても同じ機能です</small></p>

        <h3>使用例</h3>
        <p><code>[linkcard url="https://example.com/"]</code> → 外部リンクとして表示</p>
        <p><code>[nlink url="<?php echo home_url('/about/'); ?>"]</code> → 内部リンクとして表示</p>

        <h3>自動判別機能</h3>
        <p>リンク先が自サイト内かどうかを自動で判別し、上記の設定に応じて適切なデザインで表示されます。</p>

        <h2>サポート</h2>
        <p>ご不明な点や不具合報告は、<a href="https://tsuyoshikashiwazaki.jp/contact/" target="_blank" rel="noopener">作者のサイト</a>までお気軽にお問い合わせください。</p>
    </div>
    <?php
}



// ページタイトルを取得する関数
function kslc_get_page_title($page_url) {
    // URL正規化（#部分削除）
    $normalized_url = kslc_normalize_url($page_url);

    // パラメータも削除
    $clean_url = strtok($normalized_url, '?');

    $parsed_url = parse_url($clean_url);
    if (!$parsed_url) {
        return $page_url;
    }

    // 現在のサイトのホストと比較
    $site_host = parse_url(home_url(), PHP_URL_HOST);
    $page_host = isset($parsed_url['host']) ? $parsed_url['host'] : $site_host;

    $is_internal = ($site_host === $page_host);

    if ($is_internal) {
        // 内部ページの処理（WordPress内）
        $path = isset($parsed_url['path']) ? trim($parsed_url['path'], '/') : '';

        // ホームページの場合
        if (empty($path)) {
            return get_bloginfo('name') . ' - ホーム';
        }

        // 最初にurl_to_postidを試行
        $page_id = url_to_postid($clean_url);
        if ($page_id && $page_id > 0) {
            $title = get_the_title($page_id);
            if (!empty($title)) {
                return $title;
            }
        }

        // パスから直接検索
        global $wpdb;

        // 投稿スラッグから検索
        $post = $wpdb->get_row($wpdb->prepare(
            "SELECT ID, post_title, post_type FROM {$wpdb->posts}
             WHERE post_name = %s AND post_status = 'publish'
             ORDER BY CASE WHEN post_type = 'page' THEN 1 WHEN post_type = 'post' THEN 2 ELSE 3 END",
            basename($path)
        ));

        if ($post) {
            return $post->post_title;
        }

        // カテゴリの場合
        if (strpos($path, 'category/') === 0) {
            $category_slug = str_replace('category/', '', $path);
            $category = get_category_by_slug($category_slug);
            if ($category) {
                return $category->name . ' - カテゴリ';
            }
        }

        // タグの場合
        if (strpos($path, 'tag/') === 0) {
            $tag_slug = str_replace('tag/', '', $path);
            $tag = get_term_by('slug', $tag_slug, 'post_tag');
            if ($tag) {
                return $tag->name . ' - タグ';
            }
        }

        // アーカイブの場合
        if (preg_match('/^(\d{4})\/(\d{2})/', $path, $matches)) {
            $year = $matches[1];
            $month = isset($matches[2]) ? $matches[2] : null;
            if ($month) {
                return $year . '年' . intval($month) . '月のアーカイブ';
            } else {
                return $year . '年のアーカイブ';
            }
        }

        // どれにも該当しない場合はパスをタイトル風に
        $title_parts = explode('/', $path);
        $last_part = end($title_parts);
        $formatted_title = ucwords(str_replace(['-', '_'], ' ', $last_part));

        return $formatted_title ?: $page_url;

    } else {
        // 外部ページの処理（スクレイピング）
        return kslc_get_external_page_title($clean_url);
    }
}

// 外部ページのタイトルをスクレイピングで取得
function kslc_get_external_page_title($url) {
    // デバッグ：スクレイピング無効化オプション
    if (defined('KSLC_DISABLE_SCRAPING') && KSLC_DISABLE_SCRAPING) {
        $parsed = parse_url($url);
        return isset($parsed['host']) ? $parsed['host'] . ' (スクレイピング無効)' : $url;
    }

    try {
        // キャッシュキーを生成
        $cache_key = 'kslc_page_title_' . md5($url);
        $cached_title = get_transient($cache_key);

        if ($cached_title !== false) {
            return $cached_title;
        }

        // デバッグログ（無効化）
        if (defined('WP_DEBUG') && WP_DEBUG && defined('KSLC_ENABLE_DEBUG_LOGS')) {
            error_log('KSLC Debug: Getting external page title for: ' . $url);
            error_log('KSLC Debug: Output buffer level before scraping: ' . ob_get_level());
        }

        // OGP取得機能を流用（安全に実行）
        $title = '';
        if (function_exists('kslc_get_ogp_data')) {
            $ogp_data = kslc_get_ogp_data($url);
            if ($ogp_data && !empty($ogp_data['title'])) {
                $title = $ogp_data['title'];
            }
        }

        // OGPで取得できない場合は直接HTMLを取得
        if (empty($title)) {
            $title = kslc_scrape_title_from_html($url);
        }

        // デバッグログ（無効化）
        if (defined('WP_DEBUG') && WP_DEBUG && defined('KSLC_ENABLE_DEBUG_LOGS')) {
            error_log('KSLC Debug: Output buffer level after scraping: ' . ob_get_level());
            error_log('KSLC Debug: Retrieved title: ' . $title);
        }

        if (empty($title)) {
            // タイトルが取得できない場合はドメイン名を使用
            $parsed = parse_url($url);
            $title = isset($parsed['host']) ? $parsed['host'] : $url;
        }

        // 3時間キャッシュ
        set_transient($cache_key, $title, 3 * HOUR_IN_SECONDS);

        return $title;

    } catch (Exception $e) {
        // エラー時のログ（無効化）
        if (defined('WP_DEBUG') && WP_DEBUG && defined('KSLC_ENABLE_DEBUG_LOGS')) {
            error_log('KSLC Debug: Exception in external page title: ' . $e->getMessage());
        }

        // エラー時はドメイン名をフォールバック
        $parsed = parse_url($url);
        return isset($parsed['host']) ? $parsed['host'] : $url;
    }
}

// HTMLからtitleタグを直接抽出
function kslc_scrape_title_from_html($url) {
    try {
        // Use filtered values from ogp.php for consistency
        $user_agent = apply_filters( 'kslc_request_user_agent', KSLC_USER_AGENT );

        // 出力を抑制して実行
        $response = wp_remote_get($url, [
            'timeout' => 5, // タイムアウトを短縮
            'redirection' => 3, // リダイレクト回数制限
            'user-agent' => $user_agent,
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'ja,en-US;q=0.7,en;q=0.3',
                'Accept-Encoding' => 'gzip, deflate',
                'Connection' => 'keep-alive',
            ],
            'sslverify' => false, // SSL証明書の検証をスキップ（問題がある場合）
        ]);

        if (is_wp_error($response)) {
            return '';
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return '';
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return '';
        }

        $content_type = wp_remote_retrieve_header($response, 'content-type');

        // HTMLでない場合は処理しない
        if (!empty($content_type) && strpos($content_type, 'text/html') === false) {
            return '';
        }

        // titleタグを抽出（より安全な正規表現）
        if (preg_match('/<title[^>]*>(.*?)<\/title>/isu', $body, $matches)) {
            $title = $matches[1];

            // HTMLエンティティをデコード
            $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML401, 'UTF-8');
            $title = trim($title);

            // 改行やタブを削除
            $title = preg_replace('/\s+/', ' ', $title);

            // 空文字の場合は処理しない
            if (empty($title)) {
                return '';
            }

            // 長すぎる場合は切り詰め
            if (mb_strlen($title) > 100) {
                $title = mb_substr($title, 0, 97) . '...';
            }

            return $title;
        }

        return '';

    } catch (Exception $e) {
        // エラー時は空文字を返す
        return '';
    }
}

// リンクカードが設置されているページ一覧を取得
function kslc_get_pages_with_links($period = 'all') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kslc_analytics';

    // 期間に応じた WHERE 句を作成
    $where_clause = '';
    switch ($period) {
        case '1day':
            $where_clause = "WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
            break;
        case '3days':
            $where_clause = "WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)";
            break;
        case '1week':
            $where_clause = "WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            break;
        case '3months':
            $where_clause = "WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
            break;
        case 'all':
        default:
            $where_clause = '';
            break;
    }

    // ページURLも正規化して統合（#とクエリパラメータを削除）
    $pages = $wpdb->get_results("
        SELECT
            CASE
                WHEN page_url LIKE '%#%' THEN SUBSTRING(page_url, 1, LOCATE('#', page_url) - 1)
                ELSE page_url
            END as normalized_page_url,
            COUNT(*) as total_clicks,
            COUNT(DISTINCT url) as unique_links
        FROM $table_name
        $where_clause
        GROUP BY
            CASE
                WHEN page_url LIKE '%#%' THEN SUBSTRING(page_url, 1, LOCATE('#', page_url) - 1)
                ELSE page_url
            END
        ORDER BY total_clicks DESC
    ");

    // ページタイトルを追加
    foreach ($pages as $page) {
        $page->page_url = $page->normalized_page_url; // 正規化URLをpage_urlに設定
        $page->page_title = kslc_get_page_title($page->page_url);
    }

    return $pages;
}

// 特定ページのリンク統計を取得する関数
function kslc_get_page_link_stats($page_url, $period = 'all') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kslc_analytics';

    // ページURLを正規化（#とクエリパラメータを削除）
    $normalized_page_url = kslc_normalize_url($page_url);
    $normalized_page_url = strtok($normalized_page_url, '?');

    // 期間に応じた WHERE 句を作成（正規化されたページURLを使用）
    $where_clause = "WHERE (page_url = %s OR page_url LIKE %s)";
    $params = [$normalized_page_url, $normalized_page_url . '#%'];

    switch ($period) {
        case '1day':
            $where_clause .= " AND clicked_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
            break;
        case '3days':
            $where_clause .= " AND clicked_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)";
            break;
        case '1week':
            $where_clause .= " AND clicked_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            break;
        case '3months':
            $where_clause .= " AND clicked_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
            break;
    }

    // 総クリック数（そのページでの）
    $total_clicks = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name $where_clause",
        $params
    ));

    // リンク別統計（URLは既に正規化されているはず）
    $link_stats = $wpdb->get_results($wpdb->prepare("
        SELECT
            url as normalized_url,
            url as original_url,
            MAX(title) as title,
            COUNT(*) as click_count,
            MAX(clicked_at) as last_clicked
        FROM $table_name
        $where_clause
        GROUP BY url
        ORDER BY click_count DESC
    ", $params));

    // 割合を計算
    foreach ($link_stats as $link) {
        $link->percentage = $total_clicks > 0 ? round(($link->click_count / $total_clicks) * 100, 1) : 0;
    }

    return [
        'total_clicks' => (int) $total_clicks,
        'link_stats' => $link_stats,
        'page_title' => kslc_get_page_title($normalized_page_url)
    ];
}



// 統計ページの表示
function kslc_analytics_page_html() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kslc_analytics';

    // デバッグ: テーブルの存在確認
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
    $total_records = 0;
    if ($table_exists) {
        $total_records = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    }

    // 現在選択されている期間
    $selected_period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : '1week';

    // 選択されたページ
    $selected_page = isset($_GET['selected_page']) ? esc_url_raw($_GET['selected_page']) : '';

    ?>
    <div class="wrap">
        <h1>リンク統計</h1>

        <!-- デバッグ情報 -->
        <div class="notice notice-info" style="padding: 10px; margin: 20px 0;">
            <h4>システム状態</h4>
            <p><strong>データベーステーブル:</strong> <?php echo $table_exists ? '✅ 存在' : '❌ 未作成'; ?></p>
            <p><strong>総レコード数:</strong> <?php echo number_format($total_records); ?> 件</p>
            <p><strong>テーブル名:</strong> <?php echo esc_html($table_name); ?></p>

            <?php
            // JavaScriptファイルの存在確認
            $js_file_path = plugin_dir_path( __DIR__ ) . 'assets/js/analytics.js';
            $js_file_exists = file_exists($js_file_path);
            $js_file_url = plugin_dir_url( __DIR__ ) . 'assets/js/analytics.js';
            ?>
            <p><strong>JavaScriptファイル:</strong> <?php echo $js_file_exists ? '✅ 存在' : '❌ 未作成'; ?></p>
            <?php if (!$table_exists): ?>
                <p style="color: red;"><strong>⚠️ データベーステーブルが存在しません。</strong></p>
                <form method="post" style="display: inline;">
                    <?php wp_nonce_field('kslc_create_table', 'kslc_create_table_nonce'); ?>
                    <input type="hidden" name="kslc_create_table" value="1">
                    <button type="submit" class="button button-primary">テーブルを作成する</button>
                </form>
                <p><small>または、プラグインを一度無効化して再有効化してください。</small></p>
            <?php endif; ?>
        </div>

        <?php
        // テーブル作成処理
        if (isset($_POST['kslc_create_table']) && wp_verify_nonce($_POST['kslc_create_table_nonce'], 'kslc_create_table')) {
            kslc_create_analytics_table();
            echo '<div class="notice notice-success"><p>データベーステーブルの作成を実行しました。ページを更新してください。</p></div>';
            echo '<script>setTimeout(function(){ location.reload(); }, 2000);</script>';
        }
        ?>

        <!-- 期間とページ選択 -->
        <form method="get" style="margin: 20px 0;">
            <input type="hidden" name="page" value="kslc-analytics">

            <label for="period">表示期間:</label>
            <select name="period" id="period" onchange="this.form.submit()">
                <option value="1day" <?php selected($selected_period, '1day'); ?>>過去1日</option>
                <option value="3days" <?php selected($selected_period, '3days'); ?>>過去3日</option>
                <option value="1week" <?php selected($selected_period, '1week'); ?>>過去1週間</option>
                <option value="3months" <?php selected($selected_period, '3months'); ?>>過去3ヶ月</option>
                <option value="all" <?php selected($selected_period, 'all'); ?>>全期間</option>
            </select>

            <?php if ($table_exists && $total_records > 0): ?>
                <?php $pages_with_links = kslc_get_pages_with_links($selected_period); ?>

                <label for="selected_page" style="margin-left: 20px;">ページを選択:</label>
                <select name="selected_page" id="selected_page" onchange="this.form.submit()">
                    <option value="">-- ページを選択してください --</option>
                    <?php foreach ($pages_with_links as $page): ?>
                        <option value="<?php echo esc_attr($page->page_url); ?>" <?php selected($selected_page, $page->page_url); ?>>
                            <?php echo esc_html($page->page_title); ?> (<?php echo $page->total_clicks; ?>クリック)
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </form>

        <?php if (empty($selected_page) && $table_exists && $total_records > 0): ?>
            <!-- ページ選択が未選択の場合：ページ一覧を表示 -->
            <h2>リンクカードが設置されているページ一覧</h2>
            <p>上記のプルダウンからページを選択すると、そのページでクリックされたリンクの詳細統計を確認できます。</p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 60%;">ページタイトル</th>
                        <th style="width: 20%;">総クリック数</th>
                        <th style="width: 20%;">ユニークリンク数</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $pages_with_links = kslc_get_pages_with_links($selected_period); ?>
                    <?php if (!empty($pages_with_links)): ?>
                        <?php foreach ($pages_with_links as $page): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($page->page_title); ?></strong><br>
                                    <small style="color: #666;">
                                        <a href="<?php echo esc_url($page->page_url); ?>" target="_blank" rel="noopener">
                                            <?php echo esc_html($page->page_url); ?>
                                        </a>
                                    </small>
                                </td>
                                <td><strong><?php echo number_format($page->total_clicks); ?></strong></td>
                                <td><strong><?php echo number_format($page->unique_links); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" style="text-align: center; padding: 20px;">
                                選択した期間にクリックデータがありません。
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

        <?php elseif (!empty($selected_page)): ?>
            <!-- 特定ページのリンク統計を表示 -->
            <?php
            $page_stats = kslc_get_page_link_stats($selected_page, $selected_period);
            ?>

            <h2><?php echo esc_html($page_stats['page_title']); ?> - リンク別クリック統計</h2>
            <p>
                <strong>ページURL:</strong>
                <a href="<?php echo esc_url($selected_page); ?>" target="_blank" rel="noopener">
                    <?php echo esc_html($selected_page); ?>
                </a>
            </p>
            <p><strong>総クリック数:</strong> <?php echo number_format($page_stats['total_clicks']); ?> 回</p>

            <a href="<?php echo add_query_arg(['page' => 'kslc-analytics', 'period' => $selected_period], admin_url('admin.php')); ?>" class="button" style="margin: 10px 0;">← ページ一覧に戻る</a>

            <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                <thead>
                    <tr>
                        <th style="width: 40%;">リンクタイトル</th>
                        <th style="width: 30%;">リンクURL</th>
                        <th style="width: 15%;">クリック数</th>
                        <th style="width: 15%;">割合</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($page_stats['link_stats'])): ?>
                        <?php foreach ($page_stats['link_stats'] as $link): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($link->title ?: 'タイトル不明'); ?></strong>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url($link->normalized_url); ?>" target="_blank" rel="noopener">
                                        <?php echo esc_html(mb_strimwidth($link->normalized_url, 0, 50, '...')); ?>
                                    </a>
                                </td>
                                <td>
                                    <strong><?php echo number_format($link->click_count); ?></strong>
                                </td>
                                <td>
                                    <strong><?php echo $link->percentage; ?>%</strong>
                                    <div style="background: #e0e0e0; border-radius: 3px; height: 6px; margin-top: 3px;">
                                        <div style="background: #0073aa; height: 100%; width: <?php echo $link->percentage; ?>%; border-radius: 3px;"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 20px;">
                                このページで選択した期間にクリックデータがありません。
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

        <?php else: ?>
            <!-- データが存在しない場合 -->
            <div class="notice notice-warning" style="padding: 15px; margin: 20px 0;">
                <h3>統計データがありません</h3>
                <p>まだリンクカードがクリックされていないか、データベーステーブルが作成されていません。</p>
                <ol>
                    <li>リンクカードを設置したページでリンクをクリックしてみてください</li>
                    <li>上記のテーブル作成ボタンを押してデータベースを初期化してください</li>
                    <li>ブラウザの開発者ツールでJavaScriptエラーがないか確認してください</li>
                </ol>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
