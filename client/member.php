<?php
declare(strict_types=1);

require_once __DIR__ . '/api/helpers.php';
$member = require_member();
$csrfToken = csrf_token();
$pdo = db_connect();
$announcementCount = (int)$pdo->query(
    "SELECT COUNT(*)
     FROM member_announcements
     WHERE status = 'published' AND published_at <= NOW()"
)->fetchColumn();
$announcementPage = filter_var($_GET['updates_page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$announcementPageSize = 10;
$announcementTotalPages = max(1, (int)ceil($announcementCount / $announcementPageSize));
$announcementPage = min($announcementPage, $announcementTotalPages);
$announcementQuery = $pdo->prepare(
    "SELECT title, body, category, published_at
     FROM member_announcements
     WHERE status = 'published' AND published_at <= NOW()
     ORDER BY published_at DESC, id DESC
     LIMIT ? OFFSET ?"
);
$announcementQuery->bindValue(1, $announcementPageSize, PDO::PARAM_INT);
$announcementQuery->bindValue(2, ($announcementPage - 1) * $announcementPageSize, PDO::PARAM_INT);
$announcementQuery->execute();
$announcements = $announcementQuery->fetchAll();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <meta name="theme-color" content="#0f172a" />
  <title>Member Portal | Hotspot Operators Union</title>
  <link rel="icon" type="image/png" href="../images/hou_logo.png" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/client.css?v=20261010-12" />
</head>
<body>
  <main class="portal-app">
    <header class="portal-header">
      <a class="portal-brand" href="../index.php">
        <img src="../images/hou_logo.png" alt="" />
        <span>Hotspot Operators Union</span>
      </a>
      <form action="api/auth/logout.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= escape_html($csrfToken) ?>" />
        <button class="portal-logout" type="submit"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i> Log out</button>
      </form>
    </header>

    <section class="portal-content" aria-labelledby="portal-heading">
      <section class="portal-welcome" aria-labelledby="portal-heading">
        <div>
          <span class="portal-eyebrow"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> MEMBER PORTAL</span>
          <h1 id="portal-heading">Welcome, <?= escape_html($member['full_name']) ?>!</h1>
          <p>Your HOU membership is active. Here’s what’s happening in your community.</p>
        </div>
        <a class="portal-welcome-link" href="#member-updates">View latest updates <i class="fa-solid fa-arrow-down" aria-hidden="true"></i></a>
        <span class="portal-welcome-decoration" aria-hidden="true"><i class="fa-solid fa-wifi"></i></span>
      </section>

      <div class="portal-dashboard-grid">
        <section class="portal-card portal-updates" id="member-updates" aria-labelledby="updates-heading">
          <div class="portal-card-heading">
            <span class="portal-icon"><i class="fa-solid fa-bullhorn" aria-hidden="true"></i></span>
            <div class="portal-updates-title">
              <h2 id="updates-heading">Community updates</h2>
              <p>News and notices from HOU</p>
            </div>
            <span class="portal-update-count"><?= $announcementCount ?> <?= $announcementCount === 1 ? 'update' : 'updates' ?></span>
          </div>

          <?php if ($announcements): ?>
            <div class="portal-announcement-list">
              <?php foreach ($announcements as $announcement): ?>
                <?php
                  $categoryLabels = [
                      'general' => 'News',
                      'event' => 'Event',
                      'important' => 'Important',
                      'maintenance' => 'Notice',
                  ];
                  $category = (string)$announcement['category'];
                ?>
                <article class="portal-announcement<?= $category === 'important' ? ' is-important' : '' ?>">
                  <div class="portal-announcement-meta">
                    <span class="portal-announcement-category category-<?= escape_html($category) ?>"><?= escape_html($categoryLabels[$category] ?? 'News') ?></span>
                    <time datetime="<?= escape_html(date(DATE_ATOM, strtotime((string)$announcement['published_at']))) ?>">
                      <?= escape_html(date('M j, Y', strtotime((string)$announcement['published_at']))) ?>
                    </time>
                  </div>
                  <h3><?= escape_html($announcement['title']) ?></h3>
                  <p><?= nl2br(escape_html($announcement['body'])) ?></p>
                </article>
              <?php endforeach; ?>
            </div>
            <?php if ($announcementTotalPages > 1): ?>
              <nav class="portal-pagination" aria-label="Community updates pages">
                <?php if ($announcementPage > 1): ?>
                  <a href="?updates_page=<?= $announcementPage - 1 ?>#member-updates"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Newer</a>
                <?php else: ?>
                  <span></span>
                <?php endif; ?>
                <span>Page <?= $announcementPage ?> of <?= $announcementTotalPages ?></span>
                <?php if ($announcementPage < $announcementTotalPages): ?>
                  <a href="?updates_page=<?= $announcementPage + 1 ?>#member-updates">Older <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                <?php endif; ?>
              </nav>
            <?php endif; ?>
          <?php else: ?>
            <div class="portal-empty-updates">
              <span><i class="fa-regular fa-bell-slash" aria-hidden="true"></i></span>
              <h3>You’re all caught up</h3>
              <p>There are no new updates right now. Check back here for HOU news and announcements.</p>
            </div>
          <?php endif; ?>
        </section>

        <aside class="portal-side-column">
          <section class="portal-card portal-membership" aria-labelledby="membership-heading">
            <div class="portal-card-heading">
              <span class="portal-icon"><i class="fa-solid fa-id-card" aria-hidden="true"></i></span>
              <div>
                <h2 id="membership-heading">Your membership</h2>
                <p>Your account at a glance</p>
              </div>
            </div>
            <div class="portal-status-row">
              <span class="portal-status-dot" aria-hidden="true"></span>
              <span>Active member</span>
            </div>
            <dl class="portal-details">
              <div><dt>Full name</dt><dd><?= escape_html($member['full_name']) ?></dd></div>
              <div><dt>Area of operation</dt><dd><?= escape_html($member['area_of_operation']) ?></dd></div>
              <div><dt>Phone number</dt><dd><?= escape_html($member['phone']) ?></dd></div>
              <div><dt>Email address</dt><dd><?= escape_html($member['email']) ?></dd></div>
              <div><dt>Member since</dt><dd><?= escape_html(date('F Y', strtotime($member['created_at']))) ?></dd></div>
            </dl>
          </section>

          <section class="portal-help-card" aria-labelledby="help-heading">
            <span class="portal-help-icon"><i class="fa-solid fa-headset" aria-hidden="true"></i></span>
            <h2 id="help-heading">We’re here to help</h2>
            <p>Need to update your details or have a question about your membership?</p>
            <a href="../pages/contact.php">Contact HOU <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
          </section>
        </aside>
      </div>
    </section>
  </main>
</body>
</html>
