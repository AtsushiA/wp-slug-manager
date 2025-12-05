# WordPress Post Slug Manager - 使用例

## 概要

`wp-slug-manager.php` は WordPress の投稿スラッグを安全に一括変更するためのスクリプトです。

## 主な機能

- **安全なバックアップ機能**: 変更前に自動でデータベースをバックアップ
- **ドライラン機能**: 実際の変更前にプレビュー確認
- **重複チェック**: スラッグの重複を事前に検出
- **正規表現サポート**: 複雑なパターンマッチングと置換
- **統計表示**: スラッグの状況を分析

## 基本的な使用方法

### 1. スラッグ統計を表示

```bash
php wp-slug-manager.php --action=stats
```

出力例：
```
=== スラッグ統計 (post) ===
総投稿数: 540
'-2'で終わるスラッグ: 532
数字-数字パターン: 123
50文字以上の長いスラッグ: 5
========================
```

### 2. 文字列置換（ドライラン）

```bash
php wp-slug-manager.php --action=replace --search="-2" --replace="" --dry-run
```

### 3. 文字列置換（実際の実行 + バックアップ）

```bash
php wp-slug-manager.php --action=replace --search="-2" --replace="" --backup=auto
```

### 4. 正規表現を使った置換

```bash
# "-2"で終わるスラッグから"-2"を削除
php wp-slug-manager.php --action=regex --pattern="/(.+)-2$/" --replacement='$1' --backup=auto
```

### 5. カスタム投稿タイプでの実行

```bash
php wp-slug-manager.php --action=stats --post-type=page
php wp-slug-manager.php --action=replace --search="old-" --replace="new-" --post-type=product --dry-run
```

## 実用的な使用例

### ケース1: 重複スラッグの"-2"を削除

```bash
# まず統計を確認
php wp-slug-manager.php --action=stats

# ドライランで確認
php wp-slug-manager.php --action=regex --pattern="/(.+)-2$/" --replacement='$1' --dry-run

# バックアップを取って実行
php wp-slug-manager.php --action=regex --pattern="/(.+)-2$/" --replacement='$1' --backup=auto
```

### ケース2: プレフィックスの変更

```bash
# "old-prefix-" を "new-prefix-" に変更
php wp-slug-manager.php --action=replace --search="old-prefix-" --replace="new-prefix-" --backup=auto
```

### ケース3: 日付フォーマットの統一

```bash
# "2023-12-31-title" を "20231231-title" に変更
php wp-slug-manager.php --action=regex --pattern="/(\d{4})-(\d{2})-(\d{2})-(.+)/" --replacement='$1$2$3-$4' --dry-run
```

### ケース4: 特殊文字の削除

```bash
# アンダースコアをハイフンに変更
php wp-slug-manager.php --action=replace --search="_" --replace="-" --backup=auto
```

## オプション詳細

| オプション | 説明 | 必須 | デフォルト |
|-----------|------|------|-----------|
| `--action` | 実行するアクション (stats/replace/regex) | ✓ | stats |
| `--search` | 検索する文字列 | replace時のみ | - |
| `--replace` | 置換する文字列 | replace時のみ | 空文字 |
| `--pattern` | 正規表現パターン | regex時のみ | - |
| `--replacement` | 正規表現置換文字列 | regex時のみ | - |
| `--post-type` | 投稿タイプ | - | post |
| `--backup` | バックアップファイル名（autoで自動生成） | - | - |
| `--dry-run` | プレビューのみ実行 | - | false |
| `--help` | ヘルプを表示 | - | - |

## 安全性について

### 自動バックアップ
```bash
# 自動でバックアップファイルが生成されます
--backup=auto
# 例: slug_backup_2023-11-19_14-30-15.sql
```

### カスタムバックアップ名
```bash
--backup="before-slug-cleanup.sql"
```

### ドライラン機能
実際の変更前に必ずドライランで確認することを推奨：
```bash
php wp-slug-manager.php --action=replace --search="-2" --replace="" --dry-run
```

## 正規表現の例

### よく使用されるパターン

1. **末尾の"-2"を削除**
   ```bash
   --pattern="/(.+)-2$/" --replacement='$1'
   ```

2. **数字の連番を削除**
   ```bash
   --pattern="/(.+)-\d+$/" --replacement='$1'
   ```

3. **日付フォーマットの変更**
   ```bash
   --pattern="/(\d{4})-(\d{2})-(\d{2})-(.+)/" --replacement='$1$2$3-$4'
   ```

4. **プレフィックスの追加**
   ```bash
   --pattern="/^(.+)$/" --replacement='blog-$1'
   ```

## トラブルシューティング

### エラー: wp-config.php が見つかりません
WordPressのルートディレクトリで実行してください。

### エラー: データベース接続エラー
wp-config.phpのデータベース設定を確認してください。

### 重複スラッグの警告
変更後のスラッグが既存のものと重複する場合、警告が表示されます。必要に応じてパターンを調整してください。

## 注意事項

1. **必ずバックアップを取る**: `--backup=auto` オプションの使用を強く推奨
2. **ドライランで確認**: 実際の変更前に `--dry-run` で結果を確認
3. **大量データの場合**: 一度に大量のスラッグを変更する際は、サーバーの負荷に注意
4. **キャッシュクリア**: スクリプト実行後、WordPressキャッシュが自動でクリアされます
5. **パーマリンク更新**: 変更後、パーマリンクルールが自動でフラッシュされます

## ライセンス

MIT License