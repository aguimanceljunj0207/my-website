
<?php

require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/config/db.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user';

    if ($fullName === '' || $username === '' || $password === '') {
        $message = 'Please complete all fields.';
        $messageType = 'error';
    } elseif (
        strlen($fullName) > 100 ||
        strlen($username) > 50 ||
        preg_match('/[\x00-\x1F\x7F]/', $username)
    ) {
        $message = 'Please check the name and username.';
        $messageType = 'error';
    } elseif (strlen($password) < 10) {
        $message = 'Password must be at least 10 characters.';
        $messageType = 'error';
    } elseif (!in_array($role, ['admin', 'user'], true)) {
        $message = 'Invalid account role.';
        $messageType = 'error';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                "INSERT INTO users
                    (full_name, username, password_hash, role)
                 VALUES (?, ?, ?, ?)"
            );

            $stmt->execute([
                $fullName,
                $username,
                $hash,
                $role
            ]);

            $message = 'Account created successfully.';
            $messageType = 'success';
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $message = 'That username already exists.';
            } else {
                error_log($e->getMessage());
                $message = 'Unable to create account.';
            }

            $messageType = 'error';
        }
    }
}

require_once __DIR__ . '/header.php';
?>

<div class="container">
    <div class="card">
        <h1>Create Account</h1>
        <p>Create an administrator or regular user account.</p>

        <?php if ($message !== ''): ?>
            <p class="<?= $messageType ?>">
                <?= htmlspecialchars(
                    $message,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>
        <?php endif; ?>

        <form method="post" action="add_user.php">
            <label for="full_name">Full Name</label>
            <input
                id="full_name"
                type="text"
                name="full_name"
                maxlength="100"
                required
            >

            <label for="username">Username</label>
            <input
                id="username"
                type="text"
                name="username"
                maxlength="50"
                required
            >

            <label for="password">Password</label>
            <input
                id="password"
                type="password"
                name="password"
                minlength="10"
                autocomplete="new-password"
                required
            >

            <label for="role">Account Type</label>
            <select id="role" name="role" required>
                <option value="user">User — View Only</option>
                <option value="admin">Admin — Manage Gradebook</option>
            </select>

            <button type="submit">Create Account</button>
        </form>

        <p><a href="index.php">Back to Dashboard</a></p>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
