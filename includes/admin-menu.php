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
    // サブメニューは作らない。リンク統計・リンク切れ一覧も含め、すべて同じページのタブで切り替える
}
add_action( 'admin_menu', 'kslc_add_admin_menu' );

/**
 * 管理画面のタブ定義（slug => ラベル）。すべて admin.php?page=kashiwazaki-seo-link-card&tab=... で切り替える
 */
function kslc_admin_tabs() {
    return [
        'design' => 'デザイン',
        'cache'  => 'キャッシュ',
        'links'  => 'リンク先の扱い',
        'broken' => 'リンク切れ一覧',
        'stats'  => 'リンク統計',
        'usage'  => '使い方',
    ];
}

function kslc_admin_tab_url( $tab ) {
    return add_query_arg( [ 'page' => 'kashiwazaki-seo-link-card', 'tab' => $tab ], admin_url( 'admin.php' ) );
}

function kslc_current_admin_tab() {
    $tabs = kslc_admin_tabs();
    $tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'design';
    return isset( $tabs[ $tab ] ) ? $tab : 'design';
}

function kslc_add_settings_link( $links ) {
    $settings_link = '<a href="admin.php?page=kashiwazaki-seo-link-card">' . __( 'Settings' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}

// 定数が定義されている場合のみフィルターを追加
if ( defined( 'KSLC_PLUGIN_FILE' ) ) {
    add_filter( 'plugin_action_links_' . plugin_basename( KSLC_PLUGIN_FILE ), 'kslc_add_settings_link' );
}


/**
 * 管理画面本体: 見出し + タブナビ + 選択中タブの内容
 * 設定フォームはタブごとに options.php へ送る。options.php は送られたグループの全オプションを更新する
 * （POST に無いものは null → 既定値）ため、設定グループもタブごとに分けている（kslc_design_group / kslc_cache_group / kslc_links_group）
 */
function kslc_options_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $tabs        = kslc_admin_tabs();
    $current_tab = kslc_current_admin_tab();

    // キャッシュクリア処理（「キャッシュ」タブのフォームから POST される）
    if ( isset( $_POST['kslc_clear_cache'] ) &&
         check_admin_referer( 'kslc_clear_cache_action', 'kslc_clear_cache_nonce' ) ) {
        $deleted_count = kslc_clear_cache();

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
        <nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e( 'Secondary menu' ); ?>">
            <?php foreach ( $tabs as $slug => $label ) : ?>
                <a href="<?php echo esc_url( kslc_admin_tab_url( $slug ) ); ?>" class="nav-tab<?php echo $slug === $current_tab ? ' nav-tab-active' : ''; ?>"<?php echo $slug === $current_tab ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
            <?php endforeach; ?>
        </nav>
        <?php
        // options.php 経由の保存結果（settings-updated）と、このページ独自のメッセージをまとめて表示
        settings_errors();

        switch ( $current_tab ) {
            case 'cache':
                kslc_render_tab_cache();
                break;
            case 'links':
                kslc_render_tab_links();
                break;
            case 'broken':
                kslc_render_tab_broken_links();
                break;
            case 'stats':
                kslc_render_tab_analytics();
                break;
            case 'usage':
                kslc_render_tab_usage();
                break;
            default:
                kslc_render_tab_design();
                break;
        }
        ?>
    </div>
    <?php
}

/**
 * 「デザイン」タブ: 外部／内部リンクのデザインとサムネイルサイズ
 */
function kslc_render_tab_design() {
    ?>
        <form action="options.php" method="post">
            <?php settings_fields( 'kslc_design_group' ); ?>
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

            <?php submit_button( '設定を保存' ); ?>
        </form>
    <?php
}

/**
 * 「キャッシュ」タブ: キャッシュ期間とキャッシュのクリア
 */
function kslc_render_tab_cache() {
    ?>
        <form action="options.php" method="post">
            <?php settings_fields( 'kslc_cache_group' ); ?>
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
        <form action="<?php echo esc_url( kslc_admin_tab_url( 'cache' ) ); ?>" method="post">
            <?php wp_nonce_field( 'kslc_clear_cache_action', 'kslc_clear_cache_nonce' ); ?>
            <?php submit_button( 'キャッシュをすべてクリア', 'delete', 'kslc_clear_cache', false ); ?>
        </form>
    <?php
}

/**
 * 「リンク先の扱い」タブ: 転送先への自動追随・rel 自動付与・リンク切れの定期チェック
 */
function kslc_render_tab_links() {
    ?>
        <form action="options.php" method="post">
            <?php settings_fields( 'kslc_links_group' ); ?>
            <h2>リンク先の扱い</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">転送先への自動追随</th>
                    <td>
                        <label>
                            <input type="checkbox" name="kslc_follow_redirects" value="1" <?php checked( get_option( 'kslc_follow_redirects', true ) ); ?> />
                            リンク先が恒久的に移転（301 / 308）していたら、リダイレクト後の最終 URL をカードのリンク先に使う
                        </label>
                        <p class="description">ショートコードに書いた URL は変更されず、出力時にだけ差し替えます。302 / 307 などの一時的な転送（アフィリエイトの計測 URL・短縮 URL・同意画面など）には追随せず、元の URL のままにします。転送後の URL にも、ショートコードの URL の <code>#見出し</code> を引き継ぎます。内部リンクは投稿の正規のパーマリンク（末尾スラッシュや http/https の揺れを正規化したもの）を使い、<code>#見出し</code> や <code>?パラメータ</code> はそのまま保ちます。追随は最大 <?php echo (int) KSLC_MAX_REDIRECTS; ?> 回で、ループはそこで打ち切られます。</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="kslc_auto_rel_domains">rel を自動付与するドメイン</label></th>
                    <td>
                        <textarea id="kslc_auto_rel_domains" name="kslc_auto_rel_domains" rows="4" class="large-text code" placeholder="example.com&#10;shop.example.net"><?php echo esc_textarea( get_option( 'kslc_auto_rel_domains', '' ) ); ?></textarea>
                        <p class="description">1 行に 1 ドメイン（例: <code>example.com</code>）。サブドメイン（<code>sub.example.com</code>）にも一致します。登録ドメインへのカードには下の rel 値を自動で付けます。ショートコードに <code>rel</code> が指定されていればそちらを優先します。</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="kslc_auto_rel_value">自動付与する rel の値</label></th>
                    <td>
                        <select id="kslc_auto_rel_value" name="kslc_auto_rel_value">
                            <?php
                            $rel_choices = [ 'sponsored' => 'sponsored（広告・有料リンク）', 'nofollow' => 'nofollow' ];
                            $current_rel = get_option( 'kslc_auto_rel_value', 'sponsored' );
                            foreach ( $rel_choices as $value => $label ) {
                                echo '<option value="' . esc_attr( $value ) . '"' . selected( $current_rel, $value, false ) . '>' . esc_html( $label ) . '</option>';
                            }
                            ?>
                        </select>
                        <p class="description">Google の推奨: 広告や有料掲載のリンクには <code>sponsored</code>、それ以外で関連付けたくないリンクには <code>nofollow</code>。</p>
                    </td>
                </tr>
            </table>

            <h2>リンク切れの検知</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">定期チェック</th>
                    <td>
                        <label>
                            <input type="checkbox" name="kslc_link_check_enabled" value="1" <?php checked( get_option( 'kslc_link_check_enabled', true ) ); ?> />
                            WP-Cron でリンクカードのリンク先を定期的に確認する
                        </label>
                        <p class="description">公開済み投稿にあるリンクカードの URL を集めて確認し、404 / 410 / サーバーエラー / 接続失敗を「リンク切れ一覧」に載せます。カード表示時に検知した分も同じ一覧に載ります。</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="kslc_link_check_interval">チェック間隔 (時間)</label></th>
                    <td>
                        <input type="number" id="kslc_link_check_interval" name="kslc_link_check_interval" value="<?php echo esc_attr( get_option( 'kslc_link_check_interval', KSLC_DEFAULT_LINK_CHECK_INTERVAL ) ); ?>" min="<?php echo (int) KSLC_LINK_CHECK_INTERVAL_MIN; ?>" max="<?php echo (int) KSLC_LINK_CHECK_INTERVAL_MAX; ?>" step="1" required />
                        <p class="description">
                            既定は 24 時間（1 日 1 回）。
                            <?php
                            $next_check = wp_next_scheduled( KSLC_LINK_CHECK_HOOK );
                            if ( $next_check ) {
                                echo '次回の実行予定: ' . esc_html( wp_date( 'Y-m-d H:i', $next_check ) );
                            } else {
                                echo '現在、定期チェックは登録されていません。';
                            }
                            if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
                                echo '<br>※ DISABLE_WP_CRON が有効なため、サーバーの cron から wp-cron.php を実行する必要があります。';
                            }
                            ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button( '設定を保存' ); ?>
        </form>
    <?php
}

/**
 * 「使い方」タブ
 */
function kslc_render_tab_usage() {
    ?>
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

        <h2>文中の文字リンク</h2>
        <p>カードではなく、文章の途中に普通の文字リンクを置くときは <code>[linktext]</code> を使います。見た目は文字リンクのまま、転送先への自動追随・rel の自動付与・リンク切れの検知・クリック計測がカードと同じく効きます。</p>
        <p><code>[linktext url="https://example.com/" text="詳細はこちら"]</code> → 「詳細はこちら」という文字リンク</p>
        <p><code>[linktext url="https://example.com/"]</code> → リンク文字はリンク先のタイトル（内部リンクは記事タイトル）</p>
        <p><code>[linktext post_id="123" text="こちらの記事"]</code> → 投稿 ID で内部リンク</p>
        <p><small>※ 閉じタグ（<code>[/linktext]</code>）は使いません。<code>target</code> と <code>rel</code> もカードと同じく指定できます。ブロックエディターでは段落のツールバーの ▼ にある「SEO文字リンク」から、普通のリンクと同じ感覚で入れられます（リンクの中をクリックすると URL などを直せます）。</small></p>

        <h2>サポート</h2>
        <p>ご不明な点や不具合報告は、<a href="https://tsuyoshikashiwazaki.jp/contact/" target="_blank" rel="noopener">作者のサイト</a>までお気軽にお問い合わせください。</p>
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
        // 外部URLはこのサイトのページではあり得ない（正規の計測では発生しない）。取得に行かずホスト名だけ返す
        return $page_host;
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
            COUNT(DISTINCT url, link_type, link_pos) as unique_links
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

    // ページURLを正規化（# 以降だけを削除）。ページ一覧は ?query を含む page_url ごとに 1 行なので、詳細も同じ単位で集計する
    // （? 以降まで削ると、パラメータ付きで開かれたページを選んだときに別の行（パラメータ無しのページ）の数字が出る）
    $normalized_page_url = kslc_normalize_url($page_url);

    // 期間に応じた WHERE 句を作成（正規化されたページURLを使用）
    $where_clause = "WHERE (page_url = %s OR page_url LIKE %s)";
    // LIKE の % と _ は URL に含まれ得る（utm_source など）ので esc_like でそのままの文字として比べる
    $params = [$normalized_page_url, $wpdb->esc_like( $normalized_page_url ) . '#%'];

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

    // リンク別統計: リンク先 URL・種類（カード / 文字リンク）・ページ内の位置の組ごとに数える
    // （同じ URL のカードと文字リンクがあっても別の行になる。種類と位置を記録する前のクリックは「不明」として 1 行にまとまる）
    $link_stats = $wpdb->get_results($wpdb->prepare("
        SELECT
            url as normalized_url,
            url as original_url,
            link_type,
            link_pos,
            MAX(title) as title,
            COUNT(*) as click_count,
            MAX(clicked_at) as last_clicked
        FROM $table_name
        $where_clause
        GROUP BY url, link_type, link_pos
        ORDER BY click_count DESC, link_pos ASC
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
/**
 * 「リンク統計」タブ
 */
function kslc_render_tab_analytics() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kslc_analytics';

    // デバッグ: テーブルの存在確認
    $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
    $total_records = 0;
    if ($table_exists) {
        $total_records = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    }

    // 現在選択されている期間
    $selected_period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : '1week';

    // 選択されたページ
    $selected_page = isset($_GET['selected_page']) ? esc_url_raw($_GET['selected_page']) : '';

    ?>
        <h2>リンク統計</h2>

        <!-- デバッグ情報 -->
        <div class="notice notice-info inline" style="padding: 10px; margin: 20px 0;">
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
        if (isset($_POST['kslc_create_table']) && current_user_can('manage_options') && isset($_POST['kslc_create_table_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['kslc_create_table_nonce'])), 'kslc_create_table')) {
            kslc_create_analytics_table();
            echo '<div class="notice notice-success"><p>データベーステーブルの作成を実行しました。ページを更新してください。</p></div>';
            echo '<script>setTimeout(function(){ location.reload(); }, 2000);</script>';
        }
        ?>

        <!-- 期間とページ選択 -->
        <form method="get" style="margin: 20px 0;">
            <input type="hidden" name="page" value="kashiwazaki-seo-link-card">
            <input type="hidden" name="tab" value="stats">

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
                            <?php echo esc_html($page->page_title); ?> (<?php echo (int) $page->total_clicks; ?>クリック)
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </form>

        <?php if (empty($selected_page) && $table_exists && $total_records > 0): ?>
            <!-- ページ選択が未選択の場合：ページ一覧を表示 -->
            <h2>リンクカードが設置されているページ一覧</h2>
            <p>ページ名をクリックする（または上のプルダウンで選ぶ）と、そのページのリンク 1 つずつのクリック数を確認できます。</p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 60%;">ページタイトル</th>
                        <th style="width: 20%;">総クリック数</th>
                        <th style="width: 20%;">クリックされたリンク数</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $pages_with_links = kslc_get_pages_with_links($selected_period); ?>
                    <?php if (!empty($pages_with_links)): ?>
                        <?php foreach ($pages_with_links as $page): ?>
                            <tr>
                                <td>
                                    <strong><a href="<?php echo esc_url( add_query_arg( [ 'page' => 'kashiwazaki-seo-link-card', 'tab' => 'stats', 'period' => rawurlencode( $selected_period ), 'selected_page' => rawurlencode( $page->page_url ) ], admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html($page->page_title); ?></a></strong><br>
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
            <p class="description">「位置」はページの上から数えて何番目のリンクカード・文字リンクか（カードと文字リンクを通しで数えます）。記事を書き換えて並びが変わると番号も変わります。「不明」はこの記録を始める前のクリックです。</p>

            <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'kashiwazaki-seo-link-card', 'tab' => 'stats', 'period' => $selected_period ], admin_url( 'admin.php' ) ) ); ?>" class="button" style="margin: 10px 0;">← ページ一覧に戻る</a>

            <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                <thead>
                    <tr>
                        <th style="width: 9%;">位置</th>
                        <th style="width: 11%;">種類</th>
                        <th style="width: 28%;">リンク文字</th>
                        <th style="width: 28%;">リンクURL</th>
                        <th style="width: 10%;">クリック数</th>
                        <th style="width: 14%;">割合</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($page_stats['link_stats'])): ?>
                        <?php foreach ($page_stats['link_stats'] as $link): ?>
                            <tr>
                                <td>
                                    <?php echo (int) $link->link_pos > 0 ? esc_html( sprintf( '%d 番目', (int) $link->link_pos ) ) : '<span style="color:#888;">不明</span>'; ?>
                                </td>
                                <td>
                                    <?php
                                    $kslc_type_labels = array( 'card' => 'カード', 'text' => '文字リンク' );
                                    echo isset( $kslc_type_labels[ $link->link_type ] ) ? esc_html( $kslc_type_labels[ $link->link_type ] ) : '<span style="color:#888;">不明</span>';
                                    ?>
                                </td>
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
                                    <strong><?php echo esc_html( $link->percentage ); ?>%</strong>
                                    <div style="background: #e0e0e0; border-radius: 3px; height: 6px; margin-top: 3px;">
                                        <div style="background: #0073aa; height: 100%; width: <?php echo (float) $link->percentage; ?>%; border-radius: 3px;"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 20px;">
                                このページで選択した期間にクリックデータがありません。
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

        <?php else: ?>
            <!-- データが存在しない場合 -->
            <div class="notice notice-warning inline" style="padding: 15px; margin: 20px 0;">
                <h3>統計データがありません</h3>
                <p>まだリンクカードがクリックされていないか、データベーステーブルが作成されていません。</p>
                <ol>
                    <li>リンクカードを設置したページでリンクをクリックしてみてください</li>
                    <li>上記のテーブル作成ボタンを押してデータベースを初期化してください</li>
                    <li>ブラウザの開発者ツールでJavaScriptエラーがないか確認してください</li>
                </ol>
            </div>
        <?php endif; ?>
    <?php
}

/**
 * 「リンク切れ一覧」タブ
 */
function kslc_render_tab_broken_links() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // 今すぐチェック（時間予算内で確認し、残りは WP-Cron の単発イベントで続ける）
    if ( isset( $_POST['kslc_run_link_check'] ) && check_admin_referer( 'kslc_link_check_action', 'kslc_link_check_nonce' ) ) {
        $result = kslc_run_link_check( true );
        if ( 'locked' === $result['skipped'] ) {
            add_settings_error( 'kslc_link_messages', 'kslc_link_check_locked', '別のチェックが実行中です。しばらくしてから再度お試しください。', 'warning' );
        } else {
            $message = sprintf( '%d 件中 %d 件を確認しました。リンク切れ: %d 件。', $result['total'], $result['checked'], $result['broken'] );
            if ( $result['remaining'] > 0 ) {
                $message .= sprintf( ' 残り %d 件は 1 分後からバックグラウンドで続けて確認します。', $result['remaining'] );
            }
            add_settings_error( 'kslc_link_messages', 'kslc_link_check_done', $message, 'updated' );
        }
    }

    // 一覧のクリア
    if ( isset( $_POST['kslc_clear_broken_links'] ) && check_admin_referer( 'kslc_clear_broken_links_action', 'kslc_clear_broken_links_nonce' ) ) {
        delete_option( KSLC_BROKEN_LINKS_OPTION );
        add_settings_error( 'kslc_link_messages', 'kslc_broken_links_cleared', 'リンク切れ一覧をクリアしました。次回のチェックまたはカード表示時に再検知されます。', 'updated' );
    }

    $links = kslc_get_broken_links();
    uasort( $links, function ( $a, $b ) {
        return strcmp( (string) $b['last_checked'], (string) $a['last_checked'] );
    } );

    $last_run = get_option( KSLC_LINK_CHECK_LAST_RUN_OPTION, false );
    $next_run = wp_next_scheduled( KSLC_LINK_CHECK_HOOK );
    $state    = get_option( KSLC_LINK_CHECK_STATE_OPTION, false );
    ?>
        <h2>リンク切れ一覧</h2>
        <?php settings_errors( 'kslc_link_messages' ); ?>

        <?php if ( kslc_is_link_doctor_active() ) : ?>
            <div class="notice notice-info inline">
                <p><strong>Kashiwazaki SEO Link Doctor が有効です。</strong> ページ内のすべてのリンクを対象にした検査は Link Doctor で行えます。この一覧はリンクカードのリンク先だけを対象にした自動検知（カード表示時と定期チェック）です。</p>
            </div>
        <?php endif; ?>

        <div class="notice notice-info inline" style="padding: 10px; margin: 20px 0;">
            <p><strong>定期チェック:</strong>
                <?php echo get_option( 'kslc_link_check_enabled', true ) ? '有効（' . (int) kslc_link_check_interval_hours() . ' 時間ごと）' : '無効'; ?>
                <?php if ( $next_run ) : ?>
                    ／ 次回の実行予定: <?php echo esc_html( wp_date( 'Y-m-d H:i', $next_run ) ); ?>
                <?php endif; ?>
            </p>
            <p><strong>前回の完了:</strong>
                <?php if ( is_array( $last_run ) && ! empty( $last_run['time'] ) ) : ?>
                    <?php echo esc_html( wp_date( 'Y-m-d H:i', (int) $last_run['time'] ) ); ?>（<?php echo (int) $last_run['total']; ?> 件を確認、リンク切れ <?php echo (int) $last_run['broken']; ?> 件<?php if ( ! empty( $last_run['pruned'] ) ) : ?>、本文に無くなったカードの記録 <?php echo (int) $last_run['pruned']; ?> 件を一覧から外しました<?php endif; ?>）
                <?php else : ?>
                    まだ実行されていません
                <?php endif; ?>
                <?php if ( is_array( $state ) && ! empty( $state['queue'] ) ) : ?>
                    ／ 実行中: <?php echo (int) $state['cursor']; ?> / <?php echo count( $state['queue'] ); ?> 件
                <?php endif; ?>
            </p>
            <p>対象: 公開済み投稿の本文にあるリンクカード（<code>[linkcard]</code> / <code>[nlink]</code> / <code>[kashiwazaki_seo_link_card]</code>）と文字リンク（<code>[linktext]</code> / <code>[kashiwazaki_seo_link_text]</code> / ブロックエディターの「SEO文字リンク」）のリンク先。記録するのは 404 / 410 / 5xx / 接続失敗です（401 / 403 / 429 はボット対策で返ることが多いため記録しません）。<code>post_id</code> 指定のカードは <code>?p=ID</code> 形式で表示します。復旧したリンクと本文から消したカードの記録は、全件のチェックが完了したときに一覧から外れます。</p>
        </div>

        <form method="post" style="display: inline-block; margin-right: 10px;">
            <?php wp_nonce_field( 'kslc_link_check_action', 'kslc_link_check_nonce' ); ?>
            <?php submit_button( '今すぐチェック', 'primary', 'kslc_run_link_check', false ); ?>
        </form>
        <form method="post" style="display: inline-block;" onsubmit="return confirm('リンク切れ一覧をクリアします。よろしいですか？');">
            <?php wp_nonce_field( 'kslc_clear_broken_links_action', 'kslc_clear_broken_links_nonce' ); ?>
            <?php submit_button( '一覧をクリア', 'delete', 'kslc_clear_broken_links', false ); ?>
        </form>

        <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
            <thead>
                <tr>
                    <th style="width: 34%;">リンク先 URL</th>
                    <th style="width: 30%;">掲載ページ</th>
                    <th style="width: 18%;">ステータス</th>
                    <th style="width: 18%;">最終確認日時</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $links ) ) : ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 20px;">リンク切れは記録されていません。</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $links as $entry ) : ?>
                        <tr>
                            <td style="word-break: break-all;">
                                <a href="<?php echo esc_url( $entry['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $entry['url'] ); ?></a>
                            </td>
                            <td>
                                <?php if ( empty( $entry['pages'] ) ) : ?>
                                    <span style="color: #666;">不明（カード表示時に検知）</span>
                                <?php else : ?>
                                    <?php foreach ( $entry['pages'] as $page_id ) : ?>
                                        <?php
                                        $page_id   = (int) $page_id;
                                        $page_link = get_permalink( $page_id );
                                        $page_name = get_the_title( $page_id );
                                        ?>
                                        <div>
                                            <?php if ( $page_link ) : ?>
                                                <a href="<?php echo esc_url( $page_link ); ?>" target="_blank" rel="noopener"><?php echo esc_html( '' !== $page_name ? $page_name : '(ID ' . $page_id . ')' ); ?></a>
                                                <a href="<?php echo esc_url( get_edit_post_link( $page_id ) ); ?>" style="margin-left: 4px;">[編集]</a>
                                            <?php else : ?>
                                                <span style="color: #666;">(ID <?php echo $page_id; ?>)</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo esc_html( kslc_link_status_label( $entry['status'], $entry['error'] ) ); ?></strong>
                                <?php if ( (int) $entry['count'] > 1 ) : ?>
                                    <br><small style="color: #666;"><?php echo (int) $entry['count']; ?> 回検知</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html( $entry['last_checked'] ); ?>
                                <br><small style="color: #666;">初回: <?php echo esc_html( $entry['first_seen'] ); ?></small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    <?php
}
