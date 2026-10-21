<?php
require_once 'config/db.php';
$students = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$scores = (int)$pdo->query("SELECT COUNT(*) FROM scores")->fetchColumn();
$complete = (int)$pdo->query("
    SELECT COUNT(*) FROM students s
    WHERE (SELECT COUNT(*) FROM scores x WHERE x.student_id=s.student_id)=8
")->fetchColumn();
require 'header.php';
?>




<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config/db.php';

$students = (int)$pdo->query(
    "SELECT COUNT(*) FROM students"
)->fetchColumn();

$scores = (int)$pdo->query(
    "SELECT COUNT(*) FROM scores"
)->fetchColumn();

$complete = (int)$pdo->query("
    SELECT COUNT(*)
    FROM students s
    WHERE (
        SELECT COUNT(*)
        FROM scores x
        WHERE x.student_id = s.student_id
    ) = 8
")->fetchColumn();

require_once __DIR__ . '/header.php';
?>

<div class="container">

    <h1>IM 101a – Fundamentals of Database Systems</h1>

    <div class="notice">
        Final Grade = 1/3 Midterm + 2/3 Final Term.
    </div>

    <div class="grid">
        <div class="card">
            <div class="muted">Students</div>
            <div class="stat"><?= $students ?></div>
        </div>

        <div class="card">
            <div class="muted">Score Records</div>
            <div class="stat"><?= $scores ?></div>
        </div>

        <div class="card">
            <div class="muted">Complete Gradebooks</div>
            <div class="stat"><?= $complete ?></div>
        </div>
    </div>

    <div class="card">
        <h2>Grading System</h2>

        <table>
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Weight</th>
                </tr>
            </thead>

            <tbody>
            <?php
            $categories = $pdo->query(
                "SELECT category_name, weight
                 FROM assessment_categories
                 ORDER BY category_id"
            );

            foreach ($categories as $r):
            ?>
                <tr>
                    <td>
                        <?= htmlspecialchars(
                            $r['category_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </td>

                    <td>
                        <?= number_format(
                            (float)$r['weight'] * 100,
                            0
                        ) ?>%
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($_SESSION['role'] === 'admin'): ?>
        <div class="card">
            <h2>Administrator Tools</h2>
            <p>Manage accounts and gradebook records.</p>
            <a class="btn" href="add_user.php">
                Create User or Admin
            </a>
        </div>
    <?php else: ?>
        <div class="card">
            <h2>View Mode</h2>
            <p>
                You are logged in as a regular user.
                You can view the dashboard, but editing and
                deleting records require administrator access.
            </p>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
