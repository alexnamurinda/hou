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

function normalize_iotec_amount(mixed $amount): ?string
{
    if (is_int($amount)) {
        $amount = (string)$amount;
    } elseif (is_float($amount)) {
        if (!is_finite($amount) || floor($amount) !== $amount) {
            return null;
        }
        $amount = sprintf('%.0f', $amount);
    }

    return is_string($amount) && preg_match('/\A\d{1,12}\z/', $amount) === 1
        ? $amount
        : null;
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
