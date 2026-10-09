<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$message = trim((string)($_POST['message'] ?? ''));
if ($message === '') {
    echo json_encode(['reply' => 'Please share your question and we will help you as part of the Hotspot Operators Union.']);
    exit;
}

echo json_encode([
    'reply' => 'Thank you for reaching out. The Hotspot Operators Union supports members with advocacy, training, and business guidance. Please contact the union office through the contact page for direct support.'
]);
