
<?php

require_once __DIR__ . '/auth.php';

if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit(
        'Access denied. Only administrators can perform this action.'
    );
}
