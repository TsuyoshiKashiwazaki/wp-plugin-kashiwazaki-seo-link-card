# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.6] - 2025-12-24

### Added
- スクレイピング失敗時の簡易デコレーションパネル機能（URLのみの場合でも見栄えの良いカードを表示）

## [1.0.5] - 2025-12-23

### Added
- OGP画像URLの有効性チェック機能（200以外のレスポンスの場合はフォールバック画像を使用）

### Fixed
- ブロックエディタの記事検索がタイトル部分一致で正しく動作するように修正（WordPressデフォルト検索からLIKE検索に変更）

## [1.0.4] - 2025-12-10

### Added
- OGP画像が取得できない場合のフォールバックとしてGoogle Favicon APIを使用（SPA等でog:imageがないサイトでもfaviconを表示）

## [1.0.3] - 2025-12-09

### Fixed
- post_id指定時に内部リンクデータが取得できないバグを修正（kslc_get_ogp_dataにpost_idを渡すように変更）
- 相対URL（/path/to/page/）が処理できないバグを修正（home_url()で絶対URLに変換）
- ブロックエディタでリンクタイプ切替時にデータが消えるバグを修正

### Added
- ブロックエディタに「設定をクリア」ボタンを追加

## [1.0.2] - 2025-12-08

### Fixed
- 外部サイトの相対パスOGP画像の絶対パス変換処理を追加 (e.g. npa.go.jp)

## [1.0.1] - 2025-01-18

### Fixed
- URLエンコード処理の改善（`esc_url()` から `sanitize_url()` へ変更）
- 特殊文字を含むURLの正しい処理
- `parse_url()` 失敗時のエラーハンドリング追加
- キャッシュクリア機能の実装（管理画面から正常に動作）

### Improved
- 外部サイトへのリクエストヘッダーの最適化
- User-Agentの一元管理（定数`KSLC_USER_AGENT`追加）
- フィルターフック追加（`kslc_request_user_agent`, `kslc_request_timeout`, `kslc_request_headers`）
- タイムアウト設定の延長（10秒 → 15秒）

## [1.0.0] - 2025-11-07

### Added
- 初回リリース
- ショートコード `[linkcard url="..."]` によるリンクカード表示機能
- OGPデータの自動取得機能
- サムネイル、タイトル、ディスクリプションの表示
- 外部リンクと内部リンクの自動判別
- カスタマイズ可能なカラーテーマ（ブルー、グリーン、レッド、グレー、カスタム）
- サムネイル表示のオン/オフ設定
- サムネイル位置の設定（左/右）
- バッジ表示のオン/オフ設定
- キャッシュ機能（外部リンク6時間、内部リンク72時間のデフォルト設定）
- 管理画面でのキャッシュクリア機能
- クリック数のアナリティクス機能
- データベーステーブルによるクリックログの保存
- REST API エンドポイントによるクリック追跡
- 管理画面でのアナリティクスデータ表示
- サムネイルサイズのカスタマイズ機能
- カスタムカラーピッカー機能
- ブロックパターンの登録
