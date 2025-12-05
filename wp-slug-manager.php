<?php
/**
 * WordPress Post Slug Manager
 * 
 * WordPress投稿のスラッグを安全に一括変更するためのスクリプト
 * 
 * @version 1.0.0
 * @author Claude
 * @license MIT
 */

class WPSlugManager {
    
    private $pdo;
    private $wp_loaded = false;
    
    public function __construct() {
        $this->loadWordPress();
        $this->initDatabase();
    }
    
    /**
     * WordPressを読み込み
     */
    private function loadWordPress() {
        if (file_exists('wp-config.php')) {
            require_once('wp-config.php');
            if (file_exists('wp-load.php')) {
                require_once('wp-load.php');
                $this->wp_loaded = true;
                $this->log("WordPressが正常に読み込まれました");
            }
        } else {
            throw new Exception("wp-config.php が見つかりません。WordPressのルートディレクトリで実行してください。");
        }
    }
    
    /**
     * データベース接続を初期化
     */
    private function initDatabase() {
        try {
            $this->pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, 
                DB_USER, 
                DB_PASSWORD,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $this->log("データベース接続が確立されました");
        } catch (PDOException $e) {
            throw new Exception("データベース接続エラー: " . $e->getMessage());
        }
    }
    
    /**
     * ログ出力
     */
    private function log($message) {
        echo date('Y-m-d H:i:s') . " - " . $message . "\n";
    }
    
    /**
     * 警告メッセージ
     */
    private function warning($message) {
        echo "⚠️  警告: " . $message . "\n";
    }
    
    /**
     * エラーメッセージ
     */
    private function error($message) {
        echo "❌ エラー: " . $message . "\n";
    }
    
    /**
     * 成功メッセージ
     */
    private function success($message) {
        echo "✅ " . $message . "\n";
    }
    
    /**
     * データベースバックアップを作成
     */
    public function createBackup($backup_name = null) {
        if (!$backup_name) {
            $backup_name = 'slug_backup_' . date('Y-m-d_H-i-s') . '.sql';
        }
        
        $this->log("バックアップを作成中: {$backup_name}");
        
        // WP-CLIを使用してバックアップ
        $command = "wp db export " . escapeshellarg($backup_name);
        $output = [];
        $return_var = 0;
        
        exec($command, $output, $return_var);
        
        if ($return_var === 0) {
            $this->success("バックアップが作成されました: {$backup_name}");
            return $backup_name;
        } else {
            throw new Exception("バックアップの作成に失敗しました");
        }
    }
    
    /**
     * 特定のパターンにマッチするスラッグを検索
     */
    public function findSlugs($pattern, $post_type = 'post') {
        $sql = "SELECT ID, post_name, post_title FROM wp_posts WHERE post_name LIKE :pattern AND post_type = :post_type AND post_status != 'trash'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':pattern' => $pattern,
            ':post_type' => $post_type
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * スラッグの重複をチェック
     */
    public function checkDuplicates($new_slugs) {
        $placeholders = str_repeat('?,', count($new_slugs) - 1) . '?';
        $sql = "SELECT post_name FROM wp_posts WHERE post_name IN ({$placeholders}) AND post_status != 'trash'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($new_slugs));
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * 正規表現を使用してスラッグを変換
     */
    public function transformSlugWithRegex($pattern, $replacement, $post_type = 'post', $dry_run = true) {
        $posts = $this->findSlugs('%', $post_type);
        $changes = [];
        $errors = [];
        
        foreach ($posts as $post) {
            $original_slug = $post['post_name'];
            $new_slug = preg_replace($pattern, $replacement, $original_slug);
            
            if ($new_slug !== $original_slug) {
                if (empty($new_slug)) {
                    $errors[] = "ID {$post['ID']}: スラッグが空になります ({$original_slug})";
                    continue;
                }
                
                $changes[] = [
                    'id' => $post['ID'],
                    'original' => $original_slug,
                    'new' => $new_slug,
                    'title' => $post['post_title']
                ];
            }
        }
        
        if (!empty($errors)) {
            $this->warning("以下のエラーが検出されました:");
            foreach ($errors as $error) {
                echo "  - " . $error . "\n";
            }
        }
        
        if (empty($changes)) {
            $this->log("変更対象のスラッグが見つかりませんでした");
            return [];
        }
        
        // 重複チェック
        $new_slugs = array_column($changes, 'new');
        $duplicates = $this->checkDuplicates($new_slugs);
        
        if (!empty($duplicates)) {
            $this->warning("以下のスラッグは既に存在するため、重複が発生します:");
            foreach ($duplicates as $duplicate) {
                echo "  - " . $duplicate . "\n";
            }
        }
        
        if ($dry_run) {
            $this->log("ドライラン - 変更予定 (" . count($changes) . "件):");
            foreach ($changes as $change) {
                echo "  ID {$change['id']}: '{$change['original']}' → '{$change['new']}'\n";
                echo "    タイトル: {$change['title']}\n";
            }
            return $changes;
        }
        
        // 実際の更新を実行
        return $this->executeChanges($changes);
    }
    
    /**
     * 文字列置換でスラッグを変更
     */
    public function replaceInSlugs($search, $replace, $post_type = 'post', $dry_run = true) {
        $pattern = '%' . $search . '%';
        $posts = $this->findSlugs($pattern, $post_type);
        $changes = [];
        
        foreach ($posts as $post) {
            $original_slug = $post['post_name'];
            $new_slug = str_replace($search, $replace, $original_slug);
            
            if ($new_slug !== $original_slug) {
                $changes[] = [
                    'id' => $post['ID'],
                    'original' => $original_slug,
                    'new' => $new_slug,
                    'title' => $post['post_title']
                ];
            }
        }
        
        if (empty($changes)) {
            $this->log("変更対象のスラッグが見つかりませんでした");
            return [];
        }
        
        // 重複チェック
        $new_slugs = array_column($changes, 'new');
        $duplicates = $this->checkDuplicates($new_slugs);
        
        if (!empty($duplicates)) {
            $this->warning("以下のスラッグは既に存在するため、重複が発生します:");
            foreach ($duplicates as $duplicate) {
                echo "  - " . $duplicate . "\n";
            }
        }
        
        if ($dry_run) {
            $this->log("ドライラン - 変更予定 (" . count($changes) . "件):");
            foreach ($changes as $change) {
                echo "  ID {$change['id']}: '{$change['original']}' → '{$change['new']}'\n";
                echo "    タイトル: {$change['title']}\n";
            }
            return $changes;
        }
        
        return $this->executeChanges($changes);
    }
    
    /**
     * 実際にスラッグの変更を実行
     */
    private function executeChanges($changes) {
        $this->log("スラッグの変更を開始します (" . count($changes) . "件)...");
        
        $success_count = 0;
        $error_count = 0;
        
        foreach ($changes as $change) {
            try {
                $sql = "UPDATE wp_posts SET post_name = :new_slug WHERE ID = :id";
                $stmt = $this->pdo->prepare($sql);
                $result = $stmt->execute([
                    ':new_slug' => $change['new'],
                    ':id' => $change['id']
                ]);
                
                if ($result) {
                    $success_count++;
                    $this->log("✓ ID {$change['id']}: '{$change['original']}' → '{$change['new']}'");
                } else {
                    $error_count++;
                    $this->error("ID {$change['id']}の更新に失敗");
                }
                
                // 負荷軽減のため小休止
                usleep(10000); // 10ms
                
            } catch (Exception $e) {
                $error_count++;
                $this->error("ID {$change['id']}: " . $e->getMessage());
            }
        }
        
        $this->success("完了: 成功 {$success_count}件, エラー {$error_count}件");
        
        // WordPressキャッシュをクリア
        if ($this->wp_loaded && function_exists('wp_cache_flush')) {
            wp_cache_flush();
            $this->log("キャッシュをクリアしました");
        }
        
        return [
            'success' => $success_count,
            'errors' => $error_count,
            'changes' => $changes
        ];
    }
    
    /**
     * パーマリンクをフラッシュ
     */
    public function flushRewrite() {
        if ($this->wp_loaded) {
            // WP-CLIを使用
            $command = "wp rewrite flush";
            $output = [];
            $return_var = 0;
            
            exec($command, $output, $return_var);
            
            if ($return_var === 0) {
                $this->success("パーマリンクルールをフラッシュしました");
            } else {
                $this->warning("パーマリンクのフラッシュに失敗しました");
            }
        }
    }
    
    /**
     * スラッグ統計を表示
     */
    public function showStats($post_type = 'post') {
        $sql = "SELECT 
                    COUNT(*) as total_posts,
                    COUNT(CASE WHEN post_name LIKE '%-2' THEN 1 END) as with_dash_2,
                    COUNT(CASE WHEN post_name REGEXP '[0-9]+-[0-9]+' THEN 1 END) as with_numbers,
                    COUNT(CASE WHEN LENGTH(post_name) > 50 THEN 1 END) as long_slugs
                FROM wp_posts 
                WHERE post_type = :post_type AND post_status != 'trash'";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':post_type' => $post_type]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->log("=== スラッグ統計 ({$post_type}) ===");
        echo "総投稿数: {$stats['total_posts']}\n";
        echo "'-2'で終わるスラッグ: {$stats['with_dash_2']}\n";
        echo "数字-数字パターン: {$stats['with_numbers']}\n";
        echo "50文字以上の長いスラッグ: {$stats['long_slugs']}\n";
        echo "========================\n";
    }
}

// スクリプトが直接実行された場合の処理
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    try {
        echo "=== WordPress Post Slug Manager ===\n\n";
        
        $manager = new WPSlugManager();
        
        // コマンドライン引数の解析
        $options = getopt('', [
            'action:', 'pattern:', 'replacement:', 'search:', 'replace:', 
            'post-type:', 'backup:', 'dry-run', 'help'
        ]);
        
        if (isset($options['help'])) {
            echo "使用方法:\n";
            echo "  php wp-slug-manager.php --action=stats\n";
            echo "  php wp-slug-manager.php --action=replace --search='-2' --replace='' --backup=auto\n";
            echo "  php wp-slug-manager.php --action=regex --pattern='/(.+)-2$/' --replacement='$1' --dry-run\n";
            echo "\nオプション:\n";
            echo "  --action        実行するアクション (stats|replace|regex)\n";
            echo "  --search        検索する文字列\n";
            echo "  --replace       置換する文字列\n";
            echo "  --pattern       正規表現パターン\n";
            echo "  --replacement   正規表現置換文字列\n";
            echo "  --post-type     投稿タイプ (デフォルト: post)\n";
            echo "  --backup        バックアップファイル名 (auto で自動生成)\n";
            echo "  --dry-run       実際の変更を行わず、プレビューのみ\n";
            echo "  --help          このヘルプを表示\n";
            exit;
        }
        
        $action = $options['action'] ?? 'stats';
        $post_type = $options['post-type'] ?? 'post';
        $dry_run = isset($options['dry-run']);
        
        // バックアップ作成
        if (isset($options['backup'])) {
            $backup_name = $options['backup'] === 'auto' ? null : $options['backup'];
            $manager->createBackup($backup_name);
            echo "\n";
        }
        
        switch ($action) {
            case 'stats':
                $manager->showStats($post_type);
                break;
                
            case 'replace':
                if (!isset($options['search'])) {
                    throw new Exception("--search オプションが必要です");
                }
                $search = $options['search'];
                $replace = $options['replace'] ?? '';
                
                $result = $manager->replaceInSlugs($search, $replace, $post_type, $dry_run);
                
                if (!$dry_run && !empty($result['success'])) {
                    $manager->flushRewrite();
                }
                break;
                
            case 'regex':
                if (!isset($options['pattern']) || !isset($options['replacement'])) {
                    throw new Exception("--pattern と --replacement オプションが必要です");
                }
                $pattern = $options['pattern'];
                $replacement = $options['replacement'];
                
                $result = $manager->transformSlugWithRegex($pattern, $replacement, $post_type, $dry_run);
                
                if (!$dry_run && !empty($result['success'])) {
                    $manager->flushRewrite();
                }
                break;
                
            default:
                throw new Exception("不明なアクション: {$action}");
        }
        
    } catch (Exception $e) {
        echo "❌ エラー: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>