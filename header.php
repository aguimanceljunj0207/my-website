<?php
require_once __DIR__ . '/config/db.php';
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IM101a</title>

    <!-- Browser tab icon -->
    <link rel="icon" type="image/png" href="assets/cpsc.png">

    <!-- Website design -->
    <link rel="stylesheet" href="assets/style.css">
</head>

<body>
<div class="nav">
<a href="index.php">Dashboard</a>
<a href="students.php">Students</a>
<a href="grades.php">Grades</a>
<a href="summary.php">Final Summary</a>
</div>


<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>IM 101a Gradebook</title>
<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #f1f5f9;
    color: #0f172a;
    font-family: Arial, sans-serif;
}

.navbar {
    background: #0f172a;
    color: white;
    padding: 16px 5%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

.brand {
    font-size: 18px;
    font-weight: bold;
}

.nav-links {
    display: flex;
    gap: 14px;
    align-items: center;
    flex-wrap: wrap;
}

.navbar a {
    color: white;
    text-decoration: none;
}

.navbar a:hover {
    text-decoration: underline;
}

.container {
    max-width: 1150px;
    margin: 30px auto;
    padding: 0 20px;
}

.grid {
    display: grid;
    grid-template-columns: repeat(
        auto-fit, minmax(200px, 1fr)
    );
    gap: 20px;
    margin: 25px 0;
}

.card {
    background: white;
    padding: 22px;
    border-radius: 12px;
    box-shadow: 0 3px 12px #0000000d;
    margin-bottom: 20px;
}

.stat {
    font-size: 34px;
    font-weight: bold;
    margin-top: 10px;
}

.muted {
    color: #64748b;
}

.notice {
    padding: 14px;
    border-radius: 8px;
    background: #ccfbf1;
    color: #115e59;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
    background: white;
}

th, td {
    padding: 12px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
}

th {
    background: #f8fafc;
}

input, select {
    width: 100%;
    padding: 11px;
    margin: 7px 0 15px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
}

button, .btn {
    display: inline-block;
    padding: 11px 16px;
    background: #0f766e;
    color: white;
    text-decoration: none;
    border: 0;
    border-radius: 6px;
    cursor: pointer;
}

.logout-btn {
    background: #b91c1c;
    padding: 8px 12px;
    border-radius: 6px;
}

.success {
    color: #166534;
}

.error {
    color: #b91c1c;
}

.footer {
    text-align: center;
    color: #64748b;
    padding: 25px;
}
</style>

</head>
<body>

<nav class="navbar">
    <div class="brand">
        IM 101a Gradebook
    </div>

    <div class="nav-links">
        <a href="index.php">Dashboard</a>

        <?php if (isset($_SESSION['user_id'], $_SESSION['role'])): ?>

            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="add_user.php">Manage Accounts</a>
                <a href="manage_users.php">Manage Users</a>
            <?php endif; ?>

            <span>
                <?= htmlspecialchars(
                    $_SESSION['full_name'] ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
                (<?= htmlspecialchars(
                    $_SESSION['role'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>)
            </span>

            <a class="logout-btn" href="logout.php">Logout</a>

        <?php endif; ?>
    </div>
</nav>
