
<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (
    !isset($_SESSION['user_id'], $_SESSION['role']) ||
    !in_array($_SESSION['role'], ['admin', 'user'], true)
) {
    header('Location: login.php');
    exit;
}

// Allow regular users to view these pages.
// Admins can access all pages.
if ($_SESSION['role'] === 'user') {

    $currentPage = strtolower(
        basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))
    );

    $allowedPages = [
        'index.php',
        'students.php',
        'grades.php',
        'summary.php',
        'logout.php'
    ];

    if (!in_array($currentPage, $allowedPages, true)) {
        header('Location: index.php');
        exit;
    }
}
