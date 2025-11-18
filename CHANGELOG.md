# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
