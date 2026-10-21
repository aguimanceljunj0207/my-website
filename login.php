
<?php
session_start();
require_once __DIR__ . '/config/db.php';

if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: index.php');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare(
        "SELECT user_id, username, full_name, password_hash, role
         FROM users
         WHERE username = ?
         LIMIT 1"
    );

    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);

        $_SESSION['user_id'] = (int) $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];

        header('Location: index.php');
        exit;
    }

    $message = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login | IM 101a Gradebook</title>

<style>
* {
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    margin: 0;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: linear-gradient(135deg, #0f172a, #115e59);
}

.login-card {
    background: white;
    width: 410px;
    max-width: 100%;
    padding: 32px;
    border-radius: 16px;
    box-shadow: 0 12px 35px #0003;
}

.logo {
    text-align: center;
    margin-bottom: 12px;
}

.logo img {
    width: 100px;
    height: 100px;
    object-fit: contain;
    display: block;
    margin: 0 auto;
}

h1 {
    text-align: center;
    font-size: 24px;
    color: #0f172a;
    margin-bottom: 8px;
}

.subtitle {
    text-align: center;
    color: #64748b;
    line-height: 1.6;
    font-size: 14px;
    margin-top: 0;
}

.datetime {
    background: #f0fdfa;
    border: 1px solid #ccfbf1;
    border-radius: 9px;
    padding: 12px;
    margin: 20px 0;
    text-align: center;
}

.date {
    font-size: 14px;
    color: #334155;
    margin-bottom: 6px;
}

.time {
    font-size: 23px;
    font-weight: bold;
    color: #0f766e;
    font-variant-numeric: tabular-nums;
}

label {
    display: block;
    margin-top: 18px;
    font-weight: bold;
    font-size: 14px;
    color: #1e293b;
}

input {
    width: 100%;
    padding: 13px;
    margin-top: 7px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    font-size: 15px;
}

input:focus {
    outline: 2px solid #99f6e4;
    border-color: #0f766e;
}

button {
    width: 100%;
    padding: 13px;
    margin-top: 24px;
    background: #0f766e;
    color: white;
    border: 0;
    border-radius: 7px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
}

button:hover {
    background: #115e59;
}

.error {
    padding: 12px;
    border-radius: 7px;
    background: #fee2e2;
    color: #991b1b;
    font-size: 14px;
}

.success {
    padding: 12px;
    border-radius: 7px;
    background: #dcfce7;
    color: #166534;
    font-size: 14px;
}

.footer {
    margin-top: 22px;
    text-align: center;
    color: #64748b;
    font-size: 12px;
}

@media (max-width: 480px) {
    .login-card {
        padding: 24px;
    }

    .logo img {
        width: 85px;
        height: 85px;
    }
}
</style>
</head>

<body>

<div class="login-card">

    <!-- SCHOOL OR GRADEBOOK LOGO -->
    <div class="logo">
        <img
            src="assets/cpsc.png"
            alt="IM 101a Gradebook Logo"
            onerror="this.style.display='none';"
        >
    </div>

    <h1>IM 101a Gradebook</h1>

    <p class="subtitle">
        Fundamentals of Database Systems<br>
    
    </p>

    <!-- AUTOMATIC DATE AND LIVE CLOCK -->
    <div class="datetime">
        <div class="date" id="currentDate">
            Loading date...
        </div>

        <div class="time" id="currentTime">
            --:--:--
        </div>
    </div>

    <?php if (isset($_GET['created'])): ?>
        <p class="success">
            Account created successfully. You can now sign in.
        </p>
    <?php endif; ?>

    <?php if ($message !== ''): ?>
        <p class="error">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form method="post" action="login.php">

        <label for="username">Username</label>
        <input
            type="text"
            id="username"
            name="username"
            maxlength="50"
            autocomplete="username"
            required
        >

        <label for="password">Password</label>
        <input
            type="password"
            id="password"
            name="password"
            autocomplete="current-password"
            required
        >

        <button type="submit">Sign In</button>
    </form>

    <div class="footer">
        Authorized users only<br>
        IM 101a Gradebook System
    </div>

</div>

<script>
function updateDateTime() {
    const now = new Date();

    const dateOptions = {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    };

    document.getElementById('currentDate').textContent =
        new Intl.DateTimeFormat('en-PH', dateOptions).format(now);

    document.getElementById('currentTime').textContent =
        new Intl.DateTimeFormat('en-PH', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true,
            timeZone: 'Asia/Manila'
        }).format(now);
}

updateDateTime();
setInterval(updateDateTime, 1000);
</script>

</body>
</html>
