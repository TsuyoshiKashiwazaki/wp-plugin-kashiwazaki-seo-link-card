# Kashiwazaki SEO Link Card

![WordPress](https://img.shields.io/badge/WordPress-5.5%2B-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.0%2B-blue.svg)
![License](https://img.shields.io/badge/license-GPL--2.0%2B-green.svg)
![Version](https://img.shields.io/badge/version-1.0.4-blue.svg)

URLを記述するだけで、ページの情報を取得してカード形式で表示するWordPressプラグインです。OGPデータの自動取得、内部リンク最適化、クリックトラッキング、カスタマイズ可能なデザインなど、豊富な機能を搭載しています。

## 主要機能

### 1. 自動OGPデータ取得
- 外部サイトからOGP（Open Graph Protocol）データを自動取得
- タイトル、ディスクリプション、サムネイル画像を美しいカードで表示
- OGP画像がない場合の高度なフォールバック機能
  - Twitter Card、meta タグ、favicon の自動検索
  - ヘッダー・ロゴエリアの画像を優先的に取得
  - 本文画像をサイズで判定して最適なものを選択

### 2. 内部リンク最適化
- WordPressデータベースから直接データ取得（高速・正確）
- SEOプラグイン対応（Yoast SEO、All in One SEO）
- アイキャッチ画像の自動取得
- カスタム抜粋・自動生成抜粋の対応

### 3. インテリジェントキャッシュシステム
- 外部リンク・内部リンクで異なるキャッシュ期間
  - 外部リンク：デフォルト 6時間
  - 内部リンク：デフォルト 72時間
- 管理画面からキャッシュ期間のカスタマイズ可能
- ワンクリックでキャッシュクリア機能

### 4. クリックトラッキング＆アナリティクス
- JavaScriptによる非侵襲的なクリック追跡
- データベースへのクリックログ保存
- 管理画面でのアナリティクス表示
  - リンクごとのクリック数
  - ページごとの集計
  - 期間絞り込み機能

### 5. 柔軟なデザインカスタマイズ
#### カラーテーマ
- **8種類のプリセットテーマ**：赤、青、緑、紫、オレンジ、灰色、白、黒
- **カスタムカラー機能**：カラーピッカーで自由に色を設定
- **外部リンク・内部リンクで個別設定可能**

#### サムネイル設定
- 表示/非表示の切り替え
- 位置設定（左/右）
- サイズのカスタマイズ（幅・高さ）
- 外部リンク・内部リンクで個別設定可能

#### バッジ機能
- 「外部リンク」「内部リンク」バッジの自動表示
- 表示/非表示の切り替え可能
- サムネイル上への配置で視認性向上

### 6. SEO対策
- `<blockquote cite="URL">` タグで引用を明示
- 適切なセマンティックHTML構造
- target="_blank" には自動的に rel="noopener" を追加

### 7. 開発者向け機能
#### REST API
- カスタムエンドポイント `/kslc/v1/all-posts`
- 投稿タイプフィルター、検索機能
- エディタ統合用のデータ取得

#### ブロックパターン
- Gutenbergブロックパターンの登録
- ブロックエディタでの簡単挿入

## インストール

### 手動インストール

1. このリポジトリをダウンロード
```bash
git clone https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-link-card.git
```

2. WordPressの `/wp-content/plugins/` ディレクトリにアップロード
```bash
cd wp-content/plugins/
mv /path/to/wp-plugin-kashiwazaki-seo-link-card kashiwazaki-seo-link-card
```

3. WordPress管理画面のプラグインメニューから有効化

4. 設定メニュー「Kashiwazaki SEO Link Card」から設定を調整

## 使い方

### 基本的なショートコード

#### 外部リンク
```
[linkcard url="https://example.com"]
```

#### 内部リンク（投稿ID指定）
```
[linkcard post_id="123"]
```

#### カスタムタイトル
```
[linkcard url="https://example.com" title="カスタムタイトル"]
```

#### target と rel 属性のカスタマイズ
```
[linkcard url="https://example.com" target="_blank" rel="nofollow"]
```

### 利用可能なショートコード
- `[linkcard]` - 推奨
- `[kashiwazaki_seo_link_card]` - 正式名
- `[nlink]` - 短縮版

### ショートコード属性

| 属性 | 説明 | デフォルト | 例 |
|------|------|-----------|-----|
| `url` | リンク先URL（外部リンク用） | - | `url="https://example.com"` |
| `post_id` | 投稿ID（内部リンク用） | 0 | `post_id="123"` |
| `title` | カスタムタイトル | OGPから取得 | `title="記事タイトル"` |
| `target` | リンクターゲット | 外部:`_blank`<br>内部:なし | `target="_blank"` |
| `rel` | rel属性 | 外部:`noopener` | `rel="nofollow"` |

## 管理画面での設定

WordPress管理画面の「設定」→「Kashiwazaki SEO Link Card」から以下の設定が可能です：

### デザイン設定

#### 外部リンク設定
- **カラーテーマ**：8種類のプリセット + カスタムカラー
- **サムネイル表示**：オン/オフ
- **サムネイル位置**：左 or 右
- **バッジ表示**：オン/オフ

#### 内部リンク設定
- **カラーテーマ**：8種類のプリセット + カスタムカラー
- **サムネイル表示**：オン/オフ
- **サムネイル位置**：左 or 右
- **バッジ表示**：オン/オフ

#### サムネイルサイズ
- **幅**：デフォルト 200px
- **高さ**：デフォルト 140px

### キャッシュ設定
- **外部リンクキャッシュ期間**：デフォルト 6時間
- **内部リンクキャッシュ期間**：デフォルト 72時間
- **キャッシュクリア**：ワンクリックで全キャッシュをクリア

### アナリティクス
「リンク統計」サブメニューから以下の情報を確認できます：
- リンクごとのクリック数
- 掲載ページごとの集計
- クリック日時の詳細ログ
- 期間フィルタリング

## 技術仕様

### 動作環境
- **WordPress**: 5.5以上（ブロックパターン機能を使用）
- **PHP**: 7.0以上（推奨）、5.4以上で動作可能

### 技術的詳細
- PHP 5.4以上で動作（短配列構文使用）
- セキュリティサポートのためPHP 7.0以上を推奨
- WordPress 5.5のブロックパターンAPI使用
- REST API対応

### 使用技術
- **フロントエンド**：jQuery、Vanilla JavaScript
- **バックエンド**：PHP、WordPress API
- **データベース**：カスタムテーブル（`wp_kslc_analytics`）
- **キャッシュ**：WordPress Transients API
- **HTTP**：WordPress HTTP API

### データベーステーブル

プラグイン有効化時に自動作成されます：

```sql
wp_kslc_analytics
├── id (bigint) - 主キー
├── url (varchar) - リンク先URL
├── page_url (varchar) - 掲載ページURL
├── ip_address (varchar) - アクセス元IP
├── user_agent (text) - ユーザーエージェント
├── title (varchar) - リンクタイトル
└── clicked_at (datetime) - クリック日時
```

## カスタマイズ

### 定数によるカスタマイズ

`wp-config.php` または テーマの `functions.php` で以下の定数を定義できます：

```php
// デバッグログを有効化
define('KSLC_ENABLE_DEBUG_LOGS', true);

// 出力バッファハンドリングを完全無効化
define('KSLC_DISABLE_OUTPUT_BUFFER_HANDLING', true);
```

### フィルターフック

今後のバージョンでカスタムフィルターフックを追加予定です。

## トラブルシューティング

### キャッシュが更新されない
管理画面の「キャッシュをクリア」ボタンをクリックしてください。

### 画像が表示されない
1. 外部サイトのOGP設定を確認
2. サムネイル表示設定がオンになっているか確認
3. ファイアウォールがOGP画像URLをブロックしていないか確認

### クリック統計が記録されない
1. JavaScriptが有効になっているか確認
2. AJAX URLが正しく設定されているか確認（ブラウザのコンソールでエラーを確認）
3. データベーステーブルが作成されているか確認

### プラグインを再有効化
プラグインを一度無効化し、再度有効化することでデータベーステーブルが再作成されます。

## セキュリティ

- WordPress Nonce によるCSRF対策
- サニタイゼーション・エスケープ処理の徹底
- SQLインジェクション対策（prepared statements使用）
- XSS対策（`esc_html`, `esc_url`, `esc_attr` の使用）

## パフォーマンス

- トランジェントAPIによる効率的なキャッシュ
- 内部リンクはデータベース直接取得（HTTP不要）
- 外部リンクはタイムアウト設定（10秒）
- 画像検索は最初の10個のみスキャン（過負荷防止）

## ライセンス

GPL-2.0 or later

このプラグインはフリーソフトウェアです。GNU General Public License v2以降の条件の下で再配布および変更が可能です。

## 作者

**柏崎剛 (Tsuyoshi Kashiwazaki)**
- Website: https://www.tsuyoshikashiwazaki.jp
- Profile: https://www.tsuyoshikashiwazaki.jp/profile/
- Email: t.kashiwazaki@contencial.co.jp

## サポート

バグ報告や機能リクエストは、GitHubのIssuesページでお願いします。

https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-link-card/issues

## 変更履歴

### [1.0.4] - 2025-12-10
- **追加**: OGP画像が取得できない場合のフォールバックとしてGoogle Favicon APIを使用

### [1.0.3] - 2025-12-09
- **修正**: post_id指定時に内部リンクデータが取得できないバグを修正
- **修正**: 相対URL（/path/to/page/）が処理できないバグを修正
- **修正**: リンクタイプ切替時にデータが消えるバグを修正
- **追加**: ブロックエディタに「設定をクリア」ボタンを追加

### [1.0.2] - 2025-12-08
- **修正**: 外部サイトの相対パスOGP画像の絶対パス変換処理を追加

### [1.0.1] - 2025-01-18
- **修正**: URLエンコード処理の改善（特殊文字を含むURLへの対応）
- **修正**: parse_url()エラー処理の追加（不正なURL形式への対応）
- **修正**: キャッシュクリア機能の実装（管理画面から正常に動作）
- **改善**: 外部サイトリクエストヘッダーの最適化（互換性向上）
- **改善**: User-Agent設定の一元管理とフィルターフック追加

詳細は [CHANGELOG.md](CHANGELOG.md) を参照してください。

## 貢献

プルリクエストを歓迎します！大きな変更の場合は、まずissueを開いて変更内容を議論してください。

## クレジット

このプラグインは WordPress コミュニティの素晴らしいツールとドキュメントに基づいて開発されました。
