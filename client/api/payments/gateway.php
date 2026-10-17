<?php
declare(strict_types=1);

function payment_public_base_url(): ?string
{
    $baseUrl = rtrim((string)(getenv('HOU_PUBLIC_BASE_URL') ?: ''), '/');
    if ($baseUrl === '') {
        return null;
    }

    $parts = parse_url($baseUrl);
    if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || isset($parts['query']) || isset($parts['fragment'])) {
        return null;
    }
    $isHttps = strtolower($parts['scheme']) === 'https';
    $isLocalHttp = strtolower($parts['scheme']) === 'http'
        && in_array(strtolower($parts['host']), ['localhost', '127.0.0.1', '::1'], true);
    return ($isHttps || $isLocalHttp) && !isset($parts['user']) && !isset($parts['pass'])
        ? $baseUrl
        : null;
}

function payment_gateway_configuration(): ?array
{
    $clientId = trim((string)(getenv('HOU_IOTEC_CLIENT_ID') ?: ''));
    $clientSecret = trim((string)(getenv('HOU_IOTEC_CLIENT_SECRET') ?: ''));
    $walletId = trim((string)(getenv('HOU_IOTEC_WALLET_ID') ?: ''));
    $webhookSecret = (string)(getenv('HOU_IOTEC_WEBHOOK_SECRET') ?: '');
    $chargesCategory = trim((string)(getenv('HOU_IOTEC_TRANSACTION_CHARGES_CATEGORY') ?: 'ChargeCustomer'));
    $baseUrl = payment_public_base_url();

    if ($clientId === '' || $clientSecret === ''
        || preg_match('/\A[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}\z/i', $walletId) !== 1
        || strlen($webhookSecret) < 32 || !in_array($chargesCategory, ['ChargeWallet', 'ChargeCustomer'], true)
        || $baseUrl === null) {
        return null;
    }

    return [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'wallet_id' => $walletId,
        'webhook_secret' => $webhookSecret,
        'charges_category' => $chargesCategory,
        'base_url' => $baseUrl,
    ];
}

function normalize_iotec_amount_minor_units(mixed $amount): ?int
{
    if (is_float($amount)) {
        if (!is_finite($amount) || $amount < 0 || $amount > 2000000000) {
            return null;
        }
        $minorUnits = (int)round($amount * 100);
        return abs($amount * 100 - $minorUnits) < 0.00001 ? $minorUnits : null;
    }

    if (!is_int($amount) && !is_string($amount)) {
        return null;
    }

    $amount = (string)$amount;
    if (preg_match('/\A(0|[1-9]\d{0,9})(?:\.(\d{1,6}))?\z/', $amount, $matches) !== 1) {
        return null;
    }
    $fraction = $matches[2] ?? '';
    if (strlen($fraction) > 2 && trim(substr($fraction, 2), '0') !== '') {
        return null;
    }
    $fraction = str_pad(substr($fraction, 0, 2), 2, '0');
    return ((int)$matches[1] * 100) + (int)$fraction;
}

function iotec_collection_amount_matches(
    int $contributionAmount,
    mixed $providerAmount,
    mixed $providerCharges,
    string $chargesCategory
): bool {
    $amountMinorUnits = normalize_iotec_amount_minor_units($providerAmount);
    if ($amountMinorUnits === null) {
        return false;
    }

    $contributionMinorUnits = $contributionAmount * 100;
    if ($amountMinorUnits === $contributionMinorUnits) {
        return true;
    }
    if ($chargesCategory !== 'ChargeCustomer') {
        return false;
    }

    $chargesMinorUnits = normalize_iotec_amount_minor_units($providerCharges);
    return $chargesMinorUnits !== null
        && $chargesMinorUnits > 0
        && $amountMinorUnits >= $chargesMinorUnits
        && $amountMinorUnits - $chargesMinorUnits === $contributionMinorUnits;
}

function normalize_iotec_status(string $status): ?string
{
    return match (strtolower($status)) {
        'success' => 'successful',
        'failed', 'cancelled', 'rejected', 'rolledback' => 'failed',
        'pending', 'senttovendor', 'awaitingapproval', 'scheduled' => 'pending',
        default => null,
    };
}

function mark_payment_failed(PDO $pdo, string $transactionReference): void
{
    $update = $pdo->prepare(
        "UPDATE member_payments SET status = 'failed' WHERE transaction_reference = ? AND status = 'pending'"
    );
    $update->execute([$transactionReference]);
}
