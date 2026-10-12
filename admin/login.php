<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../client/config.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow, noarchive');

start_admin_session();
if (($_SESSION['hou_admin_authenticated'] ?? false) === true) {
    header('Location: members.php', true, 303);
    exit;
}

$error = '';
$configuredPasswordHash = admin_password_hash();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!admin_csrf_is_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(419);
        $error = 'Your session expired. Refresh and try again.';
    } elseif ($configuredPasswordHash === null) {
        http_response_code(503);
        $error = 'Admin access has not been configured on the server.';
    } else {
        $ipAddress = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $attemptKey = hash('sha256', 'hou-admin-login|' . $ipAddress);
        $pdo = db_connect();
        if (random_int(1, 100) === 1) {
            $pdo->exec('DELETE FROM auth_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
        }
        $attempts = $pdo->prepare(
            'SELECT COUNT(*) FROM auth_attempts WHERE attempt_key = ? AND attempted_at >= DATE_SUB(NOW(), INTERVAL 900 SECOND)'
        );
        $attempts->execute([$attemptKey]);

        if ((int)$attempts->fetchColumn() >= 10) {
            http_response_code(429);
            $error = 'Too many sign-in attempts. Please wait 15 minutes before trying again.';
        } elseif (
            !is_string($_POST['password'] ?? null)
            || strlen($_POST['password']) > 4096
            || !password_verify($_POST['password'], $configuredPasswordHash)
        ) {
            $pdo->prepare('INSERT INTO auth_attempts (attempt_key) VALUES (?)')->execute([$attemptKey]);
            http_response_code(401);
            $error = 'The admin password is incorrect.';
        } else {
            session_regenerate_id(true);
            $_SESSION['hou_admin_authenticated'] = true;
            $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
            header('Location: members.php', true, 303);
            exit;
        }
    }
}

$csrfToken = admin_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="robots" content="noindex, nofollow, noarchive" />
  <title>Admin sign in | HOU</title>
  <link rel="icon" type="image/png" href="../images/hou_logo.png" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" />
  <style>
    *{box-sizing:border-box}body{min-height:100vh;margin:0;display:grid;place-items:center;padding:20px;background:#f1f5f9;color:#1e293b;font-family:Inter,"Segoe UI",sans-serif}
    main{width:min(100%,420px);padding:32px;background:#fff;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 12px 36px rgba(15,23,42,.08)}
    .brand{display:flex;align-items:center;gap:12px;margin-bottom:28px;color:#0f172a;font-weight:800}.brand img{width:42px;height:42px;object-fit:contain}
    h1{margin:0;font-size:24px}.subtitle{margin:8px 0 24px;color:#64748b;font-size:14px;line-height:1.6}
    label{display:block;margin-bottom:7px;font-size:13px;font-weight:700}input{width:100%;height:46px;padding:0 12px;border:1px solid #cbd5e1;border-radius:8px;font:inherit}
    input:focus{outline:3px solid rgba(37,99,235,.15);border-color:#2563eb}button{width:100%;min-height:46px;margin-top:18px;border:0;border-radius:8px;background:#2563eb;color:#fff;font:inherit;font-weight:700;cursor:pointer}
    .error{padding:11px 13px;margin-bottom:18px;border:1px solid #fecaca;border-radius:8px;background:#fef2f2;color:#991b1b;font-size:13px;line-height:1.5}
  </style>
</head>
<body>
  <main>
    <div class="brand"><img src="../images/hou_logo.png" alt="" /><span>HOU Admin</span></div>
    <h1>Admin sign in</h1>
    <p class="subtitle">Sign in to view member records and submitted documents.</p>
    <?php if ($error !== ''): ?><div class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
    <form method="post" action="login.php">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" />
      <label for="password">Admin password</label>
      <input id="password" name="password" type="password" autocomplete="current-password" required autofocus />
      <button type="submit">Sign in</button>
    </form>
  </main>
</body>
</html>
