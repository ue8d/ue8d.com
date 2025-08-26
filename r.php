<?php
require_once __DIR__ . '/db.php';
$pdo = pdo();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT url FROM links WHERE id=? AND is_active=1");
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    exit('Not found');
}

// ログ保存
try {
    $stmt = $pdo->prepare("INSERT INTO link_clicks (link_id, ip, ua, referer) VALUES (?,?,?,?)");
    $stmt->execute([
        $id,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
        $_SERVER['HTTP_REFERER'] ?? null
    ]);
} catch (Throwable $e) {
    // ログに失敗しても転送は行う
}

// リダイレクト
header('Location: ' . $row['url'], true, 302);
exit;
