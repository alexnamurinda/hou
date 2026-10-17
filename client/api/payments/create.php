<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/gateway.php';

set_api_headers();
require_method('POST');
$body = read_json_body();
require_csrf_token($body);
$member = require_member();

$paymentType = $body['payment_type'] ?? null;
$paymentMethod = $body['payment_method'] ?? null;
$fixedAmounts = [
    'membership' => 600000,
    'supervision' => 100000,
];
if (!is_string($paymentType) || !in_array($paymentType, ['voluntary', 'membership', 'supervision'], true)) {
    respond_json(422, ['success' => false, 'message' => 'Choose a valid payment type.']);
}
if ($paymentMethod !== 'mobile_money') {
    respond_json(422, ['success' => false, 'message' => 'Choose a supported payment method.']);
}

if (isset($fixedAmounts[$paymentType])) {
    $amount = $fixedAmounts[$paymentType];
} else {
    $amountValue = $body['amount'] ?? null;
    if (!is_int($amountValue) && !(is_string($amountValue) && preg_match('/\A[1-9]\d{0,9}\z/', $amountValue) === 1)) {
        respond_json(422, ['success' => false, 'message' => 'Enter a valid voluntary contribution amount in whole UGX.']);
    }
    $amount = (int)$amountValue;
    if ($amount < 500 || $amount > 1000000000) {
        respond_json(422, ['success' => false, 'message' => 'IOTEC accepts voluntary contributions from UGX 500 to UGX 1,000,000,000.']);
    }
}

$payerPhone = is_string($body['payer_phone'] ?? null) ? normalize_phone($body['payer_phone']) : null;
if ($payerPhone === null || !str_starts_with($payerPhone, '+256')) {
    respond_json(422, ['success' => false, 'message' => 'Enter a valid Ugandan mobile money number, including the +256 country code.']);
}
$payerMsisdn = '0' . substr($payerPhone, 4);
if (preg_match('/\A0\d{9}\z/', $payerMsisdn) !== 1) {
    respond_json(422, ['success' => false, 'message' => 'Enter a valid Ugandan mobile money number.']);
}

$configuration = payment_gateway_configuration();
if ($configuration === null) {
    respond_json(503, [
        'success' => false,
        'message' => 'Online payments are not configured yet. Please contact HOU before trying again.',
    ]);
}
if (!function_exists('curl_init')) {
    error_log('HOU payment initiation requires the PHP cURL extension.');
    respond_json(503, ['success' => false, 'message' => 'Online payments are temporarily unavailable. Please try again later.']);
}

$pdo = db_connect();
$transactionReference = 'HOU' . strtoupper(bin2hex(random_bytes(8)));
$insert = $pdo->prepare(
    "INSERT INTO member_payments
        (member_id, transaction_reference, payment_type, payment_method, amount_ugx, payer_phone, status)
     VALUES (?, ?, ?, ?, ?, ?, 'pending')"
);
$insert->execute([$member['id'], $transactionReference, $paymentType, $paymentMethod, $amount, $payerPhone]);

$tokenCurl = curl_init('https://id.iotec.io/connect/token');
if ($tokenCurl === false) {
    error_log('HOU could not initialize cURL for IOTEC authentication.');
    mark_payment_failed($pdo, $transactionReference);
    respond_json(503, ['success' => false, 'message' => 'Online payments are temporarily unavailable. Please try again later.']);
}
curl_setopt_array($tokenCurl, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'client_id' => $configuration['client_id'],
        'client_secret' => $configuration['client_secret'],
        'grant_type' => 'client_credentials',
    ]),
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded',
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 20,
]);
$tokenResponse = curl_exec($tokenCurl);
$tokenErrorNumber = curl_errno($tokenCurl);
$tokenHttpStatus = (int)curl_getinfo($tokenCurl, CURLINFO_RESPONSE_CODE);
curl_close($tokenCurl);
if ($tokenResponse === false || $tokenHttpStatus < 200 || $tokenHttpStatus >= 300) {
    error_log('HOU IOTEC OAuth request failed with HTTP ' . $tokenHttpStatus . ' and cURL code ' . $tokenErrorNumber . '.');
    mark_payment_failed($pdo, $transactionReference);
    respond_json(502, ['success' => false, 'message' => 'Could not connect to the payment provider. Please try again later.']);
}

try {
    $tokenResult = json_decode($tokenResponse, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    error_log('HOU IOTEC OAuth endpoint returned invalid JSON.');
    mark_payment_failed($pdo, $transactionReference);
    respond_json(502, ['success' => false, 'message' => 'The payment provider returned an invalid response. Please try again later.']);
}
if (!is_array($tokenResult) || !is_string($tokenResult['access_token'] ?? null) || $tokenResult['access_token'] === '') {
    error_log('HOU IOTEC OAuth response did not contain an access token.');
    mark_payment_failed($pdo, $transactionReference);
    respond_json(502, ['success' => false, 'message' => 'The payment provider did not authorize this request. Please try again later.']);
}

$paymentDescription = match ($paymentType) {
    'membership' => 'HOU membership fee',
    'supervision' => 'HOU supervision fee',
    default => 'HOU voluntary contribution',
};
$returnUrl = $configuration['base_url'] . '/member.php?section=payments&reference=' . rawurlencode($transactionReference);
$gatewayPayload = [
    'category' => 'MobileMoney',
    'currency' => 'UGX',
    'walletId' => $configuration['wallet_id'],
    'externalId' => $transactionReference,
    'payer' => $payerMsisdn,
    'payerName' => (string)$member['full_name'],
    'payerNote' => $paymentDescription,
    'amount' => $amount,
    'payeeNote' => 'HOU member ' . (string)$member['registration_number'],
    'transactionChargesCategory' => $configuration['charges_category'],
    'redirectUrl' => $returnUrl,
];
$collectionCurl = curl_init('https://pay.iotec.io/api/collections/collect');
if ($collectionCurl === false) {
    error_log('HOU could not initialize cURL for IOTEC collection.');
    mark_payment_failed($pdo, $transactionReference);
    respond_json(503, ['success' => false, 'message' => 'Online payments are temporarily unavailable. Please try again later.']);
}
curl_setopt_array($collectionCurl, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($gatewayPayload, JSON_THROW_ON_ERROR),
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . $tokenResult['access_token'],
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30,
]);
$collectionResponse = curl_exec($collectionCurl);
$collectionErrorNumber = curl_errno($collectionCurl);
$collectionHttpStatus = (int)curl_getinfo($collectionCurl, CURLINFO_RESPONSE_CODE);
curl_close($collectionCurl);
if ($collectionResponse === false || $collectionHttpStatus < 200 || $collectionHttpStatus >= 300) {
    error_log('HOU IOTEC collection request failed with HTTP ' . $collectionHttpStatus . ' and cURL code ' . $collectionErrorNumber . ' for ' . $transactionReference . '.');
    if ($collectionResponse !== false && $collectionHttpStatus >= 400 && $collectionHttpStatus < 500) {
        mark_payment_failed($pdo, $transactionReference);
    }
    respond_json(502, [
        'success' => false,
        'message' => 'The payment provider did not confirm the request. Check your payment history before retrying.',
        'reference' => $transactionReference,
    ]);
}

try {
    $collectionResult = json_decode($collectionResponse, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    error_log('HOU IOTEC collection endpoint returned invalid JSON for ' . $transactionReference . '.');
    respond_json(502, [
        'success' => false,
        'message' => 'The payment provider returned an unreadable response. Check your payment history before retrying.',
        'reference' => $transactionReference,
    ]);
}

$providerReference = is_array($collectionResult) ? ($collectionResult['id'] ?? null) : null;
$responseAmount = is_array($collectionResult) ? normalize_iotec_amount($collectionResult['amount'] ?? null) : null;
$providerStatus = is_array($collectionResult) && is_string($collectionResult['status'] ?? null)
    ? normalize_iotec_status($collectionResult['status'])
    : null;
if (!is_array($collectionResult)
    || !is_string($providerReference)
    || preg_match('/\A[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}\z/i', $providerReference) !== 1
    || ($collectionResult['externalId'] ?? null) !== $transactionReference
    || $responseAmount !== (string)$amount
    || ($collectionResult['currency'] ?? null) !== 'UGX'
    || $providerStatus === null) {
    error_log('HOU IOTEC collection endpoint returned an unexpected response for ' . $transactionReference . '.');
    respond_json(502, [
        'success' => false,
        'message' => 'The payment provider returned an invalid response. Check your payment history before retrying.',
        'reference' => $transactionReference,
    ]);
}

$pdo->beginTransaction();
try {
    $currentStatusQuery = $pdo->prepare(
        'SELECT status FROM member_payments WHERE transaction_reference = ? FOR UPDATE'
    );
    $currentStatusQuery->execute([$transactionReference]);
    $currentStatus = $currentStatusQuery->fetchColumn();
    if ($currentStatus === false) {
        throw new RuntimeException('HOU payment transaction disappeared before its IOTEC response was saved.');
    }
    $finalStatus = $currentStatus === 'pending' ? $providerStatus : $currentStatus;
    $update = $pdo->prepare(
        'UPDATE member_payments SET provider_reference = ?, status = ?, '
        . "paid_at = CASE WHEN ? = 'successful' THEN COALESCE(paid_at, NOW()) ELSE paid_at END "
        . 'WHERE transaction_reference = ?'
    );
    $update->execute([$providerReference, $finalStatus, $finalStatus, $transactionReference]);
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $error;
}

$message = match ($finalStatus) {
    'successful' => 'Payment confirmed.',
    'failed' => 'The payment was not completed.',
    default => 'Approve the mobile money prompt on your phone. Your contribution will update when IOTEC confirms it.',
};
respond_json(200, [
    'success' => true,
    'message' => $message,
    'reference' => $transactionReference,
    'status' => $finalStatus,
    'checkout_url' => null,
]);
