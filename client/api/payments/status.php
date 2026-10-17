<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

set_api_headers();
require_method('GET');
$member = require_member();
$reference = trim((string)($_GET['reference'] ?? ''));
if (preg_match('/\AHOU[A-F0-9]{16}\z/', $reference) !== 1) {
    respond_json(422, ['success' => false, 'message' => 'Invalid transaction reference.']);
}

$query = db_connect()->prepare(
    'SELECT status FROM member_payments WHERE transaction_reference = ? AND member_id = ? LIMIT 1'
);
$query->execute([$reference, $member['id']]);
$status = $query->fetchColumn();
if ($status === false) {
    respond_json(404, ['success' => false, 'message' => 'Payment reference not found.']);
}
respond_json(200, ['success' => true, 'status' => $status]);
