
<?php

session_start();
require_once __DIR__ . '/config/db.php';

$count = (int)$pdo->query(
    "SELECT COUNT(*) FROM users"
)->fetchColumn();

if ($count > 0) {
    http_response_code(403);
    exit(
        'Setup disabled because an account already exists. ' .
        'Use phpMyAdmin to assign an existing account the admin role.'
    );
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($fullName === '' || $username === '' || $password === '') {
        $message = 'Complete all fields.';
    } elseif (strlen($password) < 10) {
        $message = 'Use a password with at least 10 characters.';
    } elseif (
        strlen($fullName) > 100 ||
        strlen($username) > 50
    ) {
        $message = 'The name or username is too long.';
    } else {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO users
                    (full_name, username, password_hash, role)
                 VALUES (?, ?, ?, 'admin')"
            );

            $stmt->execute([
                $fullName,
                $username,
                password_hash($password, PASSWORD_DEFAULT)
            ]);

            header('Location: login.php?created=1');
            exit;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $message = 'Could not create account. Check the username.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Initial Admin Setup</title>
<style>
body {
    font-family: Arial, sans-serif;
    background: #f1f5f9;
    padding: 25px;
}
.card {
    max-width: 420px;
    margin: 30px auto;
    padding: 25px;
    background: white;
    border-radius: 12px;
}
input, button {
    box-sizing: border-box;
    display: block;
    width: 100%;
    padding: 12px;
    margin: 12px 0;
}
button {
    color: white;
    background: #0f766e;
    border: 0;
    cursor: pointer;
}
</style>
</head>
<body>
<div class="card">
    <h2>Create First Administrator</h2>

    <?php if ($message !== ''): ?>
        <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form method="post">
        <label>Full Name</label>
        <input name="full_name" maxlength="100" required>

        <label>Username</label>
        <input name="username" maxlength="50" required>

        <label>Password (minimum 10 characters)</label>
        <input type="password" name="password"
               minlength="10" required>

        <button type="submit">Create Administrator</button>
    </form>
</div>
</body>
</html>
