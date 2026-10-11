<?php
// ── SMTP Configuration ─────────────────────────────────────────────────────
// Fill these in after creating the email account in Namecheap cPanel
define('SMTP_HOST',      'localhost');
define('SMTP_PORT',      25);
define('SMTP_USER',      'noreply@uneeco.store');
define('SMTP_PASS',      'BDO@Email123');
define('MAIL_TO',        'info@uneeco.co.ug');
define('MAIL_FROM',      'noreply@uneeco.store');
define('MAIL_FROM_NAME', 'Uneeco Website');
define('RECAPTCHA_SECRET', '6LcDLvAsAAAAAMnOJol4faUUVbDm8aVz6PT2L4CB');
// ──────────────────────────────────────────────────────────────────────────

header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed.']));
}

// Sanitize inputs
$email     = filter_var(trim($_POST['email']    ?? ''), FILTER_SANITIZE_EMAIL);
$whatsapp  = htmlspecialchars(trim($_POST['whatsapp'] ?? ''), ENT_QUOTES, 'UTF-8');
$subject   = htmlspecialchars(trim($_POST['subject']  ?? ''), ENT_QUOTES, 'UTF-8');
$message   = htmlspecialchars(trim($_POST['message']  ?? ''), ENT_QUOTES, 'UTF-8');
$recaptcha = trim($_POST['recaptcha'] ?? '');

// Validate required fields
if (!$email || !$whatsapp || !$subject || !$message) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'All fields are required.']));
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Invalid email address.']));
}

// Verify reCAPTCHA server-side
if ($recaptcha) {
    $rc     = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret=' . RECAPTCHA_SECRET . '&response=' . urlencode($recaptcha));
    $rcData = json_decode($rc, true);
    if (!$rcData['success']) {
        http_response_code(400);
        exit(json_encode(['success' => false, 'message' => 'reCAPTCHA verification failed. Please try again.']));
    }
}

// Load PHPMailer (download from https://github.com/PHPMailer/PHPMailer/releases
// and place the src/ folder at PHPMailer/src/ in this project root)
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$subjectLabels = [
    'quote'       => 'Request a Quote',
    'bulk'        => 'Bulk / Wholesale Order',
    'product'     => 'Product Information',
    'delivery'    => 'Delivery & Logistics',
    'partnership' => 'Partnership',
    'other'       => 'Other',
];
$subjectLabel = $subjectLabels[$subject] ?? $subject;

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = false;
    $mail->SMTPSecure = '';
    $mail->Port       = SMTP_PORT;

    $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    $mail->addAddress(MAIL_TO);
    $mail->addCC('bd@uneeco.co.ug');
    $mail->addReplyTo($email); // reply goes directly to the enquirer

    $mail->isHTML(true);
    $mail->Subject = $subjectLabel;
    $mail->Body    = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;color:#333;'>
            <div style='background:#1a3c2e;padding:15px 20px;border-radius:8px 8px 0 0;'>
                <h2 style='color:#fff;margin:0;font-size:1.1rem;'>New Enquiry at Uneeco's Website</h2>
            </div>
            <div style='background:#f9f9f9;padding:24px 32px;border-radius:0 0 8px 8px;border:1px solid #e5e5e5;'>
                <table style='width:100%;border-collapse:collapse;font-size:0.95rem;'>
                    <tr>
                        <td style='padding:10px 8px;font-weight:bold;width:140px;color:#555;'>From Email</td>
                        <td style='padding:10px 8px;'><a href='mailto:$email' style='color:#2d6a4f;'>$email</a></td>
                    </tr>
                    <tr style='background:#fff;'>
                        <td style='padding:10px 8px;font-weight:bold;color:#555;'>WhatsApp</td>
                        <td style='padding:10px 8px;'>$whatsapp</td>
                    </tr>
                    <tr>
                        <td style='padding:10px 8px;font-weight:bold;color:#555;'>Subject</td>
                        <td style='padding:10px 8px;'>$subjectLabel</td>
                    </tr>
                    <tr style='background:#fff;'>
                        <td style='padding:10px 8px;font-weight:bold;color:#555;vertical-align:top;'>Message</td>
                        <td style='padding:10px 8px;white-space:pre-line;'>$message</td>
                    </tr>
                </table>
                <p style='margin-top:24px;font-size:0.8rem;color:#999;'>
                    Sent via the contact form at uneeco.co.ug.
                </p>
            </div>
        </div>
    ";
    $mail->AltBody = "New Enquiry at Uneeco's Website\n\nFrom: $email\nWhatsApp: $whatsapp\nSubject: $subjectLabel\n\n$message";

    $mail->send();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DEBUG: ' . $e->getMessage()]);
}
