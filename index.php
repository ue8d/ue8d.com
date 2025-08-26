<?php
require_once __DIR__ . '/db.php';
$pdo = pdo();

$profile = $pdo->query("SELECT * FROM profiles WHERE id=1")->fetch();
$links = $pdo->query("SELECT * FROM links WHERE is_active=1 ORDER BY position ASC, id ASC")->fetchAll();
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=htmlspecialchars($profile['display_name'] ?? 'Link in Bio',ENT_QUOTES)?> | @ue8d_</title>
<meta name="description" content="<?=htmlspecialchars(mb_strimwidth($profile['bio'] ?? '',0,120,'...', 'UTF-8'),ENT_QUOTES)?>">
<style>
:root{
  --bg: <?=htmlspecialchars($profile['theme_bg'] ?? '#ffffff')?>;
  --text: <?=htmlspecialchars($profile['theme_text'] ?? '#111111')?>;
  --btn: <?=htmlspecialchars($profile['theme_button'] ?? '#111111')?>;
  --btn-text: <?=htmlspecialchars($profile['theme_button_text'] ?? '#ffffff')?>;
  --muted:#666; --border:#e5e7eb;
}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--text);font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,Meiryo,sans-serif}
.container{max-width:560px;margin:0 auto;padding:24px}
.header{display:flex;flex-direction:column;align-items:center;gap:12px;margin:28px 0}
.avatar{width:96px;height:96px;border-radius:50%;object-fit:cover;border:2px solid var(--border);background:#fff}
.name{font-weight:700;font-size:1.3rem}
.username {font-size:0.9rem;color:#666;margin-top:2px;}
.username a {color:inherit;text-decoration:none;}
.username a:hover {text-decoration:underline;}
.bio{color:var(--muted);text-align:center;line-height:1.4;}
.links{display:flex;flex-direction:column;gap:12px;margin-top:20px}
a.btn{display:block;text-align:center;padding:14px 16px;border-radius:12px;text-decoration:none;background:var(--btn);color:var(--btn-text);border:1px solid transparent;transition:.15s}
a.btn:hover{transform:translateY(-1px)}
.footer{margin:40px 0;color:var(--muted);font-size:.9rem;text-align:center}
</style>
</head>
<body>
  <div class="container">
    <div class="header">
      <?php if (!empty($profile['avatar_url'])): ?>
        <img class="avatar" src="<?=htmlspecialchars($profile['avatar_url'],ENT_QUOTES)?>" alt="">
      <?php endif; ?>
      <div class="name"><?=htmlspecialchars($profile['display_name'] ?? 'あなたの名前',ENT_QUOTES)?></div>
      <div class="username">
          <a href="https://twitter.com/ue8d_" target="_blank" rel="noopener">@ue8d_</a>
      </div>
      <?php if (!empty($profile['bio'])): ?>
        <div class="bio"><?=nl2br(htmlspecialchars($profile['bio'],ENT_QUOTES))?></div>
      <?php endif; ?>
    </div>

    <div class="links">
      <?php foreach($links as $ln): ?>
        <a class="btn" href="r.php?id=<?=$ln['id']?>" rel="noopener" target="_blank">
          <?=htmlspecialchars($ln['title'],ENT_QUOTES)?>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="footer">
      <a href="admin.php" style="color:inherit;opacity:.6">編集</a>
    </div>
  </div>
</body>
</html>
