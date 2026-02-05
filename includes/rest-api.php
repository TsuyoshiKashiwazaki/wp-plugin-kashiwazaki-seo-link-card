<?php
/**
 * REST API エンドポイントの登録
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * カスタム投稿タイプをREST APIで利用可能にする
 */
function kslc_register_custom_post_types_rest_support() {
    // すべての投稿タイプを取得
    $post_types = get_post_types( array( 'public' => true ), 'names' );
    
    foreach ( $post_types as $post_type ) {
        // REST APIサポートを追加
        add_post_type_support( $post_type, 'rest' );
        
        // 投稿タイプオブジェクトを取得
        $post_type_object = get_post_type_object( $post_type );
        
        if ( $post_type_object && ! $post_type_object->show_in_rest ) {
            // REST APIで表示するように設定
            global $wp_post_types;
            if ( isset( $wp_post_types[ $post_type ] ) ) {
                $wp_post_types[ $post_type ]->show_in_rest = true;
                
                // REST APIベースを設定（未設定の場合）
                if ( empty( $wp_post_types[ $post_type ]->rest_base ) ) {
                    $wp_post_types[ $post_type ]->rest_base = $post_type;
                }
                
                // REST APIコントローラーを設定（未設定の場合）
                if ( empty( $wp_post_types[ $post_type ]->rest_controller_class ) ) {
                    $wp_post_types[ $post_type ]->rest_controller_class = 'WP_REST_Posts_Controller';
                }
            }
        }
    }
}
add_action( 'init', 'kslc_register_custom_post_types_rest_support', 99 );

/**
 * カスタムREST APIエンドポイントの登録
 */
function kslc_register_rest_routes() {
    // すべての投稿を取得するエンドポイント
    register_rest_route( 'kslc/v1', '/all-posts', array(
        'methods'  => 'GET',
        'callback' => 'kslc_get_all_posts',
        'permission_callback' => function() {
            return current_user_can( 'edit_posts' );
        },
        'args' => array(
            'search' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'post_type' => array(
                'type' => 'string',
                'default' => 'all',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'per_page' => array(
                'type' => 'integer',
                'default' => 100,
                'sanitize_callback' => 'absint',
            ),
        ),
    ));
    
    // 投稿IDから情報を取得するエンドポイント
    register_rest_route( 'kslc/v1', '/post/(?P<id>\d+)', array(
        'methods'  => 'GET',
        'callback' => 'kslc_get_post_by_id',
        'permission_callback' => function() {
            return current_user_can( 'edit_posts' );
        },
        'args' => array(
            'id' => array(
                'validate_callback' => function( $param, $request, $key ) {
                    return is_numeric( $param );
                }
            ),
        ),
    ));

    // URLで投稿を検索するエンドポイント
    register_rest_route( 'kslc/v1', '/search-by-url', array(
        'methods'  => 'GET',
        'callback' => 'kslc_search_posts_by_url',
        'permission_callback' => function() {
            return current_user_can( 'edit_posts' );
        },
        'args' => array(
            'url' => array(
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'per_page' => array(
                'type' => 'integer',
                'default' => 20,
                'sanitize_callback' => 'absint',
            ),
        ),
    ));
}
add_action( 'rest_api_init', 'kslc_register_rest_routes' );

/**
 * すべての投稿タイプから投稿を取得
 */
function kslc_get_all_posts( $request ) {
    $search = $request->get_param( 'search' );
    $post_type_filter = $request->get_param( 'post_type' );
    $per_page = $request->get_param( 'per_page' );

    $all_posts = array();

    // 取得する投稿タイプを決定
    if ( $post_type_filter === 'all' ) {
        $post_types = get_post_types( array( 'public' => true ), 'names' );
    } else {
        $post_types = array( $post_type_filter );
    }

    // 検索キーワードがある場合はタイトル部分一致検索用のフィルターを追加
    $search_filter = null;
    if ( ! empty( $search ) ) {
        $search_filter = function( $where ) use ( $search ) {
            global $wpdb;
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $where .= $wpdb->prepare( " AND {$wpdb->posts}.post_title LIKE %s", $like );
            return $where;
        };
        add_filter( 'posts_where', $search_filter );
    }

    foreach ( $post_types as $post_type ) {
        $args = array(
            'post_type'      => $post_type,
            'posts_per_page' => $per_page,
            'post_status'    => 'publish',
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'suppress_filters' => false, // フィルターを有効にする
        );

        $posts = get_posts( $args );

        foreach ( $posts as $post ) {
            $post_type_obj = get_post_type_object( $post->post_type );

            $all_posts[] = array(
                'id'    => $post->ID,
                'title' => get_the_title( $post ),
                'link'  => get_permalink( $post ),
                'type'  => $post_type_obj ? $post_type_obj->labels->singular_name : $post->post_type,
                'date'  => $post->post_modified,
            );
        }
    }

    // フィルターを解除（自分が追加したフィルターのみ）
    if ( $search_filter ) {
        remove_filter( 'posts_where', $search_filter );
    }

    // 日付順でソート
    usort( $all_posts, function( $a, $b ) {
        return strtotime( $b['date'] ) - strtotime( $a['date'] );
    });

    return rest_ensure_response( $all_posts );
}

/**
 * IDから投稿情報を取得
 */
function kslc_get_post_by_id( $request ) {
    $post_id = $request->get_param( 'id' );
    $post = get_post( $post_id );

    if ( ! $post ) {
        return new WP_Error( 'not_found', 'Post not found', array( 'status' => 404 ) );
    }

    $post_type_obj = get_post_type_object( $post->post_type );

    $response = array(
        'id'    => $post->ID,
        'title' => get_the_title( $post ),
        'link'  => get_permalink( $post ),
        'type'  => $post_type_obj ? $post_type_obj->labels->singular_name : $post->post_type,
    );

    return rest_ensure_response( $response );
}

/**
 * URLで投稿を検索
 */
function kslc_search_posts_by_url( $request ) {
    global $wpdb;

    $url_search = $request->get_param( 'url' );
    $per_page = $request->get_param( 'per_page' );

    // サイトURLを取得
    $site_url = home_url();

    // 検索キーワードからパスを抽出（先頭の/を削除）
    $search_path = ltrim( $url_search, '/' );

    // すべての公開投稿タイプを取得
    $post_types = get_post_types( array( 'public' => true ), 'names' );
    $post_types_placeholder = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );

    // guid または post_name で検索
    $like_search = '%' . $wpdb->esc_like( $search_path ) . '%';

    $query = $wpdb->prepare(
        "SELECT ID, post_title, post_type, post_name, guid
         FROM {$wpdb->posts}
         WHERE post_status = 'publish'
         AND post_type IN ($post_types_placeholder)
         AND (post_name LIKE %s OR guid LIKE %s)
         ORDER BY post_modified DESC
         LIMIT %d",
        array_merge(
            $post_types,
            array( $like_search, $like_search, $per_page )
        )
    );

    $results = $wpdb->get_results( $query );

    $posts = array();
    foreach ( $results as $row ) {
        $permalink = get_permalink( $row->ID );
        $post_type_obj = get_post_type_object( $row->post_type );

        $posts[] = array(
            'id'    => $row->ID,
            'title' => $row->post_title ? $row->post_title : '（タイトルなし）',
            'link'  => $permalink,
            'type'  => $post_type_obj ? $post_type_obj->labels->singular_name : $row->post_type,
            'slug'  => $row->post_name,
        );
    }

    return rest_ensure_response( $posts );
}