<?php
declare(strict_types=1);

function send_signup_otp_email(string $recipient, string $name, string $code): void
{
    require_once __DIR__ . '/../helpers.php';

    $firstName = member_first_name($name);
    $safeName = escape_html($firstName);
    $payload = [
        'sender' => [
            'name' => 'Hotspot Operators Union',
            'email' => 'info@hotspotoperators.org',
        ],
        'to' => [[
            'email' => $recipient,
            'name' => $name,
        ]],
        'subject' => 'Email Verification Code',
        'htmlContent' => '<p>Hello ' . $safeName . ',</p>'
            . '<p>Your HOU registration verification code is:</p>'
            . '<p style="font-size:28px;font-weight:700;letter-spacing:8px">' . $code . '</p>'
            . '<p>This code expires in 10 minutes. If you did not request it, you can ignore this email.</p>',
        'textContent' => "Hello {$firstName},\n\nYour HOU registration verification code is: {$code}\n"
            . "This code expires in 10 minutes. If you did not request it, you can ignore this email.",
    ];
    send_signup_email($payload);
}

function send_registration_number_email(string $recipient, string $name, string $registrationNumber): void
{
    require_once __DIR__ . '/../helpers.php';

    $firstName = member_first_name($name);
    $safeName = escape_html($firstName);
    $safeRegistrationNumber = escape_html($registrationNumber);
    $payload = [
        'sender' => [
            'name' => 'Hotspot Operators Union',
            'email' => 'info@hotspotoperators.org',
        ],
        'to' => [[
            'email' => $recipient,
            'name' => $name,
        ]],
        'subject' => 'Your HOU Registration Number',
        'htmlContent' => '<p>Hi ' . $safeName . ',</p>'
            . '<p>Your HOU registration is complete. Your registration number is:</p>'
            . '<p style="font-size:28px;font-weight:700;letter-spacing:4px">' . $safeRegistrationNumber . '</p>'
            . '<p>Please keep this number safe. Your membership certificate will be provided in the members portal.</p>'
            . '<p>Welcome to the Hotspot Operators Union.</p>',
        'textContent' => "Hi {$firstName},\n\nYour HOU registration is complete. Your registration number is: "
            . "{$registrationNumber}\n\nPlease keep this number safe. Your membership certificate will be provided "
            . 'in the members portal.' . "\n\nWelcome to the Hotspot Operators Union.",
    ];
    send_signup_email($payload);
}

function send_signup_email(array $payload): void
{
    $apiKey = getenv('HOU_BREVO_API_KEY');
    if ($apiKey === false || trim($apiKey) === '') {
        throw new RuntimeException('Brevo API key is not configured.');
    }
    if (!extension_loaded('curl')) {
        throw new RuntimeException('The PHP cURL extension is required for Brevo email delivery.');
    }
    $apiKey = trim($apiKey);
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $curl = curl_init('https://api.brevo.com/v3/smtp/email');
    if ($curl === false) {
        throw new RuntimeException('Could not initialize the Brevo API request.');
    }

    $configured = curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'api-key: ' . $apiKey,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
    ]);
    if (!$configured) {
        curl_close($curl);
        throw new RuntimeException('Could not configure the Brevo API request.');
    }

    $response = curl_exec($curl);
    $curlError = curl_error($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($response === false) {
        throw new RuntimeException('Brevo API request failed: ' . $curlError);
    }
    if ($status !== 201) {
        $responseData = json_decode($response, true);
        $message = is_array($responseData) && isset($responseData['message']) && is_string($responseData['message'])
            ? ': ' . $responseData['message']
            : '';
        throw new RuntimeException("Brevo API returned HTTP {$status}{$message}.");
    }
}
