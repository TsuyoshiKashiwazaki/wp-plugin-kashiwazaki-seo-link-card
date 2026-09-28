<?php
/**
 * プラグインの設定値定義ファイル
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// デフォルトカラー定義
define('KSLC_DEFAULT_EXTERNAL_COLOR', '#1976d2');
define('KSLC_DEFAULT_INTERNAL_COLOR', '#616161');

// 色の明度・彩度調整定数（ハードコードを避ける）
define('KSLC_COLOR_BG_SATURATION_RATIO', 0.15);
define('KSLC_COLOR_BG_LIGHTNESS', 0.94);
define('KSLC_COLOR_BORDER_SATURATION_RATIO', 0.25);
define('KSLC_COLOR_BORDER_LIGHTNESS', 0.82);

// カラーテーマ定義
define('KSLC_COLOR_THEMES', [
    'red' => [
        'primary' => '#d32f2f',
        'text' => '#000000',
        'meta' => '#424242',
        'bg' => '#ffebee',
        'border' => '#ffcdd2'
    ],
    'blue' => [
        'primary' => '#1976d2',
        'text' => '#000000',
        'meta' => '#424242',
        'bg' => '#e3f2fd',
        'border' => '#bbdefb'
    ],
    'green' => [
        'primary' => '#388e3c',
        'text' => '#000000',
        'meta' => '#424242',
        'bg' => '#e8f5e8',
        'border' => '#c8e6c9'
    ],
    'purple' => [
        'primary' => '#7b1fa2',
        'text' => '#000000',
        'meta' => '#424242',
        'bg' => '#f3e5f5',
        'border' => '#e1bee7'
    ],
    'orange' => [
        'primary' => '#f57c00',
        'text' => '#000000',
        'meta' => '#424242',
        'bg' => '#fff3e0',
        'border' => '#ffcc02'
    ],
    'gray' => [
        'primary' => '#616161',
        'text' => '#000000',
        'meta' => '#424242',
        'bg' => '#fafafa',
        'border' => '#e0e0e0'
    ],
    'white' => [
        'primary' => '#1976d2',
        'text' => '#000000',
        'meta' => '#424242',
        'bg' => '#ffffff',
        'border' => '#e0e0e0'
    ],
    'black' => [
        'primary' => '#64b5f6',
        'text' => '#ffffff',
        'meta' => '#e0e0e0',
        'bg' => '#212121',
        'border' => '#424242'
    ]
]);

// デフォルトサイズ
define('KSLC_DEFAULT_THUMBNAIL_WIDTH', 200);
define('KSLC_DEFAULT_THUMBNAIL_HEIGHT', 140);

// デフォルトキャッシュ期間（時間）
define('KSLC_DEFAULT_EXTERNAL_CACHE', 6);
define('KSLC_DEFAULT_INTERNAL_CACHE', 72);

// 選択可能なカラーテーマ / サムネイル位置（設定値のホワイトリスト）
define('KSLC_ALLOWED_COLOR_THEMES', ['red', 'blue', 'green', 'purple', 'orange', 'gray', 'white', 'black', 'custom']);
define('KSLC_ALLOWED_THUMBNAIL_POSITIONS', ['right', 'left']);

// サムネイルサイズの許容範囲（px）— 設定画面の min/max と一致させる
define('KSLC_THUMBNAIL_WIDTH_MIN', 100);
define('KSLC_THUMBNAIL_WIDTH_MAX', 400);
define('KSLC_THUMBNAIL_HEIGHT_MIN', 80);
define('KSLC_THUMBNAIL_HEIGHT_MAX', 300);

// 外部ページ取得の上限バイト数（OGP は <head> にあるため十分）
define('KSLC_MAX_RESPONSE_BYTES', 2 * 1024 * 1024);

// 代替画像の実在確認（HTTP リクエスト）を行う最大候補数
define('KSLC_MAX_IMAGE_CHECKS', 5);

// クリック計測の受付上限（IP アドレスあたり 1 分間の件数）
define('KSLC_CLICK_RATE_LIMIT', 30);

// ---- 1.1.0: 転送先への自動追随 / リンク切れ検知 / rel 自動付与 ----

// リダイレクト追随の上限回数（WP_Http の redirection 引数。超過やループは Requests が "Too many redirects" で打ち切り WP_Error になる）
define('KSLC_MAX_REDIRECTS', 5);

// 取得失敗（404・接続失敗など）を記憶しておく秒数。この間は同じ URL を表示のたびに取得しに行かない
define('KSLC_FAILURE_CACHE_SECONDS', HOUR_IN_SECONDS);

// リンク切れ一覧の最大保持件数（超えた分は最終確認日時の古いものから捨てる）
define('KSLC_BROKEN_LINKS_MAX', 500);

// 1 件のリンク切れに紐づけて記憶する掲載ページ数の上限
define('KSLC_BROKEN_LINK_MAX_PAGES', 20);

// 定期チェック 1 回の実行時間の目安（秒）。超えたら残りは 1 分後の単発イベントに引き継ぐ
define('KSLC_LINK_CHECK_TIME_BUDGET', 20);

// 定期チェックの HTTP タイムアウト（秒）
define('KSLC_LINK_CHECK_TIMEOUT', 8);

// 定期チェックの既定間隔（時間）と許容範囲
define('KSLC_DEFAULT_LINK_CHECK_INTERVAL', 24);
define('KSLC_LINK_CHECK_INTERVAL_MIN', 1);
define('KSLC_LINK_CHECK_INTERVAL_MAX', 720);

// rel 自動付与で選べる値（Google の「発リンクの修飾」: 広告・有料リンクは sponsored、それ以外で関連付けたくない場合は nofollow）
define('KSLC_ALLOWED_AUTO_REL_VALUES', ['sponsored', 'nofollow']);