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
    'network' => ['Network', 'fa-wifi'],
    'citizenship' => ['Citizenship', 'fa-briefcase'],
    'payments' => ['Payments & Invoices', 'fa-circle-dollar-to-slot'],
    'profile' => ['Profile', 'fa-user'],
];
$section = (string)($_GET['section'] ?? 'overview');
if (!isset($sections[$section])) {
    $section = 'overview';
}
$profileQuery = db_connect()->prepare('SELECT * FROM member_profiles WHERE member_id = ? LIMIT 1');
$profileQuery->execute([$member['id']]);
$profile = $profileQuery->fetch() ?: [];
$profileSections = [
    'Address' => ['district', 'region', 'street_address', 'postal_address'],
    'Business' => ['business_name'],
    'Network' => ['hotspot_count', 'estimated_subscribers', 'operation_areas', 'current_isps'],
    'Citizenship' => ['national_id_number'],
];
$overviewFields = [
    'Address' => [
        'District' => 'district',
        'Region' => 'region',
        'Street address' => 'street_address',
        'Postal address' => 'postal_address',
    ],
    'Business' => [
        'Business name' => 'business_name',
        'Trading name' => 'trading_name',
        'Registration number' => 'business_registration_number',
        'TIN' => 'tin',
        'Certificate of registration' => 'business_certificate_path',
    ],
    'Network' => [
        'Number of hotspots' => 'hotspot_count',
        'Estimated subscribers' => 'estimated_subscribers',
        'Operation areas' => 'operation_areas',
        'Current ISPs' => 'current_isps',
    ],
    'Citizenship' => [
        'Card number' => 'national_id_number',
        'National ID photo' => 'national_id_photo_path',
    ],
];
$completedSections = 0;
foreach ($profileSections as $fields) {
    $isComplete = true;
    foreach ($fields as $field) {
        if (!isset($profile[$field]) || $profile[$field] === '') {
            $isComplete = false;
            break;
        }
    }
    $completedSections += $isComplete ? 1 : 0;
}
$progressPercent = (int)round(($completedSections / count($profileSections)) * 100);
$onboardingSteps = [
    'address' => ['Address', 'fa-location-dot'],
    'business' => ['Business', 'fa-building'],
    'network' => ['Network', 'fa-wifi'],
    'citizenship' => ['Citizenship', 'fa-briefcase'],
];
$editStep = (string)($_GET['edit'] ?? '');
if (!isset($onboardingSteps[$editStep])) {
    $editStep = '';
}
$initialStep = 0;
foreach (array_keys($onboardingSteps) as $stepIndex => $stepKey) {
    if ($editStep !== '') {
        if ($stepKey === $editStep) {
            $initialStep = $stepIndex;
            break;
        }
        continue;
    }
    $requiredField = match ($stepKey) {
        'address' => 'district',
        'business' => 'business_name',
        'network' => 'operation_areas',
        'citizenship' => 'national_id_number',
    };
    if (!isset($profile[$requiredField]) || $profile[$requiredField] === '') {
        $initialStep = $stepIndex;
        break;
    }
    $initialStep = $stepIndex;
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
  <link rel="stylesheet" href="assets/css/client.css?v=20261012-6" />
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
        <?php if ($completedSections < count($profileSections)): ?>
        <div class="profile-progress-card">
          <div><span>Profile setup</span><strong><?= $completedSections ?>/4</strong></div>
          <span class="profile-progress-track"><span style="width: <?= $progressPercent ?>%"></span></span>
        </div>
        <?php endif; ?>
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
            <p class="dashboard-member-id">Registration number: <?= escape_html($member['registration_number']) ?></p>
          </div>

          <?php if ($completedSections < count($profileSections)): ?>
          <section class="complete-profile-card" aria-labelledby="complete-profile-heading">
            <div class="complete-profile-copy">
              <h2 id="complete-profile-heading">Complete your profile</h2>
              <p>You've completed <?= $completedSections ?> of 4 sections. Finish setting up your membership to get the most out of HOU.</p>
            </div>
            <a class="onboarding-button" href="member.php?section=onboarding">Continue onboarding <span aria-hidden="true">→</span></a>
            <div class="complete-profile-progress">
              <span class="complete-profile-track"><span style="width: <?= $progressPercent ?>%"></span></span>
              <strong><?= $progressPercent ?>%</strong>
            </div>
          </section>
          <?php endif; ?>

          <div class="profile-overview-grid">
            <?php foreach ($overviewFields as $group => $fields): ?>
              <?php if ($group === 'Network' || $group === 'Citizenship') continue; ?>
              <section class="dashboard-panel profile-overview-panel">
                <div class="profile-overview-heading">
                  <h2><?= escape_html($group) ?></h2>
                </div>
                <dl class="profile-overview-details">
                  <?php foreach ($fields as $label => $field): ?>
                    <?php
                    $value = $profile[$field] ?? null;
                    if (str_ends_with($field, '_path')) {
                        $value = $value ? 'Uploaded' : null;
                    }
                    ?>
                    <div>
                      <dt><?= escape_html($label) ?></dt>
                      <dd><?= $value !== null && $value !== '' ? escape_html((string)$value) : 'Not set' ?></dd>
                    </div>
                  <?php endforeach; ?>
                </dl>
              </section>
            <?php endforeach; ?>
          </div>

          <section class="dashboard-panel recent-payments" aria-labelledby="recent-payments-heading">
            <h2 id="recent-payments-heading">Recent payments</h2>
            <p>No payments yet.</p>
          </section>
        <?php elseif ($section === 'network' || $section === 'citizenship'): ?>
          <?php $category = $overviewFields[$sections[$section][0]]; ?>
          <div class="dashboard-page-heading">
            <h1><?= escape_html($sections[$section][0]) ?></h1>
            <p>Your saved <?= strtolower(escape_html($sections[$section][0])) ?> information.</p>
          </div>
          <section class="dashboard-panel profile-overview-panel">
            <dl class="profile-overview-details">
              <?php foreach ($category as $label => $field): ?>
                <?php
                $value = $profile[$field] ?? null;
                if (str_ends_with($field, '_path')) {
                    $value = $value ? 'Uploaded' : null;
                }
                ?>
                <div>
                  <dt><?= escape_html($label) ?></dt>
                  <dd><?= $value !== null && $value !== '' ? escape_html((string)$value) : 'Not set' ?></dd>
                </div>
              <?php endforeach; ?>
            </dl>
          </section>
        <?php elseif ($section === 'profile'): ?>
          <div class="dashboard-page-heading">
            <h1>Profile</h1>
            <p>Manage yourmembership details. Choose a category to make changes.</p>
          </div>
          <div class="profile-overview-grid">
            <?php foreach ($overviewFields as $group => $fields): ?>
              <?php $editKey = strtolower($group); ?>
              <section class="dashboard-panel profile-overview-panel">
                <div class="profile-overview-heading">
                  <h2><?= escape_html($group) ?></h2>
                  <a class="profile-edit-link" href="member.php?section=onboarding&amp;edit=<?= escape_html($editKey) ?>&amp;return=profile">Edit <span aria-hidden="true">→</span></a>
                </div>
                <dl class="profile-overview-details">
                  <?php foreach ($fields as $label => $field): ?>
                    <?php
                    $value = $profile[$field] ?? null;
                    if (str_ends_with($field, '_path')) {
                        $value = $value ? 'Uploaded' : null;
                    }
                    ?>
                    <div>
                      <dt><?= escape_html($label) ?></dt>
                      <dd><?= $value !== null && $value !== '' ? escape_html((string)$value) : 'Not set' ?></dd>
                    </div>
                  <?php endforeach; ?>
                </dl>
              </section>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="dashboard-page-heading">
            <h1><?= escape_html($sections[$section][0]) ?></h1>
            <p>Welcome, <?= escape_html(member_first_name((string)$member['full_name'])) ?>. Your HOU registration number is <strong><?= escape_html($member['registration_number']) ?></strong>.</p>
          </div>
          <?php if ($section === 'onboarding'): ?>
            <div class="onboarding-stepper" aria-label="Profile setup progress">
              <?php foreach ($onboardingSteps as $stepKey => [$stepLabel, $stepIcon]): ?>
                <div class="onboarding-stepper-item" data-stepper-item="<?= escape_html($stepKey) ?>">
                  <span class="onboarding-stepper-marker"><i class="fa-solid <?= escape_html($stepIcon) ?>" aria-hidden="true"></i></span>
                  <span><?= escape_html($stepLabel) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="dashboard-panel onboarding-panel">
              <form data-onboarding-form data-initial-step="<?= $initialStep ?>" data-edit-only="<?= $editStep !== '' ? 'true' : 'false' ?>" data-return-section="<?= $editStep !== '' && ($_GET['return'] ?? '') === 'profile' ? 'profile' : 'overview' ?>" data-csrf-token="<?= escape_html($csrfToken) ?>">
                <section class="onboarding-form-step" data-onboarding-step="address" hidden>
                  <h2>Address</h2>
                  <p>Where can we find you? Keep this up to date.</p>
                  <div class="onboarding-fields">
                    <label>District
                      <input name="district" type="text" maxlength="120" placeholder="e.g. Kampala" value="<?= escape_html((string)($profile['district'] ?? '')) ?>" required />
                    </label>
                    <label>Region
                      <input name="region" type="text" maxlength="120" placeholder="e.g. Central" value="<?= escape_html((string)($profile['region'] ?? '')) ?>" required />
                    </label>
                    <label>Street address
                      <input name="street_address" type="text" maxlength="180" placeholder="Plot 123" value="<?= escape_html((string)($profile['street_address'] ?? '')) ?>" required />
                    </label>
                    <label>Postal address
                      <input name="postal_address" type="text" maxlength="180" placeholder="P.O. Box 123" value="<?= escape_html((string)($profile['postal_address'] ?? '')) ?>" required />
                    </label>
                  </div>
                </section>

                <section class="onboarding-form-step" data-onboarding-step="business" hidden>
                  <h2>Business</h2>
                  <p>Your registered business details and certificates.</p>
                  <div class="onboarding-fields">
                    <label>Business name
                      <input name="business_name" type="text" maxlength="180" required value="<?= escape_html((string)($profile['business_name'] ?? '')) ?>" />
                    </label>
                    <label>Trading name <span>(optional)</span>
                      <input name="trading_name" type="text" maxlength="180" value="<?= escape_html((string)($profile['trading_name'] ?? '')) ?>" />
                    </label>
                    <label>Registration number <span>(optional)</span>
                      <input name="business_registration_number" type="text" maxlength="80" value="<?= escape_html((string)($profile['business_registration_number'] ?? '')) ?>" />
                    </label>
                    <label>TIN <span>(optional)</span>
                      <input name="tin" type="text" maxlength="80" value="<?= escape_html((string)($profile['tin'] ?? '')) ?>" />
                    </label>
                    <label class="onboarding-field-wide">Certificate of registration <span>(optional, PDF/JPG/PNG up to 5 MB)</span>
                      <input name="business_certificate" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" />
                      <?php if (!empty($profile['business_certificate_path'])): ?><small>A certificate is already saved. Upload a new file to replace it.</small><?php endif; ?>
                    </label>
                  </div>
                </section>

                <section class="onboarding-form-step" data-onboarding-step="network" hidden>
                  <h2>Network</h2>
                  <p>Tell us about your hotspot deployments.</p>
                  <div class="onboarding-fields">
                    <label>Number of hotspots
                      <input name="hotspot_count" type="number" min="0" max="4294967295" step="1" value="<?= escape_html((string)($profile['hotspot_count'] ?? '')) ?>" required />
                    </label>
                    <label>Estimated subscribers
                      <input name="estimated_subscribers" type="number" min="0" max="4294967295" step="1" value="<?= escape_html((string)($profile['estimated_subscribers'] ?? '')) ?>" required />
                    </label>
                    <label class="onboarding-field-wide">Operation areas
                      <input name="operation_areas" type="text" maxlength="500" placeholder="e.g. Kampala, Wakiso" value="<?= escape_html((string)($profile['operation_areas'] ?? '')) ?>" required />
                    </label>
                    <label class="onboarding-field-wide">Current ISPs
                      <input name="current_isps" type="text" maxlength="500" placeholder="e.g. MTN, Airtel" value="<?= escape_html((string)($profile['current_isps'] ?? '')) ?>" required />
                    </label>
                  </div>
                </section>

                <section class="onboarding-form-step" data-onboarding-step="citizenship" hidden>
                  <h2>Citizenship</h2>
                  <p>Your national ID details and identity verification.</p>
                  <div class="onboarding-fields">
                    <label class="onboarding-field-wide">Card number
                      <input name="national_id_number" type="text" maxlength="80" placeholder="UG123456789" value="<?= escape_html((string)($profile['national_id_number'] ?? '')) ?>" required />
                    </label>
                    <label class="onboarding-field-wide">National ID photo <span>(optional, JPG/PNG up to 5 MB)</span>
                      <input name="national_id_photo" type="file" accept=".jpg,.jpeg,.png,image/jpeg,image/png" />
                      <?php if (!empty($profile['national_id_photo_path'])): ?><small>A national ID photo is already saved. Upload a new file to replace it.</small><?php endif; ?>
                    </label>
                  </div>
                </section>

                <div class="onboarding-form-feedback" data-onboarding-feedback role="alert" hidden></div>
                <div class="onboarding-controls">
                  <button class="onboarding-back-button" type="button" data-onboarding-back>← Back</button>
                  <span data-onboarding-step-label></span>
                  <button class="onboarding-button" type="button" data-onboarding-next>Next →</button>
                </div>
              </form>
            </div>
          <?php else: ?>
          <section class="dashboard-panel section-placeholder">
            <span class="placeholder-icon"><i class="fa-solid <?= escape_html($sections[$section][1]) ?>" aria-hidden="true"></i></span>
            <h2><?= escape_html($sections[$section][0]) ?> details are coming soon</h2>
            <p>We’re preparing this part of the member portal. Your information and membership services will appear here soon.</p>
            <a class="placeholder-link" href="member.php?section=overview">Return to overview</a>
          </section>
          <?php endif; ?>
        <?php endif; ?>
      </main>
    </div>
  </div>
  <?php if ($section === 'onboarding'): ?>
    <script src="assets/js/onboarding.js?v=20261012-2" defer></script>
  <?php endif; ?>
</body>
</html>
