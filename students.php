
<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config/db.php';

$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$message = '';
$messageType = 'success';

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| ADD / EDIT / DELETE STUDENT
| Only administrators can modify student records.
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!$isAdmin) {
        http_response_code(403);
        exit('Access denied. Only administrators can modify student records.');
    }

    $postedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($postedToken) ||
        !hash_equals($csrfToken, $postedToken)
    ) {
        http_response_code(403);
        exit('Invalid security token. Refresh the page and try again.');
    }

    $action = $_POST['action'] ?? '';

    try {

        /*
        |--------------------------------------------------------------------------
        | ADD STUDENT
        |--------------------------------------------------------------------------
        */
        if ($action === 'add') {

            $student_no  = trim($_POST['student_no'] ?? '');
            $first_name  = trim($_POST['first_name'] ?? '');
            $middle_name = trim($_POST['middle_name'] ?? '');
            $last_name   = trim($_POST['last_name'] ?? '');
            $program   = trim($_POST['program'] ?? '');
            $cp_number = trim($_POST['cp_number'] ?? '');
            $email     = trim($_POST['email'] ?? '');

            if (
                $student_no === '' ||
                $first_name === '' ||
                $last_name === ''
            ) {
                $message = 'Please complete Student No., First Name, and Last Name.';
                $messageType = 'error';

            } else {

                $stmt = $pdo->prepare(
                    "INSERT INTO students
                    (student_no, last_name, first_name, middle_name, program, cp_number, email)
                    VALUES (?, ?, ?, ?, ?, ?, ?)"
                );

                $stmt->execute([
                    $student_no,
                    $last_name,
                    $first_name,
                    $middle_name,
                    $program,
                    $cp_number,
                    $email
                ]);

                $message = 'Student added successfully.';
            }

        /*
        |--------------------------------------------------------------------------
        | EDIT STUDENT
        |--------------------------------------------------------------------------
        */
        } elseif ($action === 'edit') {

            $student_id  = (int) ($_POST['student_id'] ?? 0);
            $student_no  = trim($_POST['student_no'] ?? '');
            $first_name  = trim($_POST['first_name'] ?? '');
            $middle_name = trim($_POST['middle_name'] ?? '');
            $last_name   = trim($_POST['last_name'] ?? '');
            $program   = trim($_POST['program'] ?? '');
            $cp_number = trim($_POST['cp_number'] ?? '');
            $email     = trim($_POST['email'] ?? '');

            if (
                $student_id < 1 ||
                $student_no === '' ||
                $first_name === '' ||
                $last_name === ''
            ) {
                $message = 'Please provide valid student details.';
                $messageType = 'error';

            } else {

                $stmt = $pdo->prepare(
                    "UPDATE students
                     SET student_no = ?,
                         last_name = ?,
                         first_name = ?,
                         middle_name = ?,
                         program = ?,
                         cp_number = ?,
                         email = ?
                     WHERE student_id = ?"
                );

                $stmt->execute([
                    $student_no,
                    $last_name,
                    $first_name,
                    $middle_name,
                    $program,
                    $cp_number,
                    $email,
                    $student_id
                ]);

                $message = $stmt->rowCount() > 0
                    ? 'Student information updated successfully.'
                    : 'No student details were changed.';
            }

        /*
        |--------------------------------------------------------------------------
        | DELETE STUDENT
        |--------------------------------------------------------------------------
        */
        } elseif ($action === 'delete') {

            $student_id = (int) ($_POST['student_id'] ?? 0);

            if ($student_id < 1) {

                $message = 'Invalid student selected.';
                $messageType = 'error';

            } else {

                $stmt = $pdo->prepare(
                    "DELETE FROM students WHERE student_id = ?"
                );

                $stmt->execute([$student_id]);

                if ($stmt->rowCount() === 1) {
                    $message = 'Student deleted successfully.';
                } else {
                    $message = 'Student record was not found.';
                    $messageType = 'error';
                }
            }

        } else {

            $message = 'Unknown request.';
            $messageType = 'error';
        }

    } catch (PDOException $ex) {

        error_log('Student management error: ' . $ex->getMessage());

        $message = 'The operation could not be completed. Check for duplicate student numbers or linked grade records.';
        $messageType = 'error';
    }
}

/*
|--------------------------------------------------------------------------
| GET STUDENTS
|--------------------------------------------------------------------------
*/
$rows = $pdo->query(
    "SELECT student_id, student_no, last_name, first_name, middle_name,
            program, cp_number, email
     FROM students
     ORDER BY last_name ASC, first_name ASC, middle_name ASC"
)->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| GET STUDENT FOR EDIT FORM
|--------------------------------------------------------------------------
*/
$student = null;

if ($isAdmin && isset($_GET['edit'])) {

    $editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);

    if ($editId && $editId > 0) {

        $stmt = $pdo->prepare(
            "SELECT * FROM students WHERE student_id = ?"
        );

        $stmt->execute([$editId]);

        $student = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

require __DIR__ . '/header.php';
?>

<style>
.students-page {
    max-width: 1200px;
    margin: 28px auto;
    padding: 0 20px;
    color: #0f172a;
}

.students-page .page-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 22px;
}

.students-page h1 {
    margin: 0 0 7px;
    color: #0f766e;
    font-size: 28px;
}

.students-page .muted {
    margin: 0;
    color: #64748b;
    line-height: 1.5;
}

.students-page .card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 22px;
    margin-bottom: 22px;
    box-shadow: 0 5px 18px rgba(15, 23, 42, .05);
}

.students-page .form-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 15px;
    align-items: end;
}

.students-page label {
    display: block;
    font-size: 13px;
    font-weight: 700;
    color: #334155;
}

.students-page input {
    box-sizing: border-box;
    width: 100%;
    margin-top: 7px;
    padding: 11px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 14px;
}

.students-page input:focus {
    outline: 2px solid #99f6e4;
    border-color: #0f766e;
}

.students-page .btn {
    display: inline-block;
    border: 0;
    border-radius: 8px;
    padding: 10px 13px;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    white-space: nowrap;
}

.students-page .btn-primary {
    color: #fff;
    background: #0f766e;
}

.students-page .btn-primary:hover {
    background: #115e59;
}

.students-page .btn-edit {
    color: #1d4ed8;
    background: #dbeafe;
}

.students-page .btn-delete {
    color: #991b1b;
    background: #fee2e2;
}

.students-page .btn-light {
    color: #334155;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
}

.students-page .notice {
    padding: 13px 15px;
    border-radius: 9px;
    margin-bottom: 18px;
}

.students-page .notice.success {
    color: #166534;
    background: #dcfce7;
    border: 1px solid #bbf7d0;
}

.students-page .notice.error {
    color: #991b1b;
    background: #fee2e2;
    border: 1px solid #fecaca;
}

.students-page .list-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 16px;
}

.students-page .list-heading h2 {
    margin: 0 0 5px;
    font-size: 20px;
}

.students-page .table-wrap {
    overflow-x: auto;
}

.students-page table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
}

.students-page th,
.students-page td {
    padding: 12px 14px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    font-size: 14px;
}

.students-page th {
    background: #f8fafc;
    color: #475569;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .04em;
}

.students-page tbody tr:hover {
    background: #f8fafc;
}

.students-page .actions {
    display: flex;
    gap: 7px;
    align-items: center;
}

.students-page .actions form {
    margin: 0;
}

.students-page .count {
    color: #64748b;
    font-size: 13px;
}

.students-page .print-footer {
    display: none;
}

/* TABLET */
@media (max-width: 850px) {
    .students-page .form-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

/* MOBILE */
@media (max-width: 520px) {
    .students-page {
        padding: 0 12px;
        margin: 18px auto;
    }

    .students-page .card {
        padding: 15px;
    }

    .students-page .form-grid {
        grid-template-columns: 1fr;
    }

    .students-page h1 {
        font-size: 23px;
    }
}

/*
|--------------------------------------------------------------------------
| PRINT SETTINGS
|--------------------------------------------------------------------------
*/
@page {
    size: A4 landscape;
    margin: 15mm 14mm 22mm;
}

@media print {

    body * {
        visibility: hidden !important;
    }

    #print-student-list,
    #print-student-list * {
        visibility: visible !important;
    }

    #print-student-list {
        /* Place the roster at the top of the printed page. */
        position: absolute;
        left: 0;
        top: 0;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        border: 0 !important;
        box-shadow: none !important;
        background: #fff !important;
    }

    #print-student-list .no-print,
    #print-student-list .actions,
    #print-student-list form,
    #print-student-list button {
        display: none !important;
    }

    /* Keep Prepared by after the table; do not use position: fixed,
       because fixed print elements can repeat on every printed page. */
    #print-student-list .print-footer {
        display: block !important;
        position: static !important;
        width: 100%;
        margin-top: 30px;
        text-align: center;
        font-size: 11pt;
        color: #000 !important;
        background: #fff !important;
        padding-top: 8px;
        page-break-inside: avoid;
        break-inside: avoid;
    }

    #print-student-list .print-footer .prepared-line {
        display: inline-block;
        width: 220px;
        max-width: 70%;
        border-bottom: 1px solid #000;
        height: 20px;
        margin-bottom: 5px;
    }

    #print-student-list .table-wrap {
        width: 100% !important;
        overflow: visible !important;
    }

    #print-student-list h2,
    #print-student-list h3 {
        text-align: center;
        margin: 0 0 7px;
        color: #000 !important;
    }

    #print-student-list .print-subtitle {
        text-align: center;
        font-size: 10pt;
        margin: 0 0 16px;
    }

    #print-student-list table {
        width: 100% !important;
        max-width: 100% !important;
        table-layout: auto;
        border-collapse: collapse;
        font-size: 10pt;
    }

    #print-student-list th,
    #print-student-list td {
        border: 1px solid #000 !important;
        padding: 6px 7px;
        color: #000 !important;
        background: #fff !important;
        white-space: normal !important;
        overflow-wrap: anywhere;
        word-break: normal;
    }

    #print-student-list th {
        font-weight: bold;
        text-transform: none;
        letter-spacing: normal;
    }

    #print-student-list tr {
        break-inside: avoid;
        page-break-inside: avoid;
    }
}
</style>

<div class="students-page">

    <!-- PAGE HEADER -->
    <div class="page-heading no-print">
        <div>
            <h1>BSIT Students</h1>
            <p class="muted">
                Manage and print the IM 101a class roster.
            </p>
        </div>

        <button
            type="button"
            class="btn btn-primary"
            onclick="window.print()">
            &#128424; Print Student List
        </button>
    </div>

    <!-- SUCCESS OR ERROR MESSAGE -->
    <?php if ($message !== ''): ?>
        <div
            class="notice <?= e($messageType) ?> no-print"
            role="status">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <!-- ADD / EDIT FORM: ADMIN ONLY -->
    <?php if ($isAdmin): ?>

        <section class="card no-print">

            <h2>
                <?= $student ? 'Edit Student' : 'Add Student' ?>
            </h2>

            <form
                method="post"
                class="form-grid"
                action="students.php<?= $student ? '?edit=' . (int) $student['student_id'] : '' ?>">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($csrfToken) ?>">

                <input
                    type="hidden"
                    name="action"
                    value="<?= $student ? 'edit' : 'add' ?>">

                <?php if ($student): ?>
                    <input
                        type="hidden"
                        name="student_id"
                        value="<?= (int) $student['student_id'] ?>">
                <?php endif; ?>

                <div>
                    <label for="student_no">Student No.</label>
                    <input
                        id="student_no"
                        name="student_no"
                        required
                        maxlength="50"
                        value="<?= e($student['student_no'] ?? '') ?>">
                </div>

                <div>
                    <label for="first_name">First Name</label>
                    <input
                        id="first_name"
                        name="first_name"
                        required
                        maxlength="100"
                        value="<?= e($student['first_name'] ?? '') ?>">
                </div>

                <div>
                    <label for="middle_name">Middle Name</label>
                    <input
                        id="middle_name"
                        name="middle_name"
                        maxlength="100"
                        value="<?= e($student['middle_name'] ?? '') ?>">
                </div>

                <div>
                    <label for="last_name">Last Name</label>
                    <input
                        id="last_name"
                        name="last_name"
                        required
                        maxlength="100"
                        value="<?= e($student['last_name'] ?? '') ?>">
                </div>

                <div>
                    <label for="program">Program</label>
                    <input id="program" name="program" maxlength="100"
                           placeholder="e.g. BSIT"
                           value="<?= e($student['program'] ?? '') ?>">
                </div>

                <div>
                    <label for="cp_number">CP Number</label>
                    <input id="cp_number" name="cp_number" type="tel" maxlength="30"
                           placeholder="e.g. 09XXXXXXXXX"
                           value="<?= e($student['cp_number'] ?? '') ?>">
                </div>

                <div>
                    <label for="email">Email Address</label>
                    <input id="email" name="email" type="email" maxlength="255"
                           placeholder="student@example.com"
                           value="<?= e($student['email'] ?? '') ?>">
                </div>

                <div>
                    <button type="submit" class="btn btn-primary">
                        <?= $student ? 'Update Student' : 'Add Student' ?>
                    </button>

                    <?php if ($student): ?>
                        <a href="students.php" class="btn btn-light">
                            Cancel
                        </a>
                    <?php endif; ?>
                </div>

            </form>
        </section>

    <?php endif; ?>

    <!-- STUDENT LIST -->
    <section class="card" id="print-student-list">

        <h2>IM 101a – Fundamentals of Database Systems</h2>
        <h3>BSIT 2B</h3>

        <p class="print-subtitle">
            Official Class Student List
        </p>

        <div class="list-heading no-print">
            <div>
              
                <p class="muted">
                    Sorted alphabetically by last name.
                </p>
            </div>

            <span class="count">
                Total students: <?= count($rows) ?>
            </span>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width: 55px;">No.</th>
                        <th style="width: 150px;">Student No.</th>
                        <th>Name</th>
                        <th>Program</th>
                        <th>CP Number</th>
                        <th>Email Address</th>

                        <?php if ($isAdmin): ?>
                            <th class="no-print">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>

                <tbody>

                <?php if ($rows): ?>

                    <?php $no = 1; ?>

                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= $no++ ?></td>

                            <td>
                                <?= e($r['student_no']) ?>
                            </td>

                            <td>
                                <?= e(
                                    $r['last_name'] . ', ' .
                                    $r['first_name'] .
                                    (
                                        !empty($r['middle_name'])
                                        ? ' ' . $r['middle_name']
                                        : ''
                                    )
                                ) ?>
                            </td>
                            <td><?= e($r['program'] ?? '') ?></td>
                            <td><?= e($r['cp_number'] ?? '') ?></td>
                            <td><?= e($r['email'] ?? '') ?></td>

                            <?php if ($isAdmin): ?>
                                <td class="no-print">
                                    <div class="actions">

                                        <a
                                            class="btn btn-edit"
                                            href="students.php?edit=<?= (int) $r['student_id'] ?>">
                                            Edit
                                        </a>

                                        <form
                                            method="post"
                                            action="students.php"
                                            onsubmit="return confirm('Delete this student? This may fail if grade records are linked to this student.');">

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e($csrfToken) ?>">

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="delete">

                                            <input
                                                type="hidden"
                                                name="student_id"
                                                value="<?= (int) $r['student_id'] ?>">

                                            <button
                                                class="btn btn-delete"
                                                type="submit">
                                                Delete
                                            </button>

                                        </form>
                                    </div>
                                </td>
                            <?php endif; ?>

                        </tr>
                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td
                            colspan="<?= $isAdmin ? 7 : 6 ?>"
                            style="text-align:center;padding:25px;">
                            No student records found.
                        </td>
             
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>
        </div>

        <!-- FOOTER SHOWN WHEN PRINTING -->
        <div class="print-footer">
            <div>Prepared by:</div>
            <div class="prepared-line"></div>
             <div>IM 101a Instructor</div>
        </div>

    </section>

</div>

<?php require __DIR__ . '/footer.php'; ?>
