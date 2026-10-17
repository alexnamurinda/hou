<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/gateway.php';

set_api_headers();
require_method('POST');
$configuration = payment_gateway_configuration();
if ($configuration === null) {
    respond_json(503, ['success' => false, 'message' => 'Payment callback is not configured.']);
}

$providedSecret = $_SERVER['HTTP_X_HOU_WEBHOOK_SECRET'] ?? '';
if (!is_string($providedSecret) || !hash_equals($configuration['webhook_secret'], $providedSecret)) {
    respond_json(401, ['success' => false, 'message' => 'Invalid payment callback credentials.']);
}

$rawBody = file_get_contents('php://input');
if ($rawBody === false) {
    respond_json(400, ['success' => false, 'message' => 'Invalid callback data.']);
}
try {
    $event = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    respond_json(400, ['success' => false, 'message' => 'Invalid callback data.']);
}
if (!is_array($event)) {
    respond_json(400, ['success' => false, 'message' => 'Invalid callback data.']);
}

$providerReference = $event['id'] ?? null;
$transactionReference = $event['externalId'] ?? null;
$providerAmount = normalize_iotec_amount($event['amount'] ?? null);
$providerStatus = is_string($event['status'] ?? null)
    ? normalize_iotec_status($event['status'])
    : null;
if (!is_string($providerReference)
    || preg_match('/\A[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}\z/i', $providerReference) !== 1
    || !is_string($transactionReference)
    || preg_match('/\AHOU[A-F0-9]{16}\z/', $transactionReference) !== 1
    || $providerAmount === null
    || ($event['currency'] ?? null) !== 'UGX'
    || $providerStatus === null) {
    respond_json(400, ['success' => false, 'message' => 'Invalid callback data.']);
}

$pdo = db_connect();
$pdo->beginTransaction();
try {
    $lookup = $pdo->prepare(
        'SELECT id, amount_ugx, status, provider_reference '
        . 'FROM member_payments WHERE transaction_reference = ? FOR UPDATE'
    );
    $lookup->execute([$transactionReference]);
    $payment = $lookup->fetch();
    if (!$payment) {
        $pdo->rollBack();
        respond_json(404, ['success' => false, 'message' => 'Payment reference not found.']);
    }
    if ((string)$payment['amount_ugx'] !== $providerAmount) {
        $pdo->rollBack();
        error_log('HOU IOTEC callback amount mismatch for ' . $transactionReference . '.');
        respond_json(422, ['success' => false, 'message' => 'Payment amount does not match.']);
    }
    if ($payment['provider_reference'] !== null && $payment['provider_reference'] !== $providerReference) {
        $pdo->rollBack();
        error_log('HOU IOTEC callback transaction ID mismatch for ' . $transactionReference . '.');
        respond_json(422, ['success' => false, 'message' => 'Payment transaction does not match.']);
    }

    if ($payment['status'] === 'pending') {
        $update = $pdo->prepare(
            'UPDATE member_payments SET provider_reference = ?, status = ?, '
            . "paid_at = CASE WHEN ? = 'successful' THEN NOW() ELSE NULL END WHERE id = ?"
        );
        $update->execute([$providerReference, $providerStatus, $providerStatus, $payment['id']]);
    }
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $error;
}

respond_json(200, ['success' => true, 'message' => 'Payment status recorded.']);
