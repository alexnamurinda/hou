<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow, noarchive');

start_admin_session();
if (($_SESSION['hou_admin_authenticated'] ?? false) === true) {
    header('Location: members.php', true, 303);
    exit;
}

$configuredPasswordHash = admin_password_hash();
$error = '';
$success = false;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!admin_csrf_is_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(419);
        $error = 'Your session expired. Refresh and try again.';
    } elseif ($configuredPasswordHash !== null) {
        http_response_code(409);
        $error = 'Admin password setup has already been completed. Sign in instead.';
    } else {
        $ipAddress = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        $setupCode = $_POST['setup_code'] ?? null;
        $password = $_POST['password'] ?? null;
        $passwordConfirmation = $_POST['password_confirmation'] ?? null;
        if (admin_auth_attempt_count('admin-setup', $ipAddress, 900) >= 5) {
            http_response_code(429);
            $error = 'Too many setup attempts. Please wait 15 minutes before trying again.';
        } elseif (
            !is_string($setupCode)
            || preg_match('/\A[a-f0-9]{64}\z/', $setupCode) !== 1
            || !is_string($password)
            || strlen($password) < 12
            || strlen($password) > 4096
            || !is_string($passwordConfirmation)
            || !hash_equals($password, $passwordConfirmation)
        ) {
            record_admin_auth_failure('admin-setup', $ipAddress);
            http_response_code(400);
            $error = 'Enter a valid setup code and matching password of at least 12 characters.';
        } else {
            $setupTokenHash = hash('sha256', $setupCode);
            $setupSucceeded = admin_credentials_update(
                static function (array &$credentials) use ($setupTokenHash, $password): bool {
                    if (
                        !empty($credentials['password_hash'])
                        || !is_string($credentials['setup_token_hash'] ?? null)
                        || !hash_equals($credentials['setup_token_hash'], $setupTokenHash)
                        || !is_int($credentials['setup_token_expires_at'] ?? null)
                        || $credentials['setup_token_expires_at'] <= time()
                    ) {
                        return false;
                    }

                    $credentials['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                    $credentials['setup_token_hash'] = null;
                    $credentials['setup_token_expires_at'] = null;
                    return true;
                }
            );

            if (!$setupSucceeded) {
                record_admin_auth_failure('admin-setup', $ipAddress);
                http_response_code(400);
                $error = 'The setup code is invalid or expired. Generate a new code on the server.';
            } else {
                $success = true;
                $configuredPasswordHash = true;
            }
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
  <title>Set admin password | HOU</title>
  <link rel="icon" type="image/png" href="../images/hou_logo.png" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" />
  <style>
    *{box-sizing:border-box}body{min-height:100vh;margin:0;display:grid;place-items:center;padding:20px;background:#f1f5f9;color:#1e293b;font-family:Inter,"Segoe UI",sans-serif}
    main{width:min(100%,420px);padding:32px;background:#fff;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 12px 36px rgba(15,23,42,.08)}
    .brand{display:flex;align-items:center;gap:12px;margin-bottom:28px;color:#0f172a;font-weight:800}.brand img{width:42px;height:42px;object-fit:contain}
    h1{margin:0;font-size:24px}.subtitle{margin:8px 0 24px;color:#64748b;font-size:14px;line-height:1.6}
    label{display:block;margin-bottom:7px;font-size:13px;font-weight:700}input{width:100%;height:46px;padding:0 12px;border:1px solid #cbd5e1;border-radius:8px;font:inherit}
    input:focus{outline:3px solid rgba(37,99,235,.15);border-color:#2563eb}button{width:100%;min-height:46px;margin-top:18px;border:0;border-radius:8px;background:#2563eb;color:#fff;font:inherit;font-weight:700;cursor:pointer}
    .field{margin-top:16px}.message{padding:11px 13px;margin-bottom:18px;border:1px solid #fecaca;border-radius:8px;background:#fef2f2;color:#991b1b;font-size:13px;line-height:1.5}.success{border-color:#bbf7d0;background:#f0fdf4;color:#166534}
    a{color:#1d4ed8;font-weight:600}
  </style>
</head>
<body>
  <main>
    <div class="brand"><img src="../images/hou_logo.png" alt="" /><span>HOU Admin</span></div>
    <h1>Set admin password</h1>
    <p class="subtitle">Use a one-time setup code generated on the server. The code expires after 30 minutes.</p>
    <?php if ($error !== ''): ?><div class="message" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($success): ?>
      <div class="message success" role="status">Admin password saved. You can now sign in.</div>
      <a href="login.php">Go to admin sign in</a>
    <?php elseif ($configuredPasswordHash !== null): ?>
      <div class="message" role="status">Admin password setup has already been completed.</div>
      <a href="login.php">Go to admin sign in</a>
    <?php else: ?>
      <form method="post" action="setup.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" />
        <label for="setup_code">One-time setup code</label>
        <input id="setup_code" name="setup_code" type="password" autocomplete="off" required />
        <div class="field">
          <label for="password">New admin password (at least 12 characters)</label>
          <input id="password" name="password" type="password" minlength="12" maxlength="4096" autocomplete="new-password" required />
        </div>
        <div class="field">
          <label for="password_confirmation">Confirm admin password</label>
          <input id="password_confirmation" name="password_confirmation" type="password" minlength="12" maxlength="4096" autocomplete="new-password" required />
        </div>
        <button type="submit">Save admin password</button>
      </form>
    <?php endif; ?>
  </main>
</body>
</html>
