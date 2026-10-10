<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
set_api_headers();
require_method('GET');

db_connect();

respond_json(200, ['success' => true, 'csrf_token' => csrf_token()]);
