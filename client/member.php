<?php
declare(strict_types=1);

require_once __DIR__ . '/api/helpers.php';
$member = require_member();
$csrfToken = csrf_token();
$notice = $_SESSION['registration_notice'] ?? null;
unset($_SESSION['registration_notice']);

$sections = [
    'overview' => ['Overview', 'fa-house'],
    'onboarding' => ['Onboarding', 'fa-clipboard-list'],
    'address' => ['Address', 'fa-location-dot'],
    'business' => ['Business', 'fa-building'],
    'network' => ['Network', 'fa-wifi'],
    'citizenship' => ['Citizenship', 'fa-briefcase'],
    'payments' => ['Payments & Invoices', 'fa-circle-dollar-to-slot'],
    'profile' => ['Profile', 'fa-user'],
];
$section = (string)($_GET['section'] ?? 'overview');
if (!isset($sections[$section])) {
    $section = 'overview';
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <meta name="theme-color" content="#ffffff" />
  <title><?= escape_html($sections[$section][0]) ?> | HOU Member Portal</title>
  <link rel="icon" type="image/png" href="../images/hou_logo.png" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/client.css?v=20261012-4" />
</head>
<body class="portal-dashboard-body">
  <div class="portal-shell">
    <header class="dashboard-header">
      <a class="portal-brand" href="member.php?section=overview">
        <img src="../images/hou_logo.png" alt="" />
        <span>Hotspot Operators Union</span>
      </a>
      <div class="dashboard-account">
        <span class="dashboard-account-email"><?= escape_html($member['email']) ?></span>
        <form action="api/auth/logout.php" method="post">
          <input type="hidden" name="csrf_token" value="<?= escape_html($csrfToken) ?>" />
          <button class="portal-logout" type="submit"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i> Log out</button>
        </form>
      </div>
    </header>

    <div class="dashboard-layout">
      <aside class="dashboard-sidebar" aria-label="Member portal navigation">
        <div class="profile-progress-card">
          <div><span>Profile setup</span><strong>0/4</strong></div>
          <span class="profile-progress-track"><span></span></span>
        </div>
        <nav class="dashboard-nav">
          <?php foreach ($sections as $key => [$label, $icon]): ?>
            <a class="dashboard-nav-link<?= $section === $key ? ' is-active' : '' ?>"
               href="member.php?section=<?= escape_html($key) ?>"
               <?= $section === $key ? 'aria-current="page"' : '' ?>>
              <i class="fa-solid <?= escape_html($icon) ?>" aria-hidden="true"></i>
              <span><?= escape_html($label) ?></span>
            </a>
          <?php endforeach; ?>
        </nav>
        <div class="dashboard-sidebar-account">
          <span><?= escape_html($member['full_name']) ?></span>
          <span class="sidebar-registration">Member ID: <?= escape_html($member['registration_number']) ?></span>
        </div>
      </aside>

      <main class="dashboard-main">
        <?php if ($notice !== null && is_array($notice)): ?>
          <div class="dashboard-notice<?= empty($notice['email_sent']) ? ' is-warning' : '' ?>" role="status">
            <?php if (!empty($notice['email_sent'])): ?>
              Your registration number <?= escape_html((string)$member['registration_number']) ?> was sent to your email. Keep it safe; your membership certificate will be available in the members portal.
            <?php else: ?>
              Your account is ready, but we could not email your registration number. Keep it safe: <strong><?= escape_html((string)$member['registration_number']) ?></strong>. Your membership certificate will be available in the members portal.
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ($section === 'overview'): ?>
          <div class="dashboard-page-heading">
            <h1>Welcome, <?= escape_html(member_first_name((string)$member['full_name'])) ?></h1>
            <p>Signed in as <?= escape_html($member['email']) ?></p>
            <p class="dashboard-member-id">Registration number: <?= escape_html($member['registration_number']) ?></p>
          </div>

          <section class="complete-profile-card" aria-labelledby="complete-profile-heading">
            <div class="complete-profile-copy">
              <h2 id="complete-profile-heading">Complete your profile</h2>
              <p>You've completed 0 of 4 sections. Finish setting up your membership to get the most out of HOU.</p>
            </div>
            <a class="onboarding-button" href="member.php?section=onboarding">Continue onboarding <span aria-hidden="true">→</span></a>
            <div class="complete-profile-progress">
              <span class="complete-profile-track"><span></span></span>
              <strong>0%</strong>
            </div>
          </section>

          <section class="overview-status-grid" aria-label="Profile section status">
            <?php foreach (['Address', 'Business', 'Citizenship', 'Network'] as $statusLabel): ?>
              <article class="overview-status-card">
                <span><?= escape_html($statusLabel) ?></span>
                <strong>Not set</strong>
              </article>
            <?php endforeach; ?>
          </section>

          <section class="dashboard-panel recent-payments" aria-labelledby="recent-payments-heading">
            <h2 id="recent-payments-heading">Recent payments</h2>
            <p>No payments yet.</p>
          </section>
        <?php else: ?>
          <div class="dashboard-page-heading">
            <h1><?= escape_html($sections[$section][0]) ?></h1>
            <p>Welcome, <?= escape_html(member_first_name((string)$member['full_name'])) ?>. Your HOU registration number is <strong><?= escape_html($member['registration_number']) ?></strong>.</p>
          </div>
          <section class="dashboard-panel section-placeholder">
            <span class="placeholder-icon"><i class="fa-solid <?= escape_html($sections[$section][1]) ?>" aria-hidden="true"></i></span>
            <?php if ($section === 'onboarding'): ?>
              <h2>Your profile setup is ready to begin</h2>
              <p>Complete your address, business, citizenship, and network sections here. These profile forms will be available soon.</p>
              <a class="onboarding-button" href="member.php?section=address">Start with address <span aria-hidden="true">→</span></a>
            <?php else: ?>
              <h2><?= escape_html($sections[$section][0]) ?> details are coming soon</h2>
              <p>We’re preparing this part of the member portal. Your information and membership services will appear here soon.</p>
              <a class="placeholder-link" href="member.php?section=overview">Return to overview</a>
            <?php endif; ?>
          </section>
        <?php endif; ?>
      </main>
    </div>
  </div>
</body>
</html>
