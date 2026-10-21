
<?php
require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/config/db.php';

/*
|--------------------------------------------------------------------------
| SESSION AND SECURITY
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];
$currentUserId = (int) $_SESSION['user_id'];

$message = '';
$messageType = 'info';

/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/

function escapeHtml($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| HANDLE DELETE REQUEST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($postedToken) ||
        !hash_equals($csrfToken, $postedToken)
    ) {
        http_response_code(403);
        exit('Invalid security token. Refresh the page and try again.');
    }

    $deleteUserId = filter_input(
        INPUT_POST,
        'user_id',
        FILTER_VALIDATE_INT
    );

    if (!$deleteUserId || $deleteUserId < 1) {
        $message = 'Invalid user account selected.';
        $messageType = 'error';

    } elseif ($deleteUserId === $currentUserId) {
        $message = 'You cannot delete your own account.';
        $messageType = 'error';

    } else {
        try {
            $stmt = $pdo->prepare(
                "SELECT user_id, username, role
                 FROM users
                 WHERE user_id = ?
                 LIMIT 1"
            );

            $stmt->execute([$deleteUserId]);
            $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$targetUser) {
                $message = 'The selected account was not found.';
                $messageType = 'error';

            } elseif ($targetUser['role'] === 'admin') {
                $message = 'Administrator accounts are protected.';
                $messageType = 'error';

            } else {
                $stmt = $pdo->prepare(
                    "DELETE FROM users
                     WHERE user_id = ?
                       AND role = 'user'"
                );

                $stmt->execute([$deleteUserId]);

                if ($stmt->rowCount() === 1) {
                    $message = 'User account deleted successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'The account could not be deleted.';
                    $messageType = 'error';
                }
            }
        } catch (PDOException $e) {
            error_log('User deletion failed: ' . $e->getMessage());

            $message = 'Unable to delete the account. Please try again.';
            $messageType = 'error';
        }
    }
}

/*
|--------------------------------------------------------------------------
| SEARCH USERS
|--------------------------------------------------------------------------
*/

$search = trim($_GET['q'] ?? '');

if (strlen($search) > 100) {
    $search = substr($search, 0, 100);
}

/*
|--------------------------------------------------------------------------
| GET ACCOUNT STATISTICS
|--------------------------------------------------------------------------
*/

$statsStmt = $pdo->query(
    "SELECT
        COUNT(*) AS total_users,
        COALESCE(SUM(role = 'admin'), 0) AS total_admins,
        COALESCE(SUM(role = 'user'), 0) AS total_regular_users
     FROM users"
);

$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

$totalUsers = (int) $stats['total_users'];
$totalAdmins = (int) $stats['total_admins'];
$totalRegularUsers = (int) $stats['total_regular_users'];

/*
|--------------------------------------------------------------------------
| FETCH USER ACCOUNTS
|--------------------------------------------------------------------------
*/

if ($search !== '') {
    $stmt = $pdo->prepare(
        "SELECT user_id, full_name, username, role, created_at
         FROM users
         WHERE full_name LIKE ?
            OR username LIKE ?
         ORDER BY user_id DESC"
    );

    $keyword = '%' . $search . '%';

    $stmt->execute([$keyword, $keyword]);

} else {
    $stmt = $pdo->query(
        "SELECT user_id, full_name, username, role, created_at
         FROM users
         ORDER BY user_id DESC"
    );
}

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1">

<title>Manage Users | IM 101a Gradebook</title>

<style>
:root {
    --primary: #0f766e;
    --primary-dark: #115e59;
    --background: #f1f5f9;
    --surface: #ffffff;
    --text: #0f172a;
    --muted: #64748b;
    --border: #e2e8f0;
    --danger: #b91c1c;
    --success: #166534;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: var(--background);
    color: var(--text);
}

/* NAVIGATION */

.navbar {
    background: #0f172a;
    color: white;
    padding: 17px 5%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 15px;
}

.brand {
    font-size: 20px;
    font-weight: bold;
}

.nav-links {
    display: flex;
    align-items: center;
    gap: 18px;
    flex-wrap: wrap;
}

.nav-links a {
    color: #e2e8f0;
    text-decoration: none;
    font-size: 14px;
}

.nav-links a:hover {
    color: #5eead4;
}

.admin-label {
    color: #99f6e4;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

/* MAIN CONTENT */

.page {
    max-width: 1250px;
    margin: 32px auto;
    padding: 0 22px;
}

.page-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 25px;
}

.page-heading h1 {
    margin: 0 0 8px;
    font-size: 28px;
}

.page-heading p {
    margin: 0;
    color: var(--muted);
    line-height: 1.5;
}

.back-link {
    display: inline-block;
    padding: 11px 15px;
    border-radius: 8px;
    background: white;
    color: var(--primary);
    border: 1px solid var(--border);
    text-decoration: none;
    font-weight: bold;
}

/* SUMMARY CARDS */

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 25px;
}

.stat-card {
    background: var(--surface);
    padding: 23px;
    border: 1px solid var(--border);
    border-radius: 13px;
    box-shadow: 0 3px 12px #0f172a08;
}

.stat-label {
    color: var(--muted);
    font-size: 14px;
    margin-bottom: 12px;
}

.stat-value {
    font-size: 30px;
    font-weight: bold;
}

.stat-total {
    border-top: 4px solid var(--primary);
}

.stat-admin {
    border-top: 4px solid #6366f1;
}

.stat-user {
    border-top: 4px solid #0ea5e9;
}

/* ALERTS */

.alert {
    padding: 14px 16px;
    border-radius: 9px;
    margin-bottom: 20px;
    line-height: 1.5;
}

.alert-success {
    color: var(--success);
    background: #dcfce7;
    border: 1px solid #bbf7d0;
}

.alert-error {
    color: #991b1b;
    background: #fee2e2;
    border: 1px solid #fecaca;
}

.alert-info {
    color: #1e40af;
    background: #dbeafe;
    border: 1px solid #bfdbfe;
}

/* TABLE PANEL */

.panel {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 13px;
    overflow: hidden;
    box-shadow: 0 3px 12px #0f172a08;
}

.panel-header {
    padding: 22px;
    border-bottom: 1px solid var(--border);
}

.panel-header h2 {
    margin: 0 0 7px;
    font-size: 19px;
}

.panel-header p {
    margin: 0;
    color: var(--muted);
    font-size: 13px;
}

.search-form {
    display: flex;
    gap: 10px;
    margin-top: 18px;
}

.search-input {
    flex: 1;
    min-width: 0;
    padding: 12px 14px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 14px;
}

.search-input:focus {
    outline: 2px solid #99f6e4;
    border-color: var(--primary);
}

.btn {
    border: 0;
    border-radius: 8px;
    padding: 11px 15px;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-primary:hover {
    background: var(--primary-dark);
}

.btn-danger {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.btn-danger:hover {
    background: #fecaca;
}

.btn-secondary {
    background: #f1f5f9;
    color: #334155;
    border: 1px solid var(--border);
}

/* TABLE */

.table-wrap {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    white-space: nowrap;
}

th {
    background: #f8fafc;
    color: #475569;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .04em;
}

th, td {
    padding: 15px 18px;
    text-align: left;
    border-bottom: 1px solid var(--border);
}

td {
    font-size: 14px;
}

tbody tr:hover {
    background: #f8fafc;
}

tbody tr:last-child td {
    border-bottom: none;
}

.user-name {
    font-weight: bold;
    color: #0f172a;
}

.user-id {
    color: var(--muted);
    font-size: 12px;
}

.role {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.role-admin {
    color: #4338ca;
    background: #e0e7ff;
}

.role-user {
    color: #0369a1;
    background: #e0f2fe;
}

.protected {
    color: var(--muted);
    font-size: 12px;
}

.delete-form {
    display: inline;
}

.empty-state {
    text-align: center;
    padding: 45px 20px;
    color: var(--muted);
}

.empty-state strong {
    display: block;
    color: var(--text);
    font-size: 17px;
    margin-bottom: 8px;
}

.panel-footer {
    padding: 15px 20px;
    background: #f8fafc;
    border-top: 1px solid var(--border);
    color: var(--muted);
    font-size: 13px;
}

/* MOBILE */

@media (max-width: 760px) {
    .navbar {
        padding: 16px 20px;
    }

    .nav-links {
        gap: 12px;
    }

    .page {
        margin: 22px auto;
        padding: 0 14px;
    }

    .page-heading h1 {
        font-size: 23px;
    }

    .stats-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .stat-card {
        padding: 17px;
    }

    .stat-value {
        font-size: 25px;
    }

    .search-form {
        flex-wrap: wrap;
    }

    .search-input {
        flex-basis: 100%;
    }

    .search-form .btn {
        flex: 1;
    }
}
</style>
</head>

<body>

<!-- NAVIGATION -->
<nav class="navbar">
    <div class="brand">IM 101a Gradebook</div>

    <div class="nav-links">
        <a href="index.php">Dashboard</a>
        <a href="manage_users.php">Manage Users</a>
        <a href="add_user.php">Add Account</a>

        <span class="admin-label">Administrator</span>

        <span>
            <?= escapeHtml($_SESSION['full_name'] ?? 'Admin') ?>
        </span>

        <a href="logout.php">Logout</a>
    </div>
</nav>

<main class="page">

    <!-- PAGE HEADING -->
    <section class="page-heading">
        <div>
            <h1>User Management</h1>
            <p>View, search, and manage Gradebook accounts.</p>
        </div>

        <a class="back-link" href="index.php">
            &larr; Back to Dashboard
        </a>
    </section>

    <!-- SUMMARY CARDS -->
    <section class="stats-grid">

        <div class="stat-card stat-total">
            <div class="stat-label">Total Accounts</div>
            <div class="stat-value">
                <?= $totalUsers ?>
            </div>
        </div>

        <div class="stat-card stat-admin">
            <div class="stat-label">Administrator Accounts</div>
            <div class="stat-value">
                <?= $totalAdmins ?>
            </div>
        </div>

        <div class="stat-card stat-user">
            <div class="stat-label">Regular User Accounts</div>
            <div class="stat-value">
                <?= $totalRegularUsers ?>
            </div>
        </div>

    </section>

    <!-- FEEDBACK -->
    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= escapeHtml($messageType) ?>"
             role="status">
            <?= escapeHtml($message) ?>
        </div>
    <?php endif; ?>

    <!-- USER TABLE -->
    <section class="panel">

        <div class="panel-header">
            <h2>Registered Accounts</h2>
            <p>Administrator accounts are protected from deletion.</p>

            <form class="search-form" method="get"
                  action="manage_users.php">

                <input
                    class="search-input"
                    type="search"
                    name="q"
                    value="<?= escapeHtml($search) ?>"
                    maxlength="100"
                    placeholder="Search full name or username..."
                    aria-label="Search user accounts"
                >

                <button class="btn btn-primary" type="submit">
                    Search
                </button>

                <?php if ($search !== ''): ?>
                    <a class="btn btn-secondary"
                       href="manage_users.php">
                        Clear
                    </a>
                <?php endif; ?>

            </form>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Account</th>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Date Created</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($users) > 0): ?>

                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <span class="user-id">
                                    #<?= (int) $user['user_id'] ?>
                                </span>
                            </td>

                            <td>
                                <span class="user-name">
                                    <?= escapeHtml($user['full_name']) ?>
                                </span>
                            </td>

                            <td>
                                <?= escapeHtml($user['username']) ?>
                            </td>

                            <td>
                                <?php if ($user['role'] === 'admin'): ?>
                                    <span class="role role-admin">
                                        Admin
                                    </span>
                                <?php else: ?>
                                    <span class="role role-user">
                                        User
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= escapeHtml(
                                    $user['created_at'] ?? 'N/A'
                                ) ?>
                            </td>

                            <td>
                                <?php if (
                                    (int) $user['user_id'] !== $currentUserId
                                    && $user['role'] === 'user'
                                ): ?>

                                    <form
                                        class="delete-form"
                                        method="post"
                                        action="manage_users.php<?= $search !== '' ? '?q=' . rawurlencode($search) : '' ?>"
                                        onsubmit="return confirm('Are you sure you want to permanently delete the account for <?= escapeHtml($user['username']) ?>? This action cannot be undone.');"
                                    >
                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= escapeHtml($csrfToken) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="user_id"
                                            value="<?= (int) $user['user_id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-danger"
                                        >
                                            Delete
                                        </button>
                                    </form>

                                <?php else: ?>
                                    <span class="protected">
                                        Protected
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <strong>No accounts found</strong>
                                <?php if ($search !== ''): ?>
                                    Try a different name or username.
                                <?php else: ?>
                                    No user accounts have been registered yet.
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>
        </div>

        <div class="panel-footer">
            Showing <?= count($users) ?> account(s)
            <?php if ($search !== ''): ?>
                matching "<?= escapeHtml($search) ?>"
            <?php endif; ?>.
        </div>

    </section>

</main>

</body>
</html>
