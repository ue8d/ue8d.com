<?php
require_once __DIR__ . '/db.php';
session_start();

// ====== ログイン/ログアウト ======
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    if (!hash_equals($_POST['csrf'] ?? '', csrf_token())) { exit('Bad CSRF'); }
    $user = trim($_POST['user'] ?? '');
    $pass = $_POST['pass'] ?? '';
    if ($user === 'admin' && password_verify($pass, ADMIN_PASSWORD_HASH)) {
        $_SESSION['logged_in'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $error = 'ログインに失敗しました';
    }
}
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: admin.php');
    exit;
}

// ====== 未ログインならログイン画面 ======
if (!is_logged_in()):
?>
<!doctype html>
<html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>管理ログイン</title>
<style>
body{font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,Meiryo,sans-serif;background:#f3f4f6;margin:0}
.card{max-width:420px;margin:10vh auto;background:#fff;border-radius:10px;padding:24px;box-shadow:0 10px 25px rgba(0,0,0,.08)}
label{display:block;margin:.5rem 0 .25rem}
input[type=text],input[type=password]{width:100%;padding:.7rem;border:1px solid #ddd;border-radius:8px}
button{padding:.7rem 1rem;border:0;border-radius:8px}
.primary{background:#111;color:#fff}
.error{color:#b00020;margin:.5rem 0}
</style>
</head><body>
<div class="card">
  <h2>管理ログイン</h2>
  <?php if (!empty($error)): ?><div class="error"><?=htmlspecialchars($error,ENT_QUOTES)?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?=csrf_token()?>">
    <input type="hidden" name="action" value="login">
    <label>ユーザー名</label>
    <input type="text" name="user" value="admin" autocomplete="username">
    <label>パスワード</label>
    <input type="password" name="pass" autocomplete="current-password">
    <div style="margin-top:12px"><button class="primary">ログイン</button></div>
  </form>
</div>
</body></html>
<?php exit; endif;

// ====== 以降は編集UI ======
$pdo = pdo();

// 保存系
if (($_POST['act'] ?? '') === 'save_profile') {
    check_csrf();
    $stmt = $pdo->prepare("UPDATE profiles SET display_name=?, bio=?, avatar_url=?, theme_bg=?, theme_text=?, theme_button=?, theme_button_text=? WHERE id=1");
    $stmt->execute([
        $_POST['display_name'] ?? null,
        $_POST['bio'] ?? null,
        $_POST['avatar_url'] ?? null,
        $_POST['theme_bg'] ?? '#ffffff',
        $_POST['theme_text'] ?? '#111111',
        $_POST['theme_button'] ?? '#111111',
        $_POST['theme_button_text'] ?? '#ffffff',
    ]);
    header('Location: admin.php?saved=1#profile');
    exit;
}
if (($_POST['act'] ?? '') === 'add_link') {
    check_csrf();
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(position),0)+1 AS p FROM links");
    $next = (int)$stmt->fetch()['p'];
    $stmt = $pdo->prepare("INSERT INTO links (title,url,position,is_active) VALUES (?,?,?,1)");
    $stmt->execute([trim($_POST['title']), trim($_POST['url']), $next]);
    header('Location: admin.php#links');
    exit;
}
if (($_POST['act'] ?? '') === 'update_link') {
    check_csrf();
    $stmt = $pdo->prepare("UPDATE links SET title=?, url=?, is_active=? WHERE id=?");
    $stmt->execute([
        trim($_POST['title']),
        trim($_POST['url']),
        isset($_POST['is_active']) ? 1 : 0,
        (int)$_POST['id']
    ]);
    header('Location: admin.php#links');
    exit;
}
if (($_POST['act'] ?? '') === 'delete_link') {
    check_csrf();
    $stmt = $pdo->prepare("DELETE FROM links WHERE id=?");
    $stmt->execute([(int)$_POST['id']]);
    header('Location: admin.php#links');
    exit;
}
if (($_POST['act'] ?? '') === 'reorder') {
    check_csrf();
    // 受け取り形式: order= "12,7,3,..." （上=小さいposition）
    $ids = array_filter(array_map('intval', explode(',', $_POST['order'] ?? '')));
    $pdo->beginTransaction();
    $pos = 1;
    $stmt = $pdo->prepare("UPDATE links SET position=? WHERE id=?");
    foreach ($ids as $id) {
        $stmt->execute([$pos++, $id]);
    }
    $pdo->commit();
    header('Location: admin.php#links');
    exit;
}

// 表示用データ
$profile = $pdo->query("SELECT * FROM profiles WHERE id=1")->fetch();
$links = $pdo->query("SELECT * FROM links ORDER BY position ASC, id ASC")->fetchAll();
$stats = $pdo->query("SELECT l.id, l.title, COUNT(c.id) AS clicks
                      FROM links l LEFT JOIN link_clicks c ON c.link_id = l.id
                      GROUP BY l.id, l.title ORDER BY clicks DESC")->fetchAll();
?>
<!doctype html>
<html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>管理ダッシュボード</title>
<style>
:root { --bg:#f6f7f9; --card:#fff; --text:#111; --muted:#666; --border:#e5e7eb; }
*{box-sizing:border-box} body{font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,Meiryo,sans-serif;margin:0;background:var(--bg);color:var(--text)}
.header{display:flex;justify-content:space-between;align-items:center;padding:16px 20px;background:#fff;border-bottom:1px solid var(--border)}
.container{max-width:980px;margin:20px auto;padding:0 16px}
.card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:16px}
h2{margin:.2rem 0 1rem}
label{display:block;margin:.5rem 0 .25rem}
input[type=text], input[type=url], textarea, input[type=color]{width:100%;padding:.6rem;border:1px solid var(--border);border-radius:8px}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.btn{display:inline-block;padding:.5rem .8rem;border-radius:8px;border:1px solid var(--border);background:#fff}
.btn.primary{background:#111;color:#fff;border-color:#111}
.list{list-style:none;margin:0;padding:0}
.item{display:flex;gap:12px;align-items:center;border:1px solid var(--border);padding:10px;border-radius:10px;margin-bottom:8px;background:#fff}
.item .drag{cursor:grab}
.badge{font-size:.8rem;color:#fff;background:#111;border-radius:999px;padding:.15rem .5rem}
.table{width:100%;border-collapse:collapse} .table th,.table td{border-bottom:1px solid var(--border);padding:8px;text-align:left}
.footer{color:var(--muted);font-size:.9rem;text-align:right}
</style>
<script>
function reorder() {
  const ids = [...document.querySelectorAll('.item')].map(e=>e.dataset.id);
  document.getElementById('order').value = ids.join(',');
  document.getElementById('reorderForm').submit();
}
function makeDraggable(){
  const list = document.getElementById('linkList');
  let dragEl=null;
  list.querySelectorAll('.item').forEach(li=>{
    li.draggable=true;
    li.addEventListener('dragstart', e=>{dragEl=li; e.dataTransfer.effectAllowed='move';});
    li.addEventListener('dragover', e=>{e.preventDefault(); const rect=li.getBoundingClientRect(); const mid=rect.top+rect.height/2; if(e.clientY<mid){li.before(dragEl);} else {li.after(dragEl);} });
  });
}
window.addEventListener('DOMContentLoaded', makeDraggable);
</script>
</head>
<body>
  <div class="header">
    <div><strong>管理ダッシュボード</strong></div>
    <div>
      <a class="btn" href="<?=SITE_BASE_URL?>/" target="_blank">公開ページを開く</a>
      <a class="btn" href="admin.php?logout=1">ログアウト</a>
    </div>
  </div>

  <div class="container">
    <div class="card" id="profile">
      <h2>プロフィール</h2>
      <?php if (!empty($_GET['saved'])): ?><div class="badge">保存しました</div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>">
        <input type="hidden" name="act" value="save_profile">
        <div class="row">
          <div>
            <label>表示名</label>
            <input type="text" name="display_name" value="<?=htmlspecialchars($profile['display_name'] ?? '',ENT_QUOTES)?>">
          </div>
          <div>
            <label>アイコンURL（任意）</label>
            <input type="url" name="avatar_url" value="<?=htmlspecialchars($profile['avatar_url'] ?? '',ENT_QUOTES)?>" placeholder="https://...">
          </div>
        </div>
        <label>自己紹介</label>
        <textarea name="bio" rows="3"><?=htmlspecialchars($profile['bio'] ?? '',ENT_QUOTES)?></textarea>

        <div class="row">
          <div><label>背景色</label><input type="color" name="theme_bg" value="<?=htmlspecialchars($profile['theme_bg'])?>"></div>
          <div><label>文字色</label><input type="color" name="theme_text" value="<?=htmlspecialchars($profile['theme_text'])?>"></div>
          <div><label>ボタン色</label><input type="color" name="theme_button" value="<?=htmlspecialchars($profile['theme_button'])?>"></div>
          <div><label>ボタン文字色</label><input type="color" name="theme_button_text" value="<?=htmlspecialchars($profile['theme_button_text'])?>"></div>
        </div>
        <div style="margin-top:12px">
          <button class="btn primary">保存</button>
        </div>
      </form>
    </div>

    <div class="card" id="links">
      <h2>リンク</h2>
      <form method="post" style="margin-bottom:12px">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>">
        <input type="hidden" name="act" value="add_link">
        <div class="row">
          <div><label>タイトル</label><input type="text" name="title" required></div>
          <div><label>URL</label><input type="url" name="url" required placeholder="https://..."></div>
        </div>
        <div style="margin-top:8px"><button class="btn">追加</button></div>
      </form>

      <ul class="list" id="linkList">
        <?php foreach($links as $ln): ?>
          <li class="item" data-id="<?=$ln['id']?>">
            <span class="drag" title="ドラッグで並び替え">☰</span>
            <form method="post" style="flex:1;display:grid;grid-template-columns:1fr 1fr auto;gap:8px;align-items:center">
              <input type="hidden" name="csrf" value="<?=csrf_token()?>">
              <input type="hidden" name="act" value="update_link">
              <input type="hidden" name="id" value="<?=$ln['id']?>">
              <input type="text" name="title" value="<?=htmlspecialchars($ln['title'],ENT_QUOTES)?>" required>
              <input type="url" name="url" value="<?=htmlspecialchars($ln['url'],ENT_QUOTES)?>" required>
              <label style="display:flex;gap:6px;align-items:center;white-space:nowrap"><input type="checkbox" name="is_active" <?=$ln['is_active']?'checked':''?>> 有効</label>
              <div style="grid-column:1/-1;display:flex;gap:6px;justify-content:flex-end">
                <button class="btn">更新</button>
                <form method="post" onsubmit="return confirm('削除しますか？')" style="display:inline">
                  <input type="hidden" name="csrf" value="<?=csrf_token()?>">
                  <input type="hidden" name="act" value="delete_link">
                  <input type="hidden" name="id" value="<?=$ln['id']?>">
                  <button class="btn" formaction="admin.php" formmethod="post">削除</button>
                </form>
              </div>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
      <form id="reorderForm" method="post" style="margin-top:8px">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>">
        <input type="hidden" name="act" value="reorder">
        <input type="hidden" id="order" name="order" value="">
        <button type="button" class="btn primary" onclick="reorder()">並び順を保存</button>
      </form>
    </div>

    <div class="card">
      <h2>クリック数（累計）</h2>
      <table class="table">
        <thead><tr><th>リンク</th><th>クリック</th></tr></thead>
        <tbody>
        <?php foreach($stats as $s): ?>
          <tr><td><?=htmlspecialchars($s['title'],ENT_QUOTES)?></td><td><?= (int)$s['clicks'] ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <div class="footer">※ 新しい順にドラッグ＆ドロップ後「並び順を保存」を押してください。</div>
    </div>
  </div>
</body></html>
