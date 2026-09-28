<?php
/**
 * リンク切れの検知（表示時の記録 + WP-Cron による定期チェック）
 *
 * 方針:
 * - 表示時: OGP 取得の結果が 404 / 410 / 5xx / 接続失敗だったらその場で記録する（追加の HTTP 要求は発生しない）
 * - 定期:   公開済み投稿の本文からリンクカードのショートコードを集め、URL ごとに HEAD（必要なら GET）で確認する
 * - 同作者の Kashiwazaki SEO Link Doctor が有効でも本機能は止めない。
 *   Link Doctor は「手動実行・ページ内の全リンク対象」、本機能は「自動・リンクカードの URL 限定」で役割が違うため、
 *   一覧画面で Link Doctor への案内を出すだけにする
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'KSLC_LINK_CHECK_HOOK', 'kslc_link_check_event' );
define( 'KSLC_LINK_CHECK_SCHEDULE', 'kslc_link_check' );
define( 'KSLC_BROKEN_LINKS_OPTION', 'kslc_broken_links' );
define( 'KSLC_LINK_CHECK_STATE_OPTION', 'kslc_link_check_state' );
define( 'KSLC_LINK_CHECK_LAST_RUN_OPTION', 'kslc_link_check_last_run' );
define( 'KSLC_LINK_CHECK_LOCK', 'kslc_link_check_lock' );

/**
 * 表示中のページ（カードの掲載ページ）の投稿 ID。ループ外・管理画面などで特定できなければ 0
 */
function kslc_current_page_id() {
    $id = (int) get_the_ID();
    if ( $id <= 0 && ! is_admin() ) {
        $id = (int) get_queried_object_id();
    }
    return $id > 0 ? $id : 0;
}

/**
 * 記録対象にするステータスか（0 = 接続失敗、404 / 410 = リンク切れ、5xx = サーバーエラー）
 * - 401 / 403 / 429 はボット対策で返ることが多く、リンク切れとは限らないので記録しない
 * - WP_HTTP_BLOCK_EXTERNAL による遮断（WP_Error コード http_request_not_executed）はサイト側の設定なので記録しない
 */
function kslc_is_problem_status( $status, $error_code = '' ) {
    if ( 'http_request_not_executed' === (string) $error_code ) {
        return false;
    }
    $status = (int) $status;
    return 0 === $status || 404 === $status || 410 === $status || $status >= 500;
}

/**
 * ステータスの表示用ラベル
 */
function kslc_link_status_label( $status, $error = '' ) {
    $status = (int) $status;
    if ( 0 === $status ) {
        return '接続失敗' . ( '' !== (string) $error ? '（' . $error . '）' : '' );
    }
    if ( 404 === $status ) {
        return '404 Not Found' . ( '' !== (string) $error ? '（' . $error . '）' : '' );
    }
    if ( 410 === $status ) {
        return '410 Gone';
    }
    if ( $status >= 500 ) {
        return $status . ' サーバーエラー';
    }
    return (string) $status;
}

/**
 * post_id 指定の内部リンクを一覧で表す URL（投稿が消えていてもパーマリンクが引けないので ?p=ID 形式で表す）
 */
function kslc_post_reference_url( $post_id ) {
    return home_url( '/?p=' . (int) $post_id );
}

/**
 * 記録されているリンク切れ一覧（key: md5(url)）
 */
function kslc_get_broken_links() {
    $links = get_option( KSLC_BROKEN_LINKS_OPTION, [] );
    return is_array( $links ) ? $links : [];
}

function kslc_save_broken_links( $links ) {
    // 上限を超えたら最終確認日時の古いものから捨てる
    if ( count( $links ) > KSLC_BROKEN_LINKS_MAX ) {
        uasort( $links, function ( $a, $b ) {
            return strcmp( (string) $b['last_checked'], (string) $a['last_checked'] );
        } );
        $links = array_slice( $links, 0, KSLC_BROKEN_LINKS_MAX, true );
    }
    update_option( KSLC_BROKEN_LINKS_OPTION, $links, false );
}

/**
 * リンク切れを記録する（同じ URL は 1 件にまとめ、掲載ページを追記する）
 *
 * @param string    $url    リンク先 URL
 * @param int       $status HTTP ステータス（接続失敗は 0）
 * @param string    $error  エラーメッセージ（任意）
 * @param int|int[] $pages  掲載ページの投稿 ID
 */
function kslc_record_link_failure( $url, $status, $error = '', $pages = [] ) {
    $url = kslc_normalize_url( (string) $url );
    if ( '' === $url ) {
        return;
    }
    $key   = md5( $url );
    $links = kslc_get_broken_links();
    $now   = current_time( 'mysql' );

    $entry = ( isset( $links[ $key ] ) && is_array( $links[ $key ] ) ) ? $links[ $key ] : [
        'url'        => $url,
        'pages'      => [],
        'first_seen' => $now,
        'count'      => 0,
    ];
    $entry['status']       = (int) $status;
    $entry['error']        = mb_substr( wp_strip_all_tags( (string) $error ), 0, 200 );
    $entry['last_checked'] = $now;
    $entry['count']        = (int) $entry['count'] + 1;

    foreach ( (array) $pages as $page_id ) {
        $page_id = (int) $page_id;
        if ( $page_id > 0 && ! in_array( $page_id, $entry['pages'], true ) ) {
            $entry['pages'][] = $page_id;
        }
    }
    $entry['pages'] = array_slice( $entry['pages'], - KSLC_BROKEN_LINK_MAX_PAGES );

    $links[ $key ] = $entry;
    kslc_save_broken_links( $links );
}

/**
 * 正常に取得できた URL を一覧から外す（復旧したリンク）
 */
function kslc_record_link_ok( $url ) {
    $key   = md5( kslc_normalize_url( (string) $url ) );
    $links = kslc_get_broken_links();
    if ( isset( $links[ $key ] ) ) {
        unset( $links[ $key ] );
        kslc_save_broken_links( $links );
    }
}

/**
 * URL の HTTP ステータスを確認する（HEAD → 403 / 405 / 501 なら GET で再確認）
 * 取得は wp_safe_remote_*（各ホップを wp_http_validate_url で検証）。HEAD は既定で redirection=0 なので明示的に追随させる
 *
 * @return array ['status' => int（接続失敗は 0）, 'error' => string, 'code' => string（WP_Error のコード）]
 */
function kslc_check_url_status( $url ) {
    $args = [
        'timeout'     => KSLC_LINK_CHECK_TIMEOUT,
        'redirection' => KSLC_MAX_REDIRECTS,
        'user-agent'  => apply_filters( 'kslc_request_user_agent', KSLC_USER_AGENT ),
    ];

    $response = wp_safe_remote_head( $url, $args );
    if ( ! is_wp_error( $response ) ) {
        $status = (int) wp_remote_retrieve_response_code( $response );
        if ( ! in_array( $status, [ 403, 405, 501 ], true ) ) {
            return [ 'status' => $status, 'error' => '', 'code' => '' ];
        }
    }

    $response = wp_safe_remote_get( $url, $args + [ 'limit_response_size' => 4096 ] );
    if ( is_wp_error( $response ) ) {
        return [ 'status' => 0, 'error' => $response->get_error_message(), 'code' => (string) $response->get_error_code() ];
    }
    return [ 'status' => (int) wp_remote_retrieve_response_code( $response ), 'error' => '', 'code' => '' ];
}

/**
 * 公開済み投稿の本文からリンクカードのショートコードを集める
 *
 * @return array key => ['url' => string, 'post_id' => int（post_id 指定の内部リンク。それ以外は 0）, 'pages' => int[]（掲載ページ）]
 */
function kslc_collect_card_links() {
    global $wpdb;

    $post_types = array_values( get_post_types( [ 'public' => true ] ) );
    if ( empty( $post_types ) ) {
        return [];
    }

    $type_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
    $sql = "SELECT ID, post_content FROM {$wpdb->posts}
        WHERE post_status = 'publish' AND post_type IN ($type_placeholders)
        AND (post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s)";
    $params = array_merge( $post_types, [
        '%' . $wpdb->esc_like( '[linkcard' ) . '%',
        '%' . $wpdb->esc_like( '[nlink' ) . '%',
        '%' . $wpdb->esc_like( '[kashiwazaki_seo_link_card' ) . '%',
    ] );
    $rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

    $pattern = '/' . get_shortcode_regex( [ 'kashiwazaki_seo_link_card', 'linkcard', 'nlink' ] ) . '/s';
    $links = [];

    foreach ( (array) $rows as $row ) {
        if ( ! preg_match_all( $pattern, $row->post_content, $matches, PREG_SET_ORDER ) ) {
            continue;
        }
        foreach ( $matches as $m ) {
            $atts = shortcode_parse_atts( $m[3] );
            if ( ! is_array( $atts ) ) {
                $atts = [];
            }

            $target_post_id = 0;
            if ( ! empty( $atts['post_id'] ) && is_numeric( $atts['post_id'] ) ) {
                // post_id 指定は公開状態にかかわらず ?p=ID 形式をキーにする（表示側の記録と同じキー。公開/非公開で記録のキーが変わらない）
                $target_post_id = (int) $atts['post_id'];
                $url = kslc_post_reference_url( $target_post_id );
            } elseif ( ! empty( $atts['url'] ) ) {
                $url = trim( (string) $atts['url'] );
                if ( strpos( $url, '/' ) === 0 && strpos( $url, '//' ) !== 0 ) {
                    $url = home_url( $url );
                }
                $url = sanitize_url( $url );
                if ( '' === $url || ! filter_var( $url, FILTER_VALIDATE_URL ) || ! kslc_is_http_url( $url ) ) {
                    continue;
                }
            } else {
                continue;
            }

            $url = kslc_normalize_url( $url );
            $key = md5( $url );
            if ( ! isset( $links[ $key ] ) ) {
                $links[ $key ] = [ 'url' => $url, 'post_id' => $target_post_id, 'pages' => [] ];
            }
            if ( ! in_array( (int) $row->ID, $links[ $key ]['pages'], true ) ) {
                $links[ $key ]['pages'][] = (int) $row->ID;
            }
        }
    }

    return $links;
}

/**
 * 1 件を確認して記録する
 *
 * @return string 'ok' | 'broken' | 'skipped'（サイト側の設定で確認できなかった）
 */
function kslc_check_card_link( $item ) {
    $url     = $item['url'];
    $post_id = (int) $item['post_id'];

    // post_id 指定: 投稿が公開されていれば HTTP 要求なしで OK、無ければリンク切れ
    if ( $post_id > 0 ) {
        if ( 'publish' === get_post_status( $post_id ) ) {
            kslc_record_link_ok( $url );
            return 'ok';
        }
        kslc_record_link_failure( $url, 404, '投稿が見つかりません（削除または非公開）', $item['pages'] );
        return 'broken';
    }

    // URL 指定の内部リンク: 公開投稿に解決できれば HTTP 要求なしで OK（解決できなければ実際に取得して確認する）
    if ( kslc_is_same_site_url( $url ) ) {
        $resolved = (int) url_to_postid( $url );
        if ( $resolved > 0 && 'publish' === get_post_status( $resolved ) ) {
            kslc_record_link_ok( $url );
            return 'ok';
        }
    }

    $result = kslc_check_url_status( $url );
    if ( 'http_request_not_executed' === $result['code'] ) {
        // サイト側の設定（WP_HTTP_BLOCK_EXTERNAL）で確認できない URL は判定を保留する
        return 'skipped';
    }
    if ( kslc_is_problem_status( $result['status'], $result['code'] ) ) {
        kslc_record_link_failure( $url, $result['status'], $result['error'], $item['pages'] );
        return 'broken';
    }
    kslc_record_link_ok( $url );
    return 'ok';
}

/**
 * 全件走査の完了時に、今回の走査の対象に無く、走査の開始より前に記録された項目を一覧から外す
 * （本文から消したカードの記録・キーが変わった古い記録の残り）。走査の開始以降に表示側が記録した項目は次の走査まで残す
 *
 * @param array  $queue   今回の走査の対象（kslc_collect_card_links() の要素の配列）
 * @param string $started 走査を開始した日時（current_time('mysql') 形式。記録の last_checked と同じ書式で比較する）
 * @return int 外した件数
 */
function kslc_prune_broken_links( $queue, $started ) {
    $keep = [];
    foreach ( (array) $queue as $item ) {
        if ( is_array( $item ) && ! empty( $item['url'] ) ) {
            $keep[ md5( kslc_normalize_url( (string) $item['url'] ) ) ] = true;
        }
    }
    $links   = kslc_get_broken_links();
    $pruned  = 0;
    $started = (string) $started;
    foreach ( $links as $key => $entry ) {
        if ( isset( $keep[ $key ] ) ) {
            continue;
        }
        $last_checked = ( is_array( $entry ) && isset( $entry['last_checked'] ) ) ? (string) $entry['last_checked'] : '';
        if ( '' !== $started && '' !== $last_checked && strcmp( $last_checked, $started ) >= 0 ) {
            continue;
        }
        unset( $links[ $key ] );
        $pruned++;
    }
    if ( $pruned > 0 ) {
        kslc_save_broken_links( $links );
    }
    return $pruned;
}

/**
 * 定期チェック本体。時間予算（KSLC_LINK_CHECK_TIME_BUDGET 秒）を超えたら残りを 1 分後の単発イベントへ引き継ぐ
 * 全件を確認し終えたら、今回の走査に無かった古い記録を一覧から外す（kslc_prune_broken_links）
 *
 * @param bool $fresh true なら進行中の続きを捨てて最初から集め直す（「今すぐチェック」用。無効設定でも実行する）
 * @return array ['checked' => 今回確認した件数, 'broken' => 累計のリンク切れ件数, 'remaining' => 未確認件数, 'total' => 総件数, 'skipped' => 理由]
 */
function kslc_run_link_check( $fresh = false ) {
    $summary = [ 'checked' => 0, 'broken' => 0, 'remaining' => 0, 'total' => 0, 'skipped' => '' ];

    if ( ! $fresh && ! get_option( 'kslc_link_check_enabled', true ) ) {
        $summary['skipped'] = 'disabled';
        return $summary;
    }
    if ( get_transient( KSLC_LINK_CHECK_LOCK ) ) {
        $summary['skipped'] = 'locked';
        return $summary;
    }
    set_transient( KSLC_LINK_CHECK_LOCK, 1, 5 * MINUTE_IN_SECONDS );

    $state = $fresh ? false : get_option( KSLC_LINK_CHECK_STATE_OPTION, false );
    if ( ! is_array( $state ) || empty( $state['queue'] ) || ! isset( $state['cursor'] ) ) {
        $state = [
            'queue'         => array_values( kslc_collect_card_links() ),
            'cursor'        => 0,
            'broken'        => 0,
            'started'       => time(),
            'started_local' => current_time( 'mysql' ), // 記録の last_checked と同じ書式（掃除の基準）
        ];
    }

    $started = microtime( true );
    $total   = count( $state['queue'] );
    while ( $state['cursor'] < $total ) {
        if ( microtime( true ) - $started > KSLC_LINK_CHECK_TIME_BUDGET ) {
            break;
        }
        $item = $state['queue'][ $state['cursor'] ];
        $state['cursor']++;
        $summary['checked']++;
        if ( 'broken' === kslc_check_card_link( $item ) ) {
            $state['broken']++;
        }
    }

    $remaining = $total - $state['cursor'];
    if ( $remaining > 0 ) {
        update_option( KSLC_LINK_CHECK_STATE_OPTION, $state, false );
        if ( ! wp_next_scheduled( KSLC_LINK_CHECK_HOOK, [ 'continue' ] ) ) {
            wp_schedule_single_event( time() + MINUTE_IN_SECONDS, KSLC_LINK_CHECK_HOOK, [ 'continue' ] );
        }
    } else {
        // 全件を確認し終えた: 今回の走査に無く、走査開始より前に記録された項目（本文から消したカード・古いキーの記録）を外す
        $pruned = kslc_prune_broken_links( $state['queue'], isset( $state['started_local'] ) ? $state['started_local'] : '' );
        delete_option( KSLC_LINK_CHECK_STATE_OPTION );
        update_option( KSLC_LINK_CHECK_LAST_RUN_OPTION, [
            'time'   => time(),
            'total'  => $total,
            'broken' => (int) $state['broken'],
            'pruned' => $pruned,
        ], false );
    }

    delete_transient( KSLC_LINK_CHECK_LOCK );

    $summary['broken']    = (int) $state['broken'];
    $summary['remaining'] = $remaining;
    $summary['total']     = $total;
    return $summary;
}

/**
 * WP-Cron から呼ばれる入口（定期イベントと「続き」の単発イベントの両方）
 */
function kslc_link_check_cron_callback() {
    kslc_run_link_check( false );
}
add_action( KSLC_LINK_CHECK_HOOK, 'kslc_link_check_cron_callback' );

/**
 * 設定の間隔（時間）を WP-Cron のスケジュールとして登録する（cron_schedules フィルター）
 */
function kslc_add_cron_interval( $schedules ) {
    $hours = kslc_link_check_interval_hours();
    $schedules[ KSLC_LINK_CHECK_SCHEDULE ] = [
        'interval' => $hours * HOUR_IN_SECONDS,
        'display'  => sprintf( 'Kashiwazaki SEO Link Card: %d 時間ごと', $hours ),
    ];
    return $schedules;
}
add_filter( 'cron_schedules', 'kslc_add_cron_interval' );

function kslc_link_check_interval_hours() {
    return kslc_sanitize_int_range(
        get_option( 'kslc_link_check_interval', KSLC_DEFAULT_LINK_CHECK_INTERVAL ),
        KSLC_LINK_CHECK_INTERVAL_MIN,
        KSLC_LINK_CHECK_INTERVAL_MAX,
        KSLC_DEFAULT_LINK_CHECK_INTERVAL
    );
}

/**
 * 定期イベントを登録する（有効化時・設定変更時・未登録のとき）。重複登録は wp_next_scheduled で防ぐ
 */
function kslc_schedule_link_check() {
    if ( ! get_option( 'kslc_link_check_enabled', true ) ) {
        return;
    }
    if ( ! wp_next_scheduled( KSLC_LINK_CHECK_HOOK ) ) {
        wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, KSLC_LINK_CHECK_SCHEDULE, KSLC_LINK_CHECK_HOOK );
    }
}

/**
 * 定期イベントと「続き」の単発イベントをすべて解除する（無効化時）
 */
function kslc_unschedule_link_check() {
    wp_unschedule_hook( KSLC_LINK_CHECK_HOOK );
    delete_option( KSLC_LINK_CHECK_STATE_OPTION );
    delete_transient( KSLC_LINK_CHECK_LOCK );
}

/**
 * 設定（有効 / 間隔）が変わったら登録し直す。WP-Cron は登録時の間隔を保持するため、間隔変更は解除 → 再登録が必要
 */
function kslc_reschedule_link_check() {
    wp_clear_scheduled_hook( KSLC_LINK_CHECK_HOOK );
    kslc_schedule_link_check();
}
add_action( 'update_option_kslc_link_check_interval', 'kslc_reschedule_link_check' );
add_action( 'add_option_kslc_link_check_interval', 'kslc_reschedule_link_check' );
add_action( 'update_option_kslc_link_check_enabled', 'kslc_reschedule_link_check' );
add_action( 'add_option_kslc_link_check_enabled', 'kslc_reschedule_link_check' );

/**
 * ファイル差し替えで更新した場合は有効化フックが走らないので、init でも未登録なら登録する（設定 OFF なら解除する）
 */
function kslc_maybe_schedule_link_check() {
    if ( get_option( 'kslc_link_check_enabled', true ) ) {
        if ( ! wp_next_scheduled( KSLC_LINK_CHECK_HOOK ) ) {
            kslc_schedule_link_check();
        }
    } elseif ( wp_next_scheduled( KSLC_LINK_CHECK_HOOK ) ) {
        wp_clear_scheduled_hook( KSLC_LINK_CHECK_HOOK );
    }
}
add_action( 'init', 'kslc_maybe_schedule_link_check' );

/**
 * 同作者の Kashiwazaki SEO Link Doctor が有効か
 */
function kslc_is_link_doctor_active() {
    return class_exists( 'KashiwazakiSEOLinkDoctor' ) || defined( 'KSV_VERSION' );
}
