<?php
declare(strict_types=1);

require_once __DIR__ . '/../client/config.php';
require_once __DIR__ . '/auth.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow, noarchive');
require_admin();

$pdo = db_connect();
$csrfToken = admin_csrf_token();
$categories = [
    'general' => 'General',
    'event' => 'Event',
    'important' => 'Important',
    'maintenance' => 'Maintenance',
];
$statuses = ['draft' => 'Draft', 'published' => 'Published'];
$error = '';
$success = (string)($_GET['saved'] ?? '') === '1';
$formValues = [
    'title' => '',
    'body' => '',
    'category' => 'general',
    'status' => 'draft',
];
$editingId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$editing = null;

if ($editingId !== false && $editingId !== null) {
    $editQuery = $pdo->prepare(
        'SELECT id, title, body, category, status, image_data IS NOT NULL AS has_image FROM member_announcements WHERE id = ? LIMIT 1'
    );
    $editQuery->execute([$editingId]);
    $editing = $editQuery->fetch() ?: null;
    if ($editing) {
        $formValues = [
            'title' => (string)$editing['title'],
            'body' => (string)$editing['body'],
            'category' => (string)$editing['category'],
            'status' => (string)$editing['status'],
        ];
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!admin_csrf_is_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(419);
        $error = 'Your session expired. Refresh and try again.';
    } else {
        $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
        $postId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($action === 'delete') {
            if ($postId === false || $postId === null) {
                http_response_code(400);
                $error = 'Select a valid update to delete.';
            } else {
                $deleteQuery = $pdo->prepare('DELETE FROM member_announcements WHERE id = ?');
                $deleteQuery->execute([$postId]);
                header('Location: updates.php?saved=1', true, 303);
                exit;
            }
        } elseif ($action === 'save') {
            $editingId = $postId;
            $editing = null;
            if ($editingId !== false && $editingId !== null) {
                $editQuery = $pdo->prepare(
                    'SELECT id, title, body, category, status, image_data IS NOT NULL AS has_image FROM member_announcements WHERE id = ? LIMIT 1'
                );
                $editQuery->execute([$editingId]);
                $editing = $editQuery->fetch() ?: null;
                if (!$editing) {
                    http_response_code(404);
                    $error = 'That update no longer exists.';
                }
            }

            $title = is_string($_POST['title'] ?? null) ? trim($_POST['title']) : '';
            $body = is_string($_POST['body'] ?? null) ? trim($_POST['body']) : '';
            $category = is_string($_POST['category'] ?? null) ? $_POST['category'] : '';
            $status = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';
            $formValues = ['title' => $title, 'body' => $body, 'category' => $category, 'status' => $status];

            if ($error === '' && ($title === '' || strlen($title) > 180 || preg_match('//u', $title) !== 1)) {
                $error = 'Enter a title of up to 180 characters.';
            } elseif ($error === '' && ($body === '' || strlen($body) > 60000 || preg_match('//u', $body) !== 1)) {
                $error = 'Enter update content of up to 60,000 characters.';
            } elseif ($error === '' && !isset($categories[$category])) {
                $error = 'Choose a valid category.';
            } elseif ($error === '' && !isset($statuses[$status])) {
                $error = 'Choose whether this update is a draft or published.';
            }

            $imageData = null;
            $imageMime = null;
            $imageFile = $_FILES['image'] ?? null;
            if ($error === '' && is_array($imageFile) && ($imageFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                if (($imageFile['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($imageFile['tmp_name'] ?? null) || !is_uploaded_file($imageFile['tmp_name'])) {
                    $error = 'The image upload failed. Please choose the image again.';
                } else {
                    $imageSize = filesize($imageFile['tmp_name']);
                    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
                    $detectedMime = $fileInfo->file($imageFile['tmp_name']);
                    if ($imageSize === false || $imageSize < 1 || $imageSize > 5 * 1024 * 1024) {
                        $error = 'Choose an image no larger than 5 MB.';
                    } elseif (!in_array($detectedMime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                        $error = 'Use a JPEG, PNG, or WebP image.';
                    } else {
                        $contents = file_get_contents($imageFile['tmp_name']);
                        if ($contents === false) {
                            $error = 'The uploaded image could not be read.';
                        } else {
                            $imageData = $contents;
                            $imageMime = $detectedMime;
                        }
                    }
                }
            }

            if ($error === '' && !$editing && $imageData === null) {
                $error = 'Choose an image for this update.';
            } elseif ($error === '' && $editing && empty($editing['has_image']) && $imageData === null) {
                $error = 'Choose an image for this update.';
            }

            if ($error === '') {
                if ($editing) {
                    if ($imageData !== null) {
                        $saveQuery = $pdo->prepare(
                            "UPDATE member_announcements
                             SET title = ?, body = ?, category = ?, status = ?,
                                 published_at = CASE WHEN ? = 'published' THEN COALESCE(published_at, NOW()) ELSE NULL END,
                                 image_data = ?, image_mime = ?
                             WHERE id = ?"
                        );
                        $saveQuery->execute([$title, $body, $category, $status, $status, $imageData, $imageMime, $editingId]);
                    } else {
                        $saveQuery = $pdo->prepare(
                            "UPDATE member_announcements
                             SET title = ?, body = ?, category = ?, status = ?,
                                 published_at = CASE WHEN ? = 'published' THEN COALESCE(published_at, NOW()) ELSE NULL END
                             WHERE id = ?"
                        );
                        $saveQuery->execute([$title, $body, $category, $status, $status, $editingId]);
                    }
                } else {
                    $saveQuery = $pdo->prepare(
                        "INSERT INTO member_announcements (title, body, image_data, image_mime, category, status, published_at)
                         VALUES (?, ?, ?, ?, ?, ?, CASE WHEN ? = 'published' THEN NOW() ELSE NULL END)"
                    );
                    $saveQuery->execute([$title, $body, $imageData, $imageMime, $category, $status, $status]);
                }
                header('Location: updates.php?saved=1', true, 303);
                exit;
            }
        } else {
            http_response_code(400);
            $error = 'Choose a valid update action.';
        }
    }
}

$updatesQuery = $pdo->query(
    'SELECT id, title, category, status, published_at, created_at, image_data IS NOT NULL AS has_image
     FROM member_announcements ORDER BY created_at DESC, id DESC'
);
$updates = $updatesQuery->fetchAll();
function escape_update_admin(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="robots" content="noindex, nofollow, noarchive" />
  <title>Member updates | HOU Admin</title>
  <link rel="icon" type="image/png" href="../images/hou_logo.png" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" />
  <style>
    :root{font-family:Inter,"Segoe UI",sans-serif;color:#1e293b;background:#f1f5f9;font-synthesis:none}
    *{box-sizing:border-box}body{margin:0;min-width:320px}
    a{color:#0754c5;text-decoration:none}.topbar{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:16px max(20px,calc((100vw - 1180px)/2));color:#fff;background:#0f172a}
    .brand{display:flex;align-items:center;gap:12px;color:#fff;font-weight:800}.brand img{width:40px;height:40px;object-fit:contain;background:#fff;border-radius:8px}
    .topbar-actions{display:flex;align-items:center;gap:16px}.topbar-actions a{color:#fff;font-size:13px;font-weight:600}
    .logout{padding:8px 12px;color:#fff;background:transparent;border:1px solid rgba(255,255,255,.35);border-radius:8px;font:inherit;font-size:12px;font-weight:700;cursor:pointer}
    main{display:grid;width:min(1180px,calc(100% - 36px));grid-template-columns:minmax(0,1.15fr) minmax(320px,.85fr);align-items:start;gap:22px;margin:32px auto 60px}
    .panel{padding:22px;background:#fff;border:1px solid #e2e8f0;border-radius:15px;box-shadow:0 8px 25px rgba(15,23,42,.04)}
    h1{margin:0;color:#0f172a;font-size:28px}h2{margin:0 0 7px;color:#0f172a;font-size:18px}.subtitle{margin:7px 0 22px;color:#64748b;font-size:13px;line-height:1.6}
    .message{padding:12px 14px;margin-bottom:17px;border:1px solid #bfdbfe;border-radius:9px;background:#eff6ff;color:#1e40af;font-size:13px;line-height:1.5}
    .message.error{color:#991b1b;background:#fef2f2;border-color:#fecaca}
    label{display:block;margin:16px 0 6px;color:#475569;font-size:12px;font-weight:700}
    input[type=text],textarea,select,input[type=file]{width:100%;padding:11px 12px;color:#1e293b;background:#fff;border:1px solid #cbd5e1;border-radius:8px;font:inherit;font-size:13px}
    textarea{min-height:190px;resize:vertical;line-height:1.6}input[type=file]{padding:9px}
    .help{margin:6px 0 0;color:#64748b;font-size:11px;line-height:1.5}
    .button{display:inline-flex;min-height:40px;align-items:center;justify-content:center;padding:0 14px;color:#fff;background:#2563eb;border:1px solid #2563eb;border-radius:8px;font:inherit;font-size:12px;font-weight:700;cursor:pointer}
    .button.secondary{color:#1e293b;background:#fff;border-color:#cbd5e1}.button.danger{color:#b91c1c;background:#fff;border-color:#fecaca}
    .form-actions{display:flex;flex-wrap:wrap;gap:9px;margin-top:19px}.form-actions form{margin:0}
    .update-list{display:grid;gap:12px}.update-row{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:14px;border:1px solid #e2e8f0;border-radius:10px}
    .update-info{min-width:0}.update-title{display:block;color:#0f172a;font-size:13px;font-weight:700;overflow-wrap:anywhere}.update-meta{display:block;margin-top:5px;color:#64748b;font-size:11px}
    .badge{display:inline-block;margin-top:7px;padding:4px 7px;color:#475569;background:#f1f5f9;border-radius:999px;font-size:10px;font-weight:700;text-transform:uppercase}
    .badge.published{color:#166534;background:#dcfce7}.row-actions{display:flex;flex:0 0 auto;gap:7px}.row-actions .button{min-height:34px;padding:0 10px;font-size:11px}
    .empty{padding:20px;color:#64748b;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:9px;font-size:13px}
    @media(max-width:800px){main{grid-template-columns:1fr;margin-top:20px}.topbar{padding:13px 16px}}
    @media(max-width:520px){main{width:calc(100% - 24px)}.topbar-actions{gap:9px}.topbar-actions>a:first-child{display:none}.panel{padding:17px}.update-row{align-items:flex-start;flex-direction:column}.row-actions{width:100%}.row-actions .button{flex:1}}
  </style>
</head>
<body>
  <header class="topbar">
    <a class="brand" href="members.php"><img src="../images/hou_logo.png" alt="" /><span>HOU Admin</span></a>
    <div class="topbar-actions">
      <a href="members.php">Member directory</a>
      <a href="../index.php">Back to website</a>
      <form method="post" action="logout.php">
        <input type="hidden" name="csrf_token" value="<?= escape_update_admin($csrfToken) ?>" />
        <button class="logout" type="submit">Log out</button>
      </form>
    </div>
  </header>
  <main>
    <section class="panel">
      <h1><?= $editing ? 'Edit update' : 'Create an update' ?></h1>
      <p class="subtitle">Write a member communication, choose its card image, then save it as a draft or publish it to the portal.</p>
      <?php if ($error !== ''): ?><div class="message error" role="alert"><?= escape_update_admin($error) ?></div><?php endif; ?>
      <?php if ($success): ?><div class="message" role="status">Update saved.</div><?php endif; ?>
      <?php if ($editing && !empty($editing['has_image'])): ?><div class="message">An image is already attached. Upload another image to replace it.</div><?php endif; ?>
      <form method="post" action="updates.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= escape_update_admin($csrfToken) ?>" />
        <input type="hidden" name="action" value="save" />
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>" /><?php endif; ?>
        <label for="title">Title</label>
        <input id="title" name="title" type="text" maxlength="180" required value="<?= escape_update_admin($formValues['title']) ?>" />
        <label for="body">Content</label>
        <textarea id="body" name="body" maxlength="60000" required><?= escape_update_admin($formValues['body']) ?></textarea>
        <label for="image">Card image</label>
        <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" <?= $editing && !empty($editing['has_image']) ? '' : 'required' ?> />
        <p class="help">JPEG, PNG, or WebP; maximum 5 MB. This image appears behind the title on the Updates page.</p>
        <label for="category">Category</label>
        <select id="category" name="category">
          <?php foreach ($categories as $key => $label): ?>
            <option value="<?= escape_update_admin($key) ?>"<?= $formValues['category'] === $key ? ' selected' : '' ?>><?= escape_update_admin($label) ?></option>
          <?php endforeach; ?>
        </select>
        <label for="status">Visibility</label>
        <select id="status" name="status">
          <?php foreach ($statuses as $key => $label): ?>
            <option value="<?= escape_update_admin($key) ?>"<?= $formValues['status'] === $key ? ' selected' : '' ?>><?= escape_update_admin($label) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="form-actions">
          <button class="button" type="submit"><?= $editing ? 'Save changes' : 'Create update' ?></button>
          <?php if ($editing): ?><a class="button secondary" href="updates.php">Cancel</a><?php endif; ?>
        </div>
      </form>
    </section>
    <section class="panel">
      <h2>Member updates</h2>
      <p class="subtitle">Drafts are visible only to administrators. Published updates appear in the member portal.</p>
      <?php if ($updates): ?>
        <div class="update-list">
          <?php foreach ($updates as $update): ?>
            <article class="update-row">
              <div class="update-info">
                <span class="update-title"><?= escape_update_admin((string)$update['title']) ?></span>
                <span class="update-meta"><?= escape_update_admin(ucfirst((string)$update['category'])) ?><?php if ($update['published_at']): ?> · <?= escape_update_admin(date('M j, Y', strtotime((string)$update['published_at']))) ?><?php endif; ?></span>
                <span class="badge<?= $update['status'] === 'published' ? ' published' : '' ?>"><?= escape_update_admin((string)$update['status']) ?></span>
              </div>
              <div class="row-actions">
                <a class="button secondary" href="updates.php?id=<?= (int)$update['id'] ?>">Edit</a>
                <form method="post" action="updates.php" onsubmit="return confirm('Delete this update? This cannot be undone.');">
                  <input type="hidden" name="csrf_token" value="<?= escape_update_admin($csrfToken) ?>" />
                  <input type="hidden" name="action" value="delete" />
                  <input type="hidden" name="id" value="<?= (int)$update['id'] ?>" />
                  <button class="button danger" type="submit">Delete</button>
                </form>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty">No updates have been created yet.</div>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
