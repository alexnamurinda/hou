<?php
declare(strict_types=1);

require_once __DIR__ . '/../client/config.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow, noarchive');

function escape_value(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function valid_date_filter(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

$pdo = db_connect();
$search = trim((string)($_GET['q'] ?? ''));
$area = trim((string)($_GET['area'] ?? ''));
$status = (string)($_GET['status'] ?? '');
$from = (string)($_GET['from'] ?? '');
$to = (string)($_GET['to'] ?? '');
$sort = (string)($_GET['sort'] ?? 'newest');
$printView = isset($_GET['print']);
$memberId = filter_var($_GET['member'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;

$filters = [];
$parameters = [];

if ($search !== '') {
    $filters[] = '(full_name LIKE ? OR email LIKE ? OR phone LIKE ? OR area_of_operation LIKE ?)';
    $term = '%' . $search . '%';
    array_push($parameters, $term, $term, $term, $term);
}
if ($area !== '') {
    $filters[] = 'area_of_operation LIKE ?';
    $parameters[] = '%' . $area . '%';
}
if (in_array($status, ['active', 'suspended'], true)) {
    $filters[] = 'status = ?';
    $parameters[] = $status;
} else {
    $status = '';
}
if ($from !== '' && valid_date_filter($from)) {
    $filters[] = 'created_at >= ?';
    $parameters[] = $from . ' 00:00:00';
} else {
    $from = '';
}
if ($to !== '' && valid_date_filter($to)) {
    $filters[] = 'created_at < DATE_ADD(?, INTERVAL 1 DAY)';
    $parameters[] = $to;
} else {
    $to = '';
}

$where = $filters ? ' WHERE ' . implode(' AND ', $filters) : '';
$sortOptions = [
    'newest' => 'created_at DESC, id DESC',
    'oldest' => 'created_at ASC, id ASC',
    'name' => 'full_name ASC, id ASC',
    'area' => 'area_of_operation ASC, full_name ASC',
];
if (!isset($sortOptions[$sort])) {
    $sort = 'newest';
}
$orderBy = $sortOptions[$sort];
$returnQuery = array_filter([
    'q' => $search,
    'area' => $area,
    'status' => $status,
    'from' => $from,
    'to' => $to,
    'sort' => $sort !== 'newest' ? $sort : '',
], static fn(string $value): bool => $value !== '');
$returnUrl = 'members.php' . ($returnQuery ? '?' . http_build_query($returnQuery) : '');

$member = null;
if ($memberId !== false && $memberId !== null) {
    $statement = $pdo->prepare(
        'SELECT id, full_name, area_of_operation, phone, email, status, created_at, updated_at FROM members WHERE id = ? LIMIT 1'
    );
    $statement->execute([$memberId]);
    $member = $statement->fetch();
}

$countStatement = $pdo->prepare('SELECT COUNT(*) FROM members' . $where);
$countStatement->execute($parameters);
$totalMembers = (int)$countStatement->fetchColumn();

$pageSize = 25;
$totalPages = max(1, (int)ceil($totalMembers / $pageSize));
$page = min($page, $totalPages);
$offset = ($page - 1) * $pageSize;

if ($printView) {
    $statement = $pdo->prepare(
        'SELECT id, full_name, area_of_operation, phone, email, status, created_at FROM members' . $where . " ORDER BY $orderBy"
    );
    $statement->execute($parameters);
    $members = $statement->fetchAll();
} elseif (!$member) {
    $statement = $pdo->prepare(
        'SELECT id, full_name, area_of_operation, phone, email, status, created_at FROM members' . $where . " ORDER BY $orderBy LIMIT $pageSize OFFSET $offset"
    );
    $statement->execute($parameters);
    $members = $statement->fetchAll();
}

$baseQuery = $returnQuery;
$statusCountsStatement = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        SUM(status = 'active') AS active,
        SUM(status = 'suspended') AS suspended
     FROM members"
);
$statusCounts = $statusCountsStatement->fetch() ?: ['total' => 0, 'active' => 0, 'suspended' => 0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="robots" content="noindex, nofollow, noarchive" />
  <title><?= $printView ? 'Member List PDF' : ($member ? 'Member Details' : 'Member Directory') ?> | HOU Admin</title>
  <link rel="icon" type="image/png" href="../images/hou_logo.png" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" />
  <style>
    :root{font-family:Inter,"Segoe UI",sans-serif;color:#1e293b;background:#f1f5f9;font-synthesis:none}
    *{box-sizing:border-box}
    body{margin:0;min-width:320px}
    a{color:#0754c5;text-decoration:none}
    a:hover{text-decoration:underline}
    .topbar{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:18px max(22px,calc((100vw - 1180px)/2));color:#fff;background:#0f172a}
    .brand{display:flex;align-items:center;gap:12px;color:#fff;font-weight:800}
    .brand img{width:42px;height:42px;padding:4px;object-fit:contain;background:#fff;border-radius:10px}
    .topbar a.back{color:#fff;font-size:14px;font-weight:600}
    main{width:min(1180px,calc(100% - 36px));margin:32px auto 60px}
    .warning{padding:14px 17px;margin-bottom:22px;color:#7c2d12;background:#fff7ed;border:1px solid #fed7aa;border-radius:12px;font-size:13px;line-height:1.55}
    .warning strong{display:block;margin-bottom:3px}
    .page-title{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:22px}
    h1{margin:0;color:#0f172a;font-size:clamp(25px,4vw,34px);line-height:1.2}
    .subtitle{margin:7px 0 0;color:#64748b;font-size:14px}
    .actions{display:flex;flex-wrap:wrap;gap:10px}
    .button{display:inline-flex;min-height:42px;align-items:center;justify-content:center;padding:0 15px;color:#fff;background:#2563eb;border:1px solid #2563eb;border-radius:10px;font:inherit;font-size:13px;font-weight:700;cursor:pointer}
    .button:hover{color:#fff;background:#1d4ed8;text-decoration:none}
    .button.secondary{color:#1e293b;background:#fff;border-color:#cbd5e1}
    .button.secondary:hover{background:#f8fafc}
    .stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:20px}
    .stat,.panel{background:#fff;border:1px solid #e2e8f0;border-radius:15px;box-shadow:0 8px 25px rgba(15,23,42,.04)}
    .stat{padding:17px 20px}
    .stat-label{display:block;color:#64748b;font-size:12px;font-weight:600}
    .stat-value{display:block;margin-top:4px;color:#0f172a;font-size:24px;font-weight:800}
    .panel{padding:20px;margin-bottom:18px}
    .filters{display:grid;grid-template-columns:2fr 1.2fr 1fr 1fr 1fr 1fr auto auto;align-items:end;gap:12px}
    .field label{display:block;margin-bottom:6px;color:#475569;font-size:12px;font-weight:700}
    .field input,.field select{width:100%;height:42px;padding:0 11px;color:#1e293b;background:#fff;border:1px solid #cbd5e1;border-radius:9px;font:inherit;font-size:13px}
    .field input:focus,.field select:focus{outline:3px solid rgba(37,99,235,.15);border-color:#2563eb}
    .table-wrap{overflow-x:auto}
    table{width:100%;border-collapse:collapse;text-align:left}
    th,td{padding:13px 14px;border-bottom:1px solid #e2e8f0;vertical-align:middle}
    th{color:#64748b;background:#f8fafc;font-size:11px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;white-space:nowrap}
    td{font-size:13px}
    tbody tr:hover{background:#f8fbff}
    .member-name{font-weight:700}
    .muted{color:#64748b}
    .badge{display:inline-flex;padding:5px 9px;color:#166534;background:#dcfce7;border-radius:99px;font-size:11px;font-weight:800;text-transform:capitalize}
    .badge.suspended{color:#991b1b;background:#fee2e2}
    .view-link{font-weight:700;white-space:nowrap}
    .empty{padding:38px 20px;color:#64748b;text-align:center}
    .pagination{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:16px 20px;color:#64748b;font-size:13px}
    .pagination-links{display:flex;gap:8px}
    .details-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 28px}
    .detail{padding:16px 0;border-bottom:1px solid #e2e8f0}
    .detail dt{margin-bottom:6px;color:#64748b;font-size:12px;font-weight:700}
    .detail dd{margin:0;color:#0f172a;font-size:15px;font-weight:600;overflow-wrap:anywhere}
    .print-heading{display:none}
    @media(max-width:1000px){.filters{grid-template-columns:repeat(4,minmax(0,1fr))}}
    @media(max-width:620px){
      main{width:calc(100% - 24px);margin-top:18px}
      .topbar{padding:13px 16px}
      .brand{font-size:13px}
      .brand img{width:36px;height:36px}
      .topbar a.back{font-size:12px}
      .page-title{align-items:flex-start;flex-direction:column}
      .stats{gap:8px}
      .stat{padding:13px 11px}
      .stat-value{font-size:20px}
      .stat-label{font-size:10px}
      .panel{padding:14px}
      .filters{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
      .filters .field.search,.filters .field.area{grid-column:span 2}
      .filters .button{padding:0 10px}
      .details-grid{grid-template-columns:1fr}
      .pagination{align-items:flex-start;flex-direction:column}
    }
    @media print{
      @page{size:landscape;margin:14mm}
      body{min-width:0;background:#fff;color:#111}
      .topbar,.warning,.no-print,.stats{display:none!important}
      main{width:100%;margin:0}
      .print-heading{display:block;margin-bottom:16px}
      h1{font-size:22px}
      .subtitle{font-size:11px}
      .panel{padding:0;border:0;box-shadow:none}
      .table-wrap{overflow:visible}
      table{font-size:10px}
      th,td{padding:7px 8px;border-color:#bbb}
      th{color:#111;background:#eee!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
      .badge{padding:0;color:#111;background:transparent}
      a{color:#111;text-decoration:none}
      tr{break-inside:avoid}
    }
  </style>
</head>
<body>
  <header class="topbar no-print">
    <a class="brand" href="../index.php"><img src="../images/hou_logo.png" alt="" /><span>HOU Admin</span></a>
    <a class="back" href="../index.php">Back to website</a>
  </header>

  <main>
    <div class="warning no-print">
      <strong>Admin directory — currently not access-protected</strong>
      Anyone who can reach this URL can view member records. Do not publish this page on a public production server until access control is added.
    </div>

    <?php if ($memberId !== false && $memberId !== null): ?>
      <?php if (!$member): ?>
        <section class="panel">
          <h1>Member not found</h1>
          <p class="subtitle">That member record may have been removed.</p>
          <p><a class="button" href="<?= escape_value($returnUrl) ?>">Back to member list</a></p>
        </section>
      <?php else: ?>
        <div class="page-title">
          <div>
            <h1>Member details</h1>
            <p class="subtitle">Member record #<?= (int)$member['id'] ?></p>
          </div>
          <div class="actions no-print">
            <a class="button secondary" href="<?= escape_value($returnUrl) ?>">Back to list</a>
            <button class="button" type="button" onclick="window.print()">Print / Save PDF</button>
          </div>
        </div>
        <section class="panel">
          <dl class="details-grid">
            <div class="detail"><dt>Full name</dt><dd><?= escape_value($member['full_name']) ?></dd></div>
            <div class="detail"><dt>Area of operation</dt><dd><?= escape_value($member['area_of_operation']) ?></dd></div>
            <div class="detail"><dt>Phone number</dt><dd><?= escape_value($member['phone']) ?></dd></div>
            <div class="detail"><dt>Email address</dt><dd><?= escape_value($member['email']) ?></dd></div>
            <div class="detail"><dt>Account status</dt><dd><span class="badge <?= $member['status'] === 'suspended' ? 'suspended' : '' ?>"><?= escape_value($member['status']) ?></span></dd></div>
            <div class="detail"><dt>Registered</dt><dd><?= escape_value(date('F j, Y \a\t g:i A', strtotime($member['created_at']))) ?></dd></div>
            <div class="detail"><dt>Last record update</dt><dd><?= escape_value(date('F j, Y \a\t g:i A', strtotime($member['updated_at']))) ?></dd></div>
          </dl>
        </section>
      <?php endif; ?>
    <?php else: ?>
      <div class="page-title">
        <div>
          <h1><?= $printView ? 'HOU Registered Members' : 'Member Directory' ?></h1>
          <p class="subtitle"><?= number_format($totalMembers) ?> member<?= $totalMembers === 1 ? '' : 's' ?><?= $printView ? ' match the selected filters' : ' match your filters' ?></p>
        </div>
        <div class="actions no-print">
          <a class="button" href="members.php?<?= escape_value(http_build_query($baseQuery + ['print' => '1'])) ?>" target="_blank" rel="noopener">Print / Save PDF</a>
        </div>
      </div>

      <?php if (!$printView): ?>
        <section class="stats" aria-label="Membership totals">
          <div class="stat"><span class="stat-label">All members</span><span class="stat-value"><?= number_format((int)$statusCounts['total']) ?></span></div>
          <div class="stat"><span class="stat-label">Active</span><span class="stat-value"><?= number_format((int)$statusCounts['active']) ?></span></div>
          <div class="stat"><span class="stat-label">Suspended</span><span class="stat-value"><?= number_format((int)$statusCounts['suspended']) ?></span></div>
        </section>

        <section class="panel no-print">
          <form class="filters" method="get" action="members.php">
            <div class="field search"><label for="q">Search members</label><input id="q" name="q" type="search" value="<?= escape_value($search) ?>" placeholder="Name, phone, email or area" /></div>
            <div class="field area"><label for="area">Area of operation</label><input id="area" name="area" value="<?= escape_value($area) ?>" placeholder="Any area" /></div>
            <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">All statuses</option><option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option><option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Suspended</option></select></div>
            <div class="field"><label for="from">Registered from</label><input id="from" name="from" type="date" value="<?= escape_value($from) ?>" /></div>
            <div class="field"><label for="to">Registered to</label><input id="to" name="to" type="date" value="<?= escape_value($to) ?>" /></div>
            <div class="field"><label for="sort">Sort by</label><select id="sort" name="sort"><option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest first</option><option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest first</option><option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name</option><option value="area" <?= $sort === 'area' ? 'selected' : '' ?>>Area</option></select></div>
            <button class="button" type="submit">Filter</button>
            <a class="button secondary" href="members.php">Reset</a>
          </form>
        </section>
      <?php else: ?>
        <div class="print-heading">
          <p class="subtitle">Generated <?= escape_value(date('F j, Y g:i A')) ?> · <?= number_format($totalMembers) ?> members</p>
        </div>
      <?php endif; ?>

      <section class="panel">
        <?php if (!$members): ?>
          <div class="empty">No members match the selected filters.</div>
        <?php else: ?>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Name</th><th>Area of operation</th><th>Phone</th><th>Email</th><th>Status</th><th>Registered</th><?php if (!$printView): ?><th class="no-print">Details</th><?php endif; ?></tr></thead>
              <tbody>
                <?php foreach ($members as $row): ?>
                  <tr>
                    <td class="member-name"><?= escape_value($row['full_name']) ?></td>
                    <td><?= escape_value($row['area_of_operation']) ?></td>
                    <td><?= escape_value($row['phone']) ?></td>
                    <td><?= escape_value($row['email']) ?></td>
                    <td><span class="badge <?= $row['status'] === 'suspended' ? 'suspended' : '' ?>"><?= escape_value($row['status']) ?></span></td>
                    <td><?= escape_value(date('M j, Y', strtotime($row['created_at']))) ?></td>
                    <?php if (!$printView): ?><td class="no-print"><a class="view-link" href="members.php?<?= escape_value(http_build_query($baseQuery + ['member' => (string)$row['id']])) ?>">View details</a></td><?php endif; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
        <?php if (!$printView && $totalPages > 1): ?>
          <nav class="pagination no-print" aria-label="Member list pages">
            <span>Page <?= $page ?> of <?= $totalPages ?></span>
            <div class="pagination-links">
              <?php if ($page > 1): ?><a class="button secondary" href="members.php?<?= escape_value(http_build_query($baseQuery + ['page' => (string)($page - 1)])) ?>">Previous</a><?php endif; ?>
              <?php if ($page < $totalPages): ?><a class="button secondary" href="members.php?<?= escape_value(http_build_query($baseQuery + ['page' => (string)($page + 1)])) ?>">Next</a><?php endif; ?>
            </div>
          </nav>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  </main>
  <?php if ($printView): ?><script>window.addEventListener('load', () => window.print());</script><?php endif; ?>
</body>
</html>
