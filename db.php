<?php
require_once __DIR__ . '/config/config.php';

// ---- デバッグ制御（config.php に無ければ false 扱い）
if (!defined('APP_DEBUG')) { define('APP_DEBUG', false); }

// ---- ログ先（書き込み可の場所に調整）
// さくらの例: __DIR__ . '/app.log' は公開領域なので、可能なら web 外へ
$APP_LOG = __DIR__ . '/app.log';

// ---- 例外をログして適切に返す
function fail_500(string $msg, Throwable $e = null): void {
    global $APP_LOG;
    $line = '['.date('c')."] $msg";
    if ($e) { $line .= ' | '.$e->getMessage(); }
    $line .= "\n";
    // ログ出力（失敗は握りつぶし）
    @file_put_contents($APP_LOG, $line, FILE_APPEND);

    http_response_code(500);
    if (APP_DEBUG) {
        // 画面にも詳細（公開時は OFF 推奨）
        echo "500 Internal Error\n".$msg.($e?("\n".$e->getMessage()):'');
    }
    exit;
}

function pdo(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
    $opt = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opt);
    } catch (Throwable $e) {
        fail_500('DB接続に失敗しました。config.php の値/DB権限/ホスト名/バージョンを確認してください。', $e);
    }

    try {
        init_schema($pdo);
    } catch (Throwable $e) {
        fail_500('初期スキーマ作成でエラーになりました。テーブル権限や既存テーブルの状態を確認してください。', $e);
    }
    return $pdo;
}

function init_schema(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS profiles (
            id INT PRIMARY KEY AUTO_INCREMENT,
            display_name VARCHAR(100) DEFAULT NULL,
            bio TEXT DEFAULT NULL,
            avatar_url VARCHAR(255) DEFAULT NULL,
            theme_bg VARCHAR(20) DEFAULT '#ffffff',
            theme_text VARCHAR(20) DEFAULT '#111111',
            theme_button VARCHAR(20) DEFAULT '#111111',
            theme_button_text VARCHAR(20) DEFAULT '#ffffff',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS links (
            id INT PRIMARY KEY AUTO_INCREMENT,
            title VARCHAR(200) NOT NULL,
            url VARCHAR(500) NOT NULL,
            position INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS link_clicks (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            link_id INT NOT NULL,
            clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ip VARCHAR(45) DEFAULT NULL,
            ua TEXT DEFAULT NULL,
            referer TEXT DEFAULT NULL,
            INDEX (link_id),
            CONSTRAINT fk_link_clicks_link_id FOREIGN KEY (link_id) REFERENCES links(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $exists = $pdo->query("SELECT COUNT(*) AS c FROM profiles")->fetch()['c'] ?? 0;
    if ((int)$exists === 0) {
        $stmt = $pdo->prepare("INSERT INTO profiles (display_name, bio, avatar_url) VALUES (?,?,?)");
        $stmt->execute(['あなたの名前', '自己紹介をここに', null]);
    }
}

// ---- CSRF / ログイン（変更なし）
function csrf_token(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function check_csrf(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
        http_response_code(400);
        exit('Bad CSRF token');
    }
}
function is_logged_in(): bool {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    return !empty($_SESSION['logged_in']);
}
function require_login(): void {
    if (!is_logged_in()) {
        header('Location: admin.php');
        exit;
    }
}
