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
    // 1. HTTPヘッダーのcharset
    $header_charset = '';
    if ( $response ) {
        $content_type = wp_remote_retrieve_header( $response, 'content-type' );
        if ( preg_match( '/charset=["\']?([^\s;"\']+)/i', $content_type, $matches ) ) {
            $header_charset = kslc_normalize_charset_name( $matches[1] );
        }
    }

    // 2. 文書側の宣言（XML宣言 → http-equiv → HTML5 meta charset の順）
    $meta_charset = '';
    if ( preg_match( '/<\?xml[^>]+encoding=["\']([^"\']+)["\']/i', $html, $matches ) ) {
        $meta_charset = kslc_normalize_charset_name( $matches[1] );
    } elseif ( preg_match( '/<meta[^>]+http-equiv=["\']?Content-Type["\']?[^>]+charset=([^\s"\';>]+)/i', $html, $matches ) ) {
        $meta_charset = kslc_normalize_charset_name( $matches[1] );
    } elseif ( preg_match( '/<meta[^>]+charset=["\']?([^\s"\';>]+)/i', $html, $matches ) ) {
        $meta_charset = kslc_normalize_charset_name( $matches[1] );
    }

    // ヘッダーが UTF-8 以外を主張していても、文書自身が UTF-8 を宣言し中身も UTF-8 として妥当ならそちらを信用する
    // （AddDefaultCharset ISO-8859-1 のような誤ったヘッダーで文字化けさせない）
    if ( '' !== $header_charset && 'UTF-8' !== $header_charset && 'UTF-8' === $meta_charset && mb_check_encoding( $html, 'UTF-8' ) ) {
        return 'UTF-8';
    }
    if ( '' !== $header_charset ) {
        return $header_charset;
    }
    if ( '' !== $meta_charset ) {
        return $meta_charset;
    }

    // 3. mb_detect_encodingでフォールバック
    $detected = mb_detect_encoding( $html, ['UTF-8', 'SJIS', 'EUC-JP', 'ISO-2022-JP', 'ISO-8859-1'], true );
    if ( $detected ) {
        return strtoupper( $detected );
    }

    return 'UTF-8';
}

/**
 * charset 名を大文字化し、UTF8 のような表記ゆれを吸収する
 */
function kslc_normalize_charset_name( $name ) {
    $name = strtoupper( trim( (string) $name ) );
    if ( 'UTF8' === $name ) {
        $name = 'UTF-8';
    }
    return $name;
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

    // mb_convert_encodingで変換（PHP 8 では未知のエンコーディング名で ValueError が投げられるため捕捉する）
    try {
        $converted = mb_convert_encoding( $html, 'UTF-8', $encoding );
    } catch ( \ValueError $e ) {
        return $html;
    }
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

    // http(s) 以外のスキームは取得しない
    $scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );
    if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
        return false;
    }

    // User-Agent and headers can be filtered
    $user_agent = apply_filters( 'kslc_request_user_agent', KSLC_USER_AGENT );
    $timeout = apply_filters( 'kslc_request_timeout', 15 );
    $headers = apply_filters( 'kslc_request_headers', array(
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language' => 'ja,en-US;q=0.9,en;q=0.8',
    ) );

    // wp_safe_remote_get: ループバック／プライベートIP・不正ポート宛の要求（SSRF）を拒否する
    $response = wp_safe_remote_get( $url, array(
        'timeout'             => $timeout,
        'user-agent'          => $user_agent,
        'headers'             => $headers,
        'limit_response_size' => KSLC_MAX_RESPONSE_BYTES,
    ) );

    if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
        return false;
    }

    // HTML 以外（PDF・画像など）は解析しない → 簡易カードにフォールバック
    $content_type = strtolower( (string) wp_remote_retrieve_header( $response, 'content-type' ) );
    if ( '' !== $content_type && false === strpos( $content_type, 'html' ) && false === strpos( $content_type, 'xml' ) ) {
        return false;
    }

    $html = wp_remote_retrieve_body( $response );
    if ( empty( $html ) ) {
        return false;
    }

    // エンコーディングを検出してUTF-8に変換
    $encoding = kslc_detect_encoding( $html, $response );
    $html = kslc_convert_to_utf8( $html, $encoding );

    $dom = kslc_load_html_dom( $html );
    $xpath = new DOMXPath( $dom );

    // 相対URL解決の基準: リダイレクト後の最終URL → <base href> があればそれを優先
    $base_url = kslc_get_response_final_url( $response, $url );
    $base_tag = $xpath->query( '//base/@href' );
    if ( $base_tag->length > 0 ) {
        $base_href = trim( $base_tag->item( 0 )->nodeValue );
        if ( '' !== $base_href ) {
            $resolved_base = kslc_relative_to_absolute_url( $base_href, $base_url );
            if ( filter_var( $resolved_base, FILTER_VALIDATE_URL ) ) {
                $base_url = $resolved_base;
            }
        }
    }

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
             $ogp_data[ $key ] = kslc_relative_to_absolute_url( $value, $base_url );
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
        $fallback_result = kslc_find_fallback_image( $xpath, $dom, $base_url );
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

/**
 * OGP画像が無いページ用の代替画像を探す
 * 候補を優先順に集め、実在確認（HTTP 2xx かつ image/*）を通った最初のものを返す
 */
function kslc_find_fallback_image( $xpath, $dom, $base_url ) {
    $candidates = [];

    // 1. 他のmetaタグから画像を探す
    $meta_image_queries = [
        '//meta[@property="twitter:image"]/@content',
        '//meta[@name="twitter:image"]/@content',
        '//meta[@itemprop="image"]/@content',
        '//meta[@name="msapplication-TileImage"]/@content'
    ];

    foreach ( $meta_image_queries as $query ) {
        $image_node = $xpath->query( $query );
        if ( $image_node->length > 0 ) {
            $candidates[] = kslc_relative_to_absolute_url( trim( $image_node->item( 0 )->nodeValue ), $base_url );
        }
    }

    // 2. favicon系を探す（拡張子付きのもののみ）
    $favicon_queries = [
        '//link[@rel="icon"]/@href',
        '//link[@rel="shortcut icon"]/@href',
        '//link[@rel="apple-touch-icon"]/@href',
        '//link[@rel="apple-touch-icon-precomposed"]/@href'
    ];

    foreach ( $favicon_queries as $query ) {
        $favicon_node = $xpath->query( $query );
        if ( $favicon_node->length > 0 ) {
            $absolute_url = kslc_relative_to_absolute_url( trim( $favicon_node->item( 0 )->nodeValue ), $base_url );
            if ( kslc_url_has_image_extension( $absolute_url, true ) ) {
                $candidates[] = $absolute_url;
            }
        }
    }

    // 3. ヘッダー・ナビゲーション・上部エリアの画像を探す（面積の大きい順）
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
    foreach ( $header_queries as $query ) {
        foreach ( $xpath->query( $query ) as $img ) {
            $src = kslc_get_best_src( $img );
            if ( ! $src ) {
                continue;
            }
            $absolute_url = kslc_relative_to_absolute_url( $src, $base_url );
            if ( ! kslc_url_has_image_extension( $absolute_url ) ) {
                continue;
            }
            $width  = (int) $img->getAttribute( 'width' ) ?: 100;
            $height = (int) $img->getAttribute( 'height' ) ?: 100;
            $header_images[] = [ 'url' => $absolute_url, 'size' => $width * $height ];
        }
    }
    usort( $header_images, function ( $a, $b ) {
        return $b['size'] - $a['size'];
    } );
    foreach ( $header_images as $img ) {
        $candidates[] = $img['url'];
    }

    // 4. 本文上部（最初の10個）の画像を探す（70x70以上、面積の大きい順）
    $img_tags = $dom->getElementsByTagName( 'img' );
    $body_images = [];
    $max_images_to_check = min( 10, $img_tags->length );

    for ( $i = 0; $i < $max_images_to_check; $i++ ) {
        $img = $img_tags->item( $i );
        $src = kslc_get_best_src( $img );
        if ( ! $src ) {
            continue;
        }
        $absolute_url = kslc_relative_to_absolute_url( $src, $base_url );
        if ( ! kslc_url_has_image_extension( $absolute_url ) ) {
            continue;
        }
        $width  = (int) $img->getAttribute( 'width' ) ?: 200;
        $height = (int) $img->getAttribute( 'height' ) ?: 200;
        if ( $width * $height < 5000 ) {
            continue; // 小さすぎる画像（アイコンなど）は除外
        }
        $body_images[] = [ 'url' => $absolute_url, 'size' => $width * $height ];
    }
    usort( $body_images, function ( $a, $b ) {
        return $b['size'] - $a['size'];
    } );
    foreach ( $body_images as $img ) {
        $candidates[] = $img['url'];
    }

    // 実在確認: 候補を順に確認し、最初に通ったものを採用（HTTP リクエスト数には上限を設ける）
    $candidates = array_values( array_unique( array_filter( $candidates ) ) );
    $checked = 0;
    foreach ( $candidates as $candidate ) {
        if ( ! filter_var( $candidate, FILTER_VALIDATE_URL ) ) {
            continue;
        }
        if ( $checked >= KSLC_MAX_IMAGE_CHECKS ) {
            break;
        }
        $checked++;
        if ( kslc_is_image_url_valid( $candidate ) ) {
            return [ 'image' => $candidate ];
        }
    }

    // 5. 最終フォールバック: Google Favicon API
    $domain = parse_url( $base_url, PHP_URL_HOST );
    if ( $domain ) {
        return [ 'image' => 'https://www.google.com/s2/favicons?domain=' . urlencode( $domain ) . '&sz=128' ];
    }

    return [ 'image' => '' ];
}

function kslc_get_best_src( $img ) {
    $src_candidates = [
        $img->getAttribute( 'data-src' ),
        $img->getAttribute( 'data-lazy-src' ),
        $img->getAttribute( 'data-original' ),
        $img->getAttribute( 'src' )
    ];

    foreach ( $src_candidates as $src ) {
        $src = trim( (string) $src );
        if ( '' !== $src ) {
            return $src;
        }
    }
    return false;
}

/**
 * URL のパス部分が画像拡張子で終わるか（クエリ文字列は無視する）
 */
function kslc_url_has_image_extension( $url, $allow_ico = false ) {
    $path = (string) parse_url( $url, PHP_URL_PATH );
    $pattern = $allow_ico ? '/\.(jpe?g|png|gif|webp|avif|svg|ico)$/i' : '/\.(jpe?g|png|gif|webp|avif|svg)$/i';
    return (bool) preg_match( $pattern, $path );
}

/**
 * 画像URLが有効かどうかをチェックする（HTTP 2xx かつ Content-Type が image/*）
 *
 * @param string $image_url チェックする画像URL
 * @return bool 有効な場合はtrue、無効な場合はfalse
 */
function kslc_is_image_url_valid( $image_url ) {
    if ( empty( $image_url ) || ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
        return false;
    }

    $scheme = strtolower( (string) parse_url( $image_url, PHP_URL_SCHEME ) );
    if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
        return false;
    }

    // Google Favicon APIは常に有効とみなす（フォールバック用）
    if ( strpos( $image_url, 'google.com/s2/favicons' ) !== false ) {
        return true;
    }

    $args = array(
        'timeout'     => 5,
        'redirection' => 3,
        'user-agent'  => apply_filters( 'kslc_request_user_agent', KSLC_USER_AGENT ),
    );

    // HEADリクエストでステータスコードを確認（軽量）
    $response = wp_safe_remote_head( $image_url, $args );
    if ( is_wp_error( $response ) ) {
        return false;
    }
    $status_code = (int) wp_remote_retrieve_response_code( $response );

    // HEAD を受け付けないサーバーは GET（先頭数KBのみ）で再確認
    if ( in_array( $status_code, array( 403, 405, 501 ), true ) ) {
        $response = wp_safe_remote_get( $image_url, $args + array( 'limit_response_size' => 4096 ) );
        if ( is_wp_error( $response ) ) {
            return false;
        }
        $status_code = (int) wp_remote_retrieve_response_code( $response );
    }

    if ( $status_code < 200 || $status_code >= 300 ) {
        return false;
    }

    // Content-Type が付いていれば image/* であることを確認
    $content_type = strtolower( (string) wp_remote_retrieve_header( $response, 'content-type' ) );
    if ( '' !== $content_type && 0 !== strpos( $content_type, 'image/' ) ) {
        return false;
    }

    return true;
}

/**
 * 相対URLを絶対URLに変換する（RFC 3986 §5.2 準拠）
 *
 * - "images/a.gif" + "https://ex.com/dir/"          => https://ex.com/dir/images/a.gif
 * - "images/a.gif" + "https://ex.com/dir/page.html" => https://ex.com/dir/images/a.gif
 * - "/a.gif"        + "https://ex.com/dir/"          => https://ex.com/a.gif
 * - "//cdn/a.gif"   + "https://ex.com/"              => https://cdn/a.gif
 * - "../a.gif"      + "https://ex.com/a/b/"          => https://ex.com/a/a.gif
 */
function kslc_relative_to_absolute_url( $relative_url, $base_url ) {
    $relative_url = trim( (string) $relative_url );
    if ( '' === $relative_url ) {
        return '';
    }

    // スキーム付き（http:, https:, data: など）はそのまま返す
    if ( preg_match( '#^[a-z][a-z0-9+.\-]*:#i', $relative_url ) ) {
        return $relative_url;
    }

    $base = parse_url( $base_url );
    if ( false === $base || empty( $base['scheme'] ) || empty( $base['host'] ) ) {
        return $relative_url;
    }

    // "//host/path" 形式はスキームだけ引き継ぐ
    if ( substr( $relative_url, 0, 2 ) === '//' ) {
        return $base['scheme'] . ':' . $relative_url;
    }

    $origin = $base['scheme'] . '://' . $base['host'] . ( isset( $base['port'] ) ? ':' . $base['port'] : '' );

    $ref = parse_url( $relative_url );
    if ( false === $ref ) {
        $ref = array( 'path' => $relative_url );
    }

    $base_path = ( isset( $base['path'] ) && '' !== $base['path'] ) ? $base['path'] : '/';
    $ref_path  = isset( $ref['path'] ) ? $ref['path'] : '';
    $query     = isset( $ref['query'] ) ? '?' . $ref['query'] : '';
    $fragment  = isset( $ref['fragment'] ) ? '#' . $ref['fragment'] : '';

    if ( '' === $ref_path ) {
        // "?q=1" や "#f" のみ: ベースのパスをそのまま使う
        $path = $base_path;
        if ( '' === $query && isset( $base['query'] ) ) {
            $query = '?' . $base['query'];
        }
    } elseif ( '/' === $ref_path[0] ) {
        $path = $ref_path;
    } else {
        // RFC 3986 §5.2.3: ベースパスの最後の "/" までを引き継ぐ
        // （末尾が "/" のURLはそれ自体がディレクトリ。dirname() を使うと1階層上に外れる）
        $path = substr( $base_path, 0, strrpos( $base_path, '/' ) + 1 ) . $ref_path;
    }

    return $origin . kslc_remove_dot_segments( $path ) . $query . $fragment;
}

/**
 * パス中の "." と ".." を解決する（RFC 3986 §5.2.4）
 */
function kslc_remove_dot_segments( $path ) {
    $segments = explode( '/', $path );
    $last     = count( $segments ) - 1;
    $output   = array();

    foreach ( $segments as $i => $segment ) {
        if ( '.' === $segment || '..' === $segment ) {
            if ( '..' === $segment && count( $output ) > 1 ) {
                array_pop( $output );
            }
            if ( $i === $last ) {
                $output[] = ''; // 末尾の "." / ".." はディレクトリ扱い（末尾スラッシュを残す）
            }
            continue;
        }
        $output[] = $segment;
    }

    $result = implode( '/', $output );
    return ( '' === $result || '/' !== $result[0] ) ? '/' . ltrim( $result, '/' ) : $result;
}

/**
 * HTML文字列から DOMDocument を生成する
 * PHP 8.2 で非推奨となった mb_convert_encoding(..., 'HTML-ENTITIES') を使わず、UTF-8 宣言を前置して読み込む
 */
function kslc_load_html_dom( $html ) {
    // 既に UTF-8 へ変換済みなので、文書内の charset 宣言（旧エンコーディングを指している可能性がある）は取り除く
    $html = preg_replace( '/^\s*<\?xml[^>]*\?>/i', '', $html );
    $html = preg_replace( '/<meta[^>]+charset=[^>]*>/i', '', $html );

    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors( true );
    $dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NONET );
    libxml_clear_errors();
    libxml_use_internal_errors( $previous );

    return $dom;
}

/**
 * リダイレクト後の最終URLを取得する（取得できなければ元のURL）
 */
function kslc_get_response_final_url( $response, $fallback_url ) {
    if ( is_array( $response ) && isset( $response['http_response'] ) && is_object( $response['http_response'] )
        && method_exists( $response['http_response'], 'get_response_object' ) ) {
        $requests_response = $response['http_response']->get_response_object();
        if ( is_object( $requests_response ) && ! empty( $requests_response->url )
            && filter_var( $requests_response->url, FILTER_VALIDATE_URL ) ) {
            return $requests_response->url;
        }
    }
    return $fallback_url;
}
