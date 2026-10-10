<?php
declare(strict_types=1);

require_once __DIR__ . '/api/helpers.php';
$member = require_member();
$csrfToken = csrf_token();

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
  <link rel="stylesheet" href="assets/css/client.css?v=20261010-11" />
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
      <h1 id="portal-heading">Welcome, <?= escape_html($member['full_name']) ?>!</h1>
      <p class="portal-intro">Your HOU membership account is active. Thank you!</p>

      <div class="portal-card">
        <div class="portal-card-heading">
          <span class="portal-icon"><i class="fa-solid fa-user" aria-hidden="true"></i></span>
          <div>
            <h2>Membership details</h2>
            <p>Contact HOU if any of your information needs updating.</p>
          </div>
        </div>
        <dl class="portal-details">
          <div><dt>Full name</dt><dd><?= escape_html($member['full_name']) ?></dd></div>
          <div><dt>Area of operation</dt><dd><?= escape_html($member['area_of_operation']) ?></dd></div>
          <div><dt>Phone number</dt><dd><?= escape_html($member['phone']) ?></dd></div>
          <div><dt>Email address</dt><dd><?= escape_html($member['email']) ?></dd></div>
          <div><dt>Member since</dt><dd><?= escape_html(date('F Y', strtotime($member['created_at']))) ?></dd></div>
        </dl>
      </div>
      <a class="portal-contact" href="../pages/contact.php"><i class="fa-solid fa-circle-question" aria-hidden="true"></i> Need help? Contact HOU</a>
    </section>
  </main>
</body>
</html>
