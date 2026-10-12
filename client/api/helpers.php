<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function set_api_headers(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}

function respond_json(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        header('Allow: ' . $method);
        respond_json(405, ['success' => false, 'message' => 'Method not allowed.']);
    }
}

function read_json_body(): array
{
    try {
        $body = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        respond_json(400, ['success' => false, 'message' => 'Invalid request data.']);
    }

    if (!is_array($body)) {
        respond_json(400, ['success' => false, 'message' => 'Invalid request data.']);
    }
    return $body;
}

function require_csrf_token(?array $body = null): void
{
    start_member_session();
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($body['csrf_token'] ?? $_POST['csrf_token'] ?? '');
    $stored = $_SESSION['csrf_token'] ?? '';
    if (!is_string($provided) || !is_string($stored) || $stored === '' || !hash_equals($stored, $provided)) {
        respond_json(419, ['success' => false, 'message' => 'Your session has expired. Refresh the page and try again.']);
    }
}

function csrf_token(): string
{
    start_member_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function normalize_phone(string $phone): ?string
{
    $phone = trim($phone);
    if (!preg_match('/^[0-9+\s().-]+$/', $phone)) {
        return null;
    }

    $phone = preg_replace('/[\s().-]/', '', $phone);
    if (str_starts_with($phone, '00')) {
        $phone = '+' . substr($phone, 2);
    } elseif (preg_match('/^0\d{9}$/', $phone)) {
        $phone = '+256' . substr($phone, 1);
    }

    return preg_match('/^\+[1-9]\d{7,14}$/', $phone) ? $phone : null;
}

function auth_attempt_key(string $purpose, string $identity): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return hash('sha256', $purpose . '|' . $ip . '|' . $identity);
}

function enforce_auth_rate_limit(string $key, int $maximum, int $windowSeconds): void
{
    $query = db_connect()->prepare(
        'SELECT COUNT(*) FROM auth_attempts WHERE attempt_key = ? AND attempted_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)'
    );
    $query->execute([$key, $windowSeconds]);
    if ((int)$query->fetchColumn() >= $maximum) {
        respond_json(429, ['success' => false, 'message' => 'Too many attempts. Please wait and try again.']);
    }
}

function record_auth_attempt(string $key): void
{
    $pdo = db_connect();
    if (random_int(1, 100) === 1) {
        $pdo->exec('DELETE FROM auth_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
    }
    $pdo->prepare('INSERT INTO auth_attempts (attempt_key) VALUES (?)')->execute([$key]);
}

function escape_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function member_first_name(string $fullName): string
{
    $parts = preg_split('/\s+/u', trim($fullName), 2);
    return $parts[0] ?? '';
}

function require_member(): array
{
    start_member_session();
    $memberId = $_SESSION['member_id'] ?? null;
    if (!is_int($memberId) || $memberId < 1) {
        header('Location: index.html#login', true, 303);
        exit;
    }

    $query = db_connect()->prepare(
        "SELECT id, registration_number, full_name, area_of_operation, phone, email, created_at FROM members WHERE id = ? AND status = 'active' LIMIT 1"
    );
    $query->execute([$memberId]);
    $member = $query->fetch();
    if (!$member) {
        $_SESSION = [];
        session_destroy();
        header('Location: index.html#login', true, 303);
        exit;
    }
    return $member;
}
