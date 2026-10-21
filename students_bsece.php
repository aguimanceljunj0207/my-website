<?php

require_once 'config/db.php';

$message = '';

$institute = 'IECS';
$program   = 'BSECE';


/*
|--------------------------------------------------------------------------
| ADD / EDIT / DELETE
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';


    /* ADD */

    if ($action === 'add') {

        $student_no  = trim($_POST['student_no'] ?? '');
        $first_name  = trim($_POST['first_name'] ?? '');
        $middle_name = trim($_POST['middle_name'] ?? '');
        $last_name   = trim($_POST['last_name'] ?? '');

        if ($student_no === '') {

            $message = 'Please enter the Student Number.';

        } elseif ($first_name === '') {

            $message = 'Please enter the First Name.';

        } elseif ($last_name === '') {

            $message = 'Please enter the Last Name.';

        } else {

            try {

                $stmt = $pdo->prepare("
                    INSERT INTO students
                    (
                        student_no,
                        institute,
                        program,
                        last_name,
                        first_name,
                        middle_name
                    )
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $student_no,
                    $institute,
                    $program,
                    $last_name,
                    $first_name,
                    $middle_name
                ]);

                $message = 'BSECE student added successfully.';

            } catch (PDOException $e) {

                $message = 'Error adding student: ' . $e->getMessage();
            }
        }
    }


    /* EDIT */

    elseif ($action === 'edit') {

        $student_id  = (int)($_POST['student_id'] ?? 0);

        $student_no  = trim($_POST['student_no'] ?? '');
        $first_name  = trim($_POST['first_name'] ?? '');
        $middle_name = trim($_POST['middle_name'] ?? '');
        $last_name   = trim($_POST['last_name'] ?? '');

        if ($student_id <= 0) {

            $message = 'Invalid student.';

        } elseif ($student_no === '') {

            $message = 'Please enter the Student Number.';

        } elseif ($first_name === '') {

            $message = 'Please enter the First Name.';

        } elseif ($last_name === '') {

            $message = 'Please enter the Last Name.';

        } else {

            try {

                $stmt = $pdo->prepare("
                    UPDATE students
                    SET
                        student_no = ?,
                        institute = ?,
                        program = ?,
                        last_name = ?,
                        first_name = ?,
                        middle_name = ?
                    WHERE student_id = ?
                    AND program = 'BSECE'
                ");

                $stmt->execute([
                    $student_no,
                    $institute,
                    $program,
                    $last_name,
                    $first_name,
                    $middle_name,
                    $student_id
                ]);

                $message = 'BSECE student updated successfully.';

            } catch (PDOException $e) {

                $message = 'Error updating student: ' . $e->getMessage();
            }
        }
    }


    /* DELETE */

    elseif ($action === 'delete') {

        $student_id = (int)($_POST['student_id'] ?? 0);

        if ($student_id <= 0) {

            $message = 'Invalid student.';

        } else {

            try {

                $stmt = $pdo->prepare("
                    DELETE FROM students
                    WHERE student_id = ?
                    AND program = 'BSECE'
                ");

                $stmt->execute([
                    $student_id
                ]);

                $message = 'BSECE student deleted successfully.';

            } catch (PDOException $e) {

                $message =
                    'Unable to delete this student. ' .
                    'The student may have existing grade records.';
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT
        student_id,
        student_no,
        institute,
        program,
        last_name,
        first_name,
        middle_name
    FROM students
    WHERE institute = ?
    AND program = ?
";

$params = [
    $institute,
    $program
];

if ($search !== '') {

    $sql .= "
        AND (
            student_no LIKE ?
            OR first_name LIKE ?
            OR middle_name LIKE ?
            OR last_name LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

$sql .= "
    ORDER BY
        last_name ASC,
        first_name ASC,
        middle_name ASC
";

$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$rows = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| EDIT DATA
|--------------------------------------------------------------------------
*/

$student = null;

if (isset($_GET['edit'])) {

    $edit_id = (int)$_GET['edit'];

    $stmt = $pdo->prepare("
        SELECT
            student_id,
            student_no,
            institute,
            program,
            last_name,
            first_name,
            middle_name
        FROM students
        WHERE student_id = ?
        AND institute = ?
        AND program = ?
    ");

    $stmt->execute([
        $edit_id,
        $institute,
        $program
    ]);

    $student = $stmt->fetch();
}


require 'header.php';

?>

<div class="container">


    <div class="no-print" style="margin-bottom:20px;">

        <a
            href="students.php"
            class="btn"
        >
            ← Back to Program Selection
        </a>

    </div>


    <h1>BSECE Students</h1>

    <p>
        <strong>Institute:</strong> IECS
        &nbsp; | &nbsp;
        <strong>Program:</strong> BSECE
    </p>


    <?php if ($message): ?>

        <div class="notice no-print">

            <?= htmlspecialchars($message) ?>

        </div>

    <?php endif; ?>


    <!-- ADD -->

    <div class="card no-print">

        <h2>Add BSECE Student</h2>

        <form method="post" class="form-grid">

            <input
                type="hidden"
                name="action"
                value="add"
            >


            <div>

                <label>
                    Student No.

                    <input
                        type="text"
                        name="student_no"
                        required
                    >
                </label>

            </div>


            <div>

                <label>
                    Institute

                    <input
                        type="text"
                        value="IECS"
                        readonly
                    >
                </label>

            </div>


            <div>

                <label>
                    Program

                    <input
                        type="text"
                        value="BSECE"
                        readonly
                    >
                </label>

            </div>


            <div>

                <label>
                    First Name

                    <input
                        type="text"
                        name="first_name"
                        required
                    >
                </label>

            </div>


            <div>

                <label>
                    Middle Name

                    <input
                        type="text"
                        name="middle_name"
                    >
                </label>

            </div>


            <div>

                <label>
                    Last Name

                    <input
                        type="text"
                        name="last_name"
                        required
                    >
                </label>

            </div>


            <div>

                <button type="submit">
                    Add BSECE Student
                </button>

            </div>

        </form>

    </div>


    <!-- SEARCH -->

    <div class="card no-print">

        <h2>Search BSECE Students</h2>

        <form method="get">

            <input
                type="text"
                name="search"
                value="<?= htmlspecialchars($search) ?>"
                placeholder="Search student number or name"
            >

            <button type="submit">
                Search
            </button>

            <a
                href="students_bsece.php"
                class="btn"
            >
                Clear
            </a>

        </form>

    </div>


    <!-- PRINT -->

    <div class="no-print" style="margin-bottom:20px;">

        <button
            type="button"
            onclick="window.print()"
        >
            🖨 Print BSECE List
        </button>

    </div>


    <!-- LIST -->

    <div class="card">

        <h2>BSECE Student List</h2>

        <?php if (count($rows) === 0): ?>

            <p>No BSECE students found.</p>

        <?php else: ?>

            <table>

                <thead>

                    <tr>

                        <th>No.</th>
                        <th>Student No.</th>
                        <th>Institute</th>
                        <th>Program</th>
                        <th>Student Name</th>
                        <th class="no-print">Action</th>

                    </tr>

                </thead>

                <tbody>

                    <?php $no = 1; ?>

                    <?php foreach ($rows as $r): ?>

                        <tr>

                            <td>
                                <?= $no++ ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($r['student_no']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($r['institute']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($r['program']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $r['last_name']
                                    . ', '
                                    . $r['first_name']
                                    .
                                    (
                                        !empty($r['middle_name'])
                                        ? ' ' . $r['middle_name']
                                        : ''
                                    )
                                ) ?>
                            </td>

                            <td class="no-print">

                                <a
                                    href="students_bsece.php?edit=<?= (int)$r['student_id'] ?>"
                                    class="btn"
                                >
                                    Edit
                                </a>


                                <form
                                    method="post"
                                    style="display:inline;"
                                    onsubmit="return confirm('Delete this BSECE student?');"
                                >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete"
                                    >

                                    <input
                                        type="hidden"
                                        name="student_id"
                                        value="<?= (int)$r['student_id'] ?>"
                                    >

                                    <button type="submit">
                                        Delete
                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>

</div>


<!-- EDIT -->

<?php if ($student): ?>

<div class="container no-print">

    <div class="card">

        <h2>Edit BSECE Student</h2>

        <form method="post" class="form-grid">

            <input
                type="hidden"
                name="action"
                value="edit"
            >

            <input
                type="hidden"
                name="student_id"
                value="<?= (int)$student['student_id'] ?>"
            >


            <div>

                <label>
                    Student No.

                    <input
                        type="text"
                        name="student_no"
                        required
                        value="<?= htmlspecialchars($student['student_no']) ?>"
                    >
                </label>

            </div>


            <div>

                <label>
                    Institute

                    <input
                        type="text"
                        value="IECS"
                        readonly
                    >
                </label>

            </div>


            <div>

                <label>
                    Program

                    <input
                        type="text"
                        value="BSECE"
                        readonly
                    >
                </label>

            </div>


            <div>

                <label>
                    First Name

                    <input
                        type="text"
                        name="first_name"
                        required
                        value="<?= htmlspecialchars($student['first_name']) ?>"
                    >
                </label>

            </div>


            <div>

                <label>
                    Middle Name

                    <input
                        type="text"
                        name="middle_name"
                        value="<?= htmlspecialchars($student['middle_name']) ?>"
                    >
                </label>

            </div>


            <div>

                <label>
                    Last Name

                    <input
                        type="text"
                        name="last_name"
                        required
                        value="<?= htmlspecialchars($student['last_name']) ?>"
                    >
                </label>

            </div>


            <div>

                <button type="submit">
                    Update BSECE Student
                </button>

                <a
                    href="students_bsece.php"
                    class="btn"
                >
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>

<?php endif; ?>


<?php require 'footer.php'; ?>