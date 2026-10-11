<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

function send_signup_otp_email(string $recipient, string $name, string $code): void
{
    $autoloadPath = dirname(__DIR__, 3) . '/vendor/autoload.php';
    if (!is_file($autoloadPath)) {
        throw new RuntimeException('Composer dependencies are not installed.');
    }
    require_once $autoloadPath;

    $host = getenv('HOU_SMTP_HOST');
    $username = getenv('HOU_SMTP_USERNAME');
    $password = getenv('HOU_SMTP_PASSWORD');
    $encryption = strtolower(getenv('HOU_SMTP_ENCRYPTION') ?: 'ssl');
    $port = filter_var(getenv('HOU_SMTP_PORT') ?: '465', FILTER_VALIDATE_INT);
    if (!$host || !$username || !$password || !in_array($encryption, ['ssl', 'tls'], true) || $port === false) {
        throw new RuntimeException('SMTP is not configured correctly.');
    }

    $mailer = new PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = $host;
    $mailer->SMTPAuth = true;
    $mailer->Username = $username;
    $mailer->Password = $password;
    $mailer->SMTPSecure = $encryption === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mailer->Port = $port;
    $mailer->CharSet = PHPMailer::CHARSET_UTF8;
    $mailer->setFrom('info@hotspotoperators.org', 'Hotspot Operators Union');
    $mailer->addAddress($recipient, $name);
    $mailer->isHTML(true);
    $mailer->Subject = 'Your HOU email verification code';
    $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $mailer->Body = '<p>Hello ' . $safeName . ',</p>'
        . '<p>Your HOU registration verification code is:</p>'
        . '<p style="font-size:28px;font-weight:700;letter-spacing:8px">' . $code . '</p>'
        . '<p>This code expires in 10 minutes. If you did not request it, you can ignore this email.</p>'
        . '<p>Hotspot Operators Union</p>';
    $mailer->AltBody = "Hello {$name},\n\nYour HOU registration verification code is: {$code}\n"
        . "This code expires in 10 minutes. If you did not request it, you can ignore this email.\n\n"
        . 'Hotspot Operators Union';
    $mailer->send();
}
