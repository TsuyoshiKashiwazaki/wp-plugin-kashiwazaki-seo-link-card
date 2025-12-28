<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function kslc_get_internal_post_data( $url ) {
    // URLから投稿IDを取得
    $post_id = url_to_postid( $url );

    if ( ! $post_id ) {
        return false; // 投稿が見つからない場合
    }

    return kslc_get_internal_post_data_by_id( $post_id );
}

function kslc_get_internal_post_data_by_id( $post_id ) {
    if ( ! $post_id ) {
        return false;
    }

    $post = get_post( $post_id );

    if ( ! $post || $post->post_status !== 'publish' ) {
        return false; // 投稿が存在しないか公開されていない場合
    }

    $ogp_data = array();

    // タイトルを取得
    $ogp_data['title'] = $post->post_title;

    // 説明文を取得（優先順位：SEOプラグインのmeta description → 抜粋 → 本文）
    // Yoast SEOのmeta descriptionをチェック
    $yoast_desc = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
    
    // All in One SEOのmeta descriptionをチェック
    $aioseo_desc = get_post_meta( $post_id, '_aioseo_description', true );
    
    // SEOプラグインのmeta descriptionがあればそれを使用
    if ( ! empty( $yoast_desc ) ) {
        $ogp_data['description'] = $yoast_desc;
    } elseif ( ! empty( $aioseo_desc ) ) {
        $ogp_data['description'] = $aioseo_desc;
    } elseif ( ! empty( $post->post_excerpt ) ) {
        // 抜粋がある場合は抜粋を使用
        $ogp_data['description'] = $post->post_excerpt;
    } else {
        // 本文から自動生成（ショートコードを除去）
        $content = $post->post_content;
        
        // ショートコードを除去
        $content = strip_shortcodes( $content );
        
        // HTMLタグを除去
        $content = strip_tags( $content );
        
        // 複数の空白・改行を1つのスペースに
        $content = preg_replace( '/\s+/', ' ', $content );
        
        // 前後の空白を削除
        $content = trim( $content );
        
        // 最初の160文字を取得
        if ( mb_strlen( $content ) > 160 ) {
            $ogp_data['description'] = mb_substr( $content, 0, 160 ) . '...';
        } else {
            $ogp_data['description'] = $content;
        }
    }

    // アイキャッチ画像を取得
    $thumbnail_id = get_post_thumbnail_id( $post_id );
    if ( $thumbnail_id ) {
        $image_url = wp_get_attachment_image_url( $thumbnail_id, 'large' );
        $ogp_data['image'] = $image_url;
    } else {
        $ogp_data['image'] = '';
    }

    // サイト名を取得
    $ogp_data['site_name'] = get_bloginfo( 'name' );

    return $ogp_data;
}

/**
 * HTMLからエンコーディングを検出する
 *
 * @param string $html HTML文字列
 * @param array $response wp_remote_getのレスポンス
 * @return string 検出されたエンコーディング（デフォルト: UTF-8）
 */
function kslc_detect_encoding( $html, $response = null ) {
    // 1. HTTPヘッダーからcharsetを取得
    if ( $response ) {
        $content_type = wp_remote_retrieve_header( $response, 'content-type' );
        if ( preg_match( '/charset=([^\s;]+)/i', $content_type, $matches ) ) {
            return strtoupper( trim( $matches[1] ) );
        }
    }

    // 2. XML宣言からエンコーディングを取得
    if ( preg_match( '/<\?xml[^>]+encoding=["\']([^"\']+)["\']/i', $html, $matches ) ) {
        return strtoupper( trim( $matches[1] ) );
    }

    // 3. meta http-equivからcharsetを取得
    if ( preg_match( '/<meta[^>]+http-equiv=["\']?Content-Type["\']?[^>]+charset=([^\s"\';>]+)/i', $html, $matches ) ) {
        return strtoupper( trim( $matches[1] ) );
    }

    // 4. HTML5形式のmeta charsetを取得
    if ( preg_match( '/<meta[^>]+charset=["\']?([^\s"\';>]+)/i', $html, $matches ) ) {
        return strtoupper( trim( $matches[1] ) );
    }

    // 5. mb_detect_encodingでフォールバック
    $detected = mb_detect_encoding( $html, ['UTF-8', 'SJIS', 'EUC-JP', 'ISO-2022-JP', 'ISO-8859-1'], true );
    if ( $detected ) {
        return strtoupper( $detected );
    }

    return 'UTF-8';
}

/**
 * HTMLをUTF-8に変換する
 *
 * @param string $html HTML文字列
 * @param string $encoding 元のエンコーディング
 * @return string UTF-8に変換されたHTML
 */
function kslc_convert_to_utf8( $html, $encoding ) {
    $encoding = strtoupper( $encoding );

    // Shift_JISの別名を統一
    if ( in_array( $encoding, ['SHIFT_JIS', 'SHIFT-JIS', 'SJIS', 'SJIS-WIN', 'CP932', 'MS932'] ) ) {
        $encoding = 'SJIS-win';
    }

    // EUC-JPの別名を統一
    if ( in_array( $encoding, ['EUC-JP', 'EUCJP', 'EUC_JP'] ) ) {
        $encoding = 'EUC-JP';
    }

    // ISO-2022-JPの別名を統一
    if ( in_array( $encoding, ['ISO-2022-JP', 'ISO2022JP', 'JIS', 'CSISO2022JP'] ) ) {
        $encoding = 'ISO-2022-JP';
    }

    // 既にUTF-8の場合はそのまま返す
    if ( $encoding === 'UTF-8' ) {
        return $html;
    }

    // mb_convert_encodingで変換
    $converted = mb_convert_encoding( $html, 'UTF-8', $encoding );
    if ( $converted !== false ) {
        return $converted;
    }

    return $html;
}

function kslc_get_ogp_data( $url, $post_id = 0 ) {
    $transient_key = 'kslc_ogp_data_' . md5( $url );
    $cached_data = get_transient( $transient_key );

    if ( false !== $cached_data ) {
        return $cached_data;
    }

    // post_idが直接指定されている場合は、それを使用して内部データを取得
    if ( $post_id > 0 ) {
        $internal_data = kslc_get_internal_post_data_by_id( $post_id );
        if ( $internal_data ) {
            $cache_period_hours = get_option( 'kslc_internal_cache_period', get_option( 'kslc_cache_period', 72 ) );
            set_transient( $transient_key, $internal_data, $cache_period_hours * HOUR_IN_SECONDS );
            return $internal_data;
        }
    }

    // 内部リンクかどうかをチェック
    $site_host = parse_url( home_url(), PHP_URL_HOST );
    $link_host = parse_url( $url, PHP_URL_HOST );

    // parse_url()が失敗した場合は外部リンクとして扱う
    if ( $site_host === false || $link_host === false ) {
        $is_internal = false;
    } else {
        $is_internal = $site_host === $link_host;
    }

    // 内部リンクの場合は、まずWordPressのデータベースから取得を試行
    if ( $is_internal ) {
        $internal_data = kslc_get_internal_post_data( $url );
        if ( $internal_data ) {
            // 内部リンク用のキャッシュ期間を使用
            $cache_period_hours = get_option( 'kslc_internal_cache_period', get_option( 'kslc_cache_period', 72 ) );
            set_transient( $transient_key, $internal_data, $cache_period_hours * HOUR_IN_SECONDS );
            return $internal_data;
        }
        // データベースから取得できない場合は、スクレイピングにフォールバック
    }

    // User-Agent and headers can be filtered
    $user_agent = apply_filters( 'kslc_request_user_agent', KSLC_USER_AGENT );
    $timeout = apply_filters( 'kslc_request_timeout', 15 );
    $headers = apply_filters( 'kslc_request_headers', array(
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language' => 'ja,en-US;q=0.9,en;q=0.8',
    ) );

    $response = wp_remote_get( $url, array(
        'timeout' => $timeout,
        'user-agent' => $user_agent,
        'headers' => $headers
    ) );

    if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
        return false;
    }

    $html = wp_remote_retrieve_body( $response );
    if ( empty( $html ) ) {
        return false;
    }

    // エンコーディングを検出してUTF-8に変換
    $encoding = kslc_detect_encoding( $html, $response );
    $html = kslc_convert_to_utf8( $html, $encoding );

    $dom = new DOMDocument();
    @$dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) );
    $xpath = new DOMXPath( $dom );

    $ogp_data = [];
    // まずmeta descriptionを取得
    $meta_description = $xpath->query('//meta[@name="description"]/@content');
    $ogp_data['description'] = $meta_description->length > 0 ? $meta_description->item(0)->nodeValue : '';
    
    // OGPタグを取得
    $ogp_tags = [
        'title'       => $xpath->query( '//meta[@property="og:title"]/@content' ),
        'description' => $xpath->query( '//meta[@property="og:description"]/@content' ),
        'image'       => $xpath->query( '//meta[@property="og:image"]/@content' ),
        'site_name'   => $xpath->query( '//meta[@property="og:site_name"]/@content' ),
    ];

    foreach ( $ogp_tags as $key => $tag ) {
        $value = $tag->length > 0 ? $tag->item( 0 )->nodeValue : '';
        // descriptionの場合は、すでにmeta descriptionがあればOGPで上書きしない
        if ( $key === 'description' ) {
            if ( empty( $ogp_data['description'] ) && ! empty( $value ) ) {
                $ogp_data[ $key ] = $value;
            }
        } elseif ( $key === 'image' ) {
             $ogp_data[ $key ] = kslc_relative_to_absolute_url( $value, $url );
        } else {
            $ogp_data[ $key ] = $value;
        }
    }

    // OGP画像が取得できた場合、有効性をチェック（200以外ならフォールバックへ）
    if ( ! empty( $ogp_data['image'] ) && ! kslc_is_image_url_valid( $ogp_data['image'] ) ) {
        // 無効なURLはクリアしてフォールバック処理に移行
        $ogp_data['image'] = '';
    }

    if ( empty( $ogp_data['image'] ) ) {
        $fallback_result = kslc_find_fallback_image( $xpath, $dom, $url );
        $ogp_data['image'] = $fallback_result['image'];
    }

    if ( empty( $ogp_data['title'] ) ) {
        $title_node = $xpath->query('//title');
        if ($title_node->length > 0) {
            $ogp_data['title'] = $title_node->item(0)->nodeValue;
        }
    }

    // descriptionが空の場合、本文から取得を試みる
    if ( empty( $ogp_data['description'] ) ) {
        // 本文の最初の段落やテキストを探す
        $paragraphs = $xpath->query('//article//p | //main//p | //div[@class="content"]//p | //div[@class="entry-content"]//p');
        if ( $paragraphs->length > 0 ) {
            $text_content = '';
            for ( $i = 0; $i < min(3, $paragraphs->length); $i++ ) {
                $paragraph_text = $paragraphs->item($i)->textContent;
                // ショートコードのパターンを除去（[...]形式）
                $paragraph_text = preg_replace('/\[[^\]]*\]/', '', $paragraph_text);
                $text_content .= $paragraph_text . ' ';
            }
            $text_content = preg_replace( '/\s+/', ' ', trim($text_content) );
            if ( mb_strlen( $text_content ) > 160 ) {
                $ogp_data['description'] = mb_substr( $text_content, 0, 160 ) . '...';
            } else {
                $ogp_data['description'] = $text_content;
            }
        }
    }


    // 外部リンク用のキャッシュ期間を使用
    $cache_period_hours = get_option( 'kslc_external_cache_period', get_option( 'kslc_cache_period', 6 ) );
    set_transient( $transient_key, $ogp_data, $cache_period_hours * HOUR_IN_SECONDS );

    return $ogp_data;
}

function kslc_find_fallback_image( $xpath, $dom, $base_url ) {

    // 1. 他のmetaタグから画像を探す
    $meta_image_queries = [
        '//meta[@property="twitter:image"]/@content',
        '//meta[@name="twitter:image"]/@content',
        '//meta[@itemprop="image"]/@content',
        '//meta[@name="msapplication-TileImage"]/@content'
    ];

    foreach ($meta_image_queries as $query) {
        $image_node = $xpath->query($query);
        if ($image_node->length > 0) {
            $image_url = $image_node->item(0)->nodeValue;
            if (!empty($image_url)) {
                $absolute_url = kslc_relative_to_absolute_url($image_url, $base_url);
                if (filter_var($absolute_url, FILTER_VALIDATE_URL)) {
                    return ['image' => $absolute_url];
                }
            }
        }
    }

    // 2. favicon系を探す
    $favicon_queries = [
        '//link[@rel="icon"]/@href',
        '//link[@rel="shortcut icon"]/@href',
        '//link[@rel="apple-touch-icon"]/@href',
        '//link[@rel="apple-touch-icon-precomposed"]/@href'
    ];

    foreach ($favicon_queries as $query) {
        $favicon_node = $xpath->query($query);
        if ($favicon_node->length > 0) {
            $favicon_url = $favicon_node->item(0)->nodeValue;
            if (!empty($favicon_url)) {
                $absolute_url = kslc_relative_to_absolute_url($favicon_url, $base_url);
                if (filter_var($absolute_url, FILTER_VALIDATE_URL) &&
                    preg_match('/\.(jpg|jpeg|png|gif|webp|svg|ico)$/i', $absolute_url)) {
                    return ['image' => $absolute_url];
                }
            }
        }
    }

    // 3. ヘッダー・ナビゲーション・上部エリアの画像を探す
    $header_queries = [
        '//header//img',
        '//nav//img',
        '//div[contains(@class, "header")]//img',
        '//div[contains(@class, "logo")]//img',
        '//div[contains(@class, "banner")]//img',
        '//div[contains(@id, "header")]//img',
        '//div[contains(@id, "logo")]//img'
    ];

    $header_images = [];
    foreach ($header_queries as $query) {
        $header_imgs = $xpath->query($query);
        foreach ($header_imgs as $img) {
            $src = kslc_get_best_src($img);
            if ($src) {
                $absolute_url = kslc_relative_to_absolute_url($src, $base_url);
                if (filter_var($absolute_url, FILTER_VALIDATE_URL) &&
                    preg_match('/\.(jpg|jpeg|png|gif|webp|svg)$/i', $absolute_url)) {
                    $width = (int)$img->getAttribute('width') ?: 100;
                    $height = (int)$img->getAttribute('height') ?: 100;
                    $header_images[] = [
                        'url' => $absolute_url,
                        'size' => $width * $height,
                        'type' => 'header'
                    ];
                }
            }
        }
    }

    if (!empty($header_images)) {
        usort($header_images, function($a, $b) {
            return $b['size'] - $a['size'];
        });
        return ['image' => $header_images[0]['url']];
    }

    // 4. 本文上部（最初の方）の画像を探す
    $img_tags = $dom->getElementsByTagName('img');

    $candidate_images = [];
    $max_images_to_check = min(10, $img_tags->length); // 最初の10個の画像のみチェック

    for ($i = 0; $i < $max_images_to_check; $i++) {
        $img = $img_tags->item($i);
        $src = kslc_get_best_src($img);

        if ($src) {
            $absolute_url = kslc_relative_to_absolute_url($src, $base_url);
            if (filter_var($absolute_url, FILTER_VALIDATE_URL) &&
                preg_match('/\.(jpg|jpeg|png|gif|webp|svg)$/i', $absolute_url)) {

                $width = (int)$img->getAttribute('width') ?: 200;
                $height = (int)$img->getAttribute('height') ?: 200;
                $estimated_size = $width * $height;

                // 小さすぎる画像（アイコンなど）は除外
                if ($estimated_size >= 5000) { // 70x70以上
                    $candidate_images[] = [
                        'url' => $absolute_url,
                        'size' => $estimated_size,
                        'position' => $i + 1
                    ];
                }
            }
        }
    }

    // サイズでソートして最大のものを返す
    if (!empty($candidate_images)) {
        usort($candidate_images, function($a, $b) {
            return $b['size'] - $a['size'];
        });

        return ['image' => $candidate_images[0]['url']];
    }

    // 5. 最終フォールバック: Google Favicon API
    $domain = parse_url($base_url, PHP_URL_HOST);
    if ($domain) {
        return ['image' => 'https://www.google.com/s2/favicons?domain=' . urlencode($domain) . '&sz=128'];
    }

    return ['image' => ''];
}

function kslc_get_best_src($img) {
    $src_candidates = [
        $img->getAttribute('data-src'),
        $img->getAttribute('data-lazy-src'),
        $img->getAttribute('data-original'),
        $img->getAttribute('src')
    ];

    foreach ($src_candidates as $src) {
        if (!empty($src)) {
            return $src;
        }
    }
    return false;
}

/**
 * 画像URLが有効かどうかをチェックする（HTTPステータス200かどうか）
 *
 * @param string $image_url チェックする画像URL
 * @return bool 有効な場合はtrue、無効な場合はfalse
 */
function kslc_is_image_url_valid( $image_url ) {
    if ( empty( $image_url ) ) {
        return false;
    }

    // URLの形式をチェック
    if ( ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
        return false;
    }

    // data: URIはそのまま有効とする
    if ( strpos( $image_url, 'data:' ) === 0 ) {
        return true;
    }

    // Google Favicon APIは常に有効とみなす（フォールバック用）
    if ( strpos( $image_url, 'google.com/s2/favicons' ) !== false ) {
        return true;
    }

    // HEADリクエストでステータスコードを確認（軽量）
    $response = wp_remote_head( $image_url, array(
        'timeout' => 5,
        'redirection' => 3,
        'user-agent' => KSLC_USER_AGENT,
    ) );

    if ( is_wp_error( $response ) ) {
        return false;
    }

    $status_code = wp_remote_retrieve_response_code( $response );

    // 200-299の範囲は成功
    return $status_code >= 200 && $status_code < 300;
}

function kslc_relative_to_absolute_url( $relative_url, $base_url ) {
    if ( empty($relative_url) || filter_var( $relative_url, FILTER_VALIDATE_URL ) ) {
        return $relative_url;
    }

    $base_parts = parse_url( $base_url );
    if ( $base_parts === false || ! isset( $base_parts['scheme'] ) || ! isset( $base_parts['host'] ) ) {
        return $relative_url;
    }

    $base_scheme = $base_parts['scheme'];
    $base_host = $base_parts['host'];
    $base_path = isset($base_parts['path']) ? $base_parts['path'] : '/';

    if ( substr( $relative_url, 0, 2 ) === '//' ) {
        return $base_scheme . ':' . $relative_url;
    }
    if ( substr( $relative_url, 0, 1 ) === '/' ) {
        return $base_scheme . '://' . $base_host . $relative_url;
    }

    $path = dirname( $base_path );
    if ( $path === '.' || $path === '/' ) {
        $path = '';
    }

    // パスを解決
    $absolute_path = $path . '/' . $relative_url;
    $absolute_path = preg_replace( '#/./#', '/', $absolute_path );
    $absolute_path = preg_replace( '#/[^/]+/../#', '/', $absolute_path );

    return $base_scheme . '://' . $base_host . $absolute_path;
}
