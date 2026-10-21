
<?php

require_once 'config/db.php';

$message = '';

/*
|--------------------------------------------------------------------------
| SELECT SEMESTER
|--------------------------------------------------------------------------
*/

$semester_id = (int)($_GET['semester_id'] ?? $_POST['semester_id'] ?? 1);

/*
|--------------------------------------------------------------------------
| DELETE GRADES FOR SELECTED SEMESTER
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {

        $student_id = (int)($_POST['student_id'] ?? 0);
        $semester_id = (int)($_POST['semester_id'] ?? 1);

        if ($student_id > 0 && $semester_id > 0) {

            if (($_SESSION['role'] ?? '') === 'admin') {

                $stmt = $pdo->prepare("
                    DELETE FROM scores
                    WHERE student_id = ?
                      AND semester_id = ?
                ");

                $stmt->execute([
                    $student_id,
                    $semester_id
                ]);

                $message = 'Grades for the selected semester were deleted.';

            } else {
                $message = 'You do not have permission to delete grades.';
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| GET SEMESTER INFORMATION
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT semester_id, academic_year, semester_name
    FROM semesters
    WHERE semester_id = ?
");

$stmt->execute([$semester_id]);
$semester = $stmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| FALL BACK TO FIRST AVAILABLE SEMESTER
|--------------------------------------------------------------------------
*/

if (!$semester) {

    $stmt = $pdo->query("
        SELECT semester_id, academic_year, semester_name
        FROM semesters
        ORDER BY semester_id ASC
        LIMIT 1
    ");

    $semester = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($semester) {
        $semester_id = (int)$semester['semester_id'];
    }
}

/*
|--------------------------------------------------------------------------
| GET ALL SEMESTERS
|--------------------------------------------------------------------------
*/

$semesters = $pdo->query("
    SELECT semester_id, academic_year, semester_name
    FROM semesters
    ORDER BY semester_id ASC
")->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| GET STUDENTS AND GRADES
|--------------------------------------------------------------------------
*/

$sql = "
SELECT
    s.student_id,
    s.student_no,

    CONCAT(
        s.last_name,
        ', ',
        s.first_name,
        CASE
            WHEN s.middle_name IS NOT NULL
                 AND s.middle_name <> ''
            THEN CONCAT(' ', s.middle_name)
            ELSE ''
        END
    ) AS student_name,

    ROUND(
        100 * COALESCE(
            (
                SELECT SUM(
                    CASE
                        WHEN x.max_score > 0
                        THEN (x.earned / x.max_score) * a.weight
                        ELSE 0
                    END
                )
                FROM scores x
                JOIN assessment_categories a
                    ON a.category_id = x.category_id
                WHERE x.student_id = s.student_id
                  AND x.semester_id = :semester_midterm
                  AND x.term_id = 1
            ),
            0
        ),
        2
    ) AS midterm,

    ROUND(
        100 * COALESCE(
            (
                SELECT SUM(
                    CASE
                        WHEN x.max_score > 0
                        THEN (x.earned / x.max_score) * a.weight
                        ELSE 0
                    END
                )
                FROM scores x
                JOIN assessment_categories a
                    ON a.category_id = x.category_id
                WHERE x.student_id = s.student_id
                  AND x.semester_id = :semester_final
                  AND x.term_id = 2
            ),
            0
        ),
        2
    ) AS final_term

FROM students s

ORDER BY
    s.last_name ASC,
    s.first_name ASC,
    s.middle_name ASC
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':semester_midterm' => $semester_id,
    ':semester_final'   => $semester_id
]);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| STUDENT AND PASSING STATISTICS
|--------------------------------------------------------------------------
*/

$totalStudents = count($rows);
$totalPassed = 0;
$totalFailed = 0;

foreach ($rows as $r) {

    $midterm = (float)$r['midterm'];
    $finalTerm = (float)$r['final_term'];

    $finalGrade = ($midterm / 3) + ($finalTerm * 2 / 3);

    if ($finalGrade >= 75) {
        $totalPassed++;
    } else {
        $totalFailed++;
    }
}

require 'header.php';

?>

<div class="container">

    <div id="print-grade-summary">

        <!-- REPORT TITLE -->

        <div class="report-heading">
            <h3>IM 101a – Fundamentals of Database Systems</h3>
            <h3>BSIT 2B</h3>
            <h4>FINAL GRADE SUMMARY</h4>
        </div>

        <!-- MESSAGE -->

        <?php if ($message): ?>
            <div class="notice no-print">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- SEMESTER SELECTOR -->

        <div class="card no-print">
            <form method="get">

                <label>
                    <strong>Select Semester:</strong>
                </label>

                <select
                    name="semester_id"
                    onchange="this.form.submit()"
                >
                    <?php foreach ($semesters as $sem): ?>
                        <option
                            value="<?= (int)$sem['semester_id'] ?>"
                            <?= $semester_id == $sem['semester_id']
                                ? 'selected'
                                : '' ?>
                        >
                            <?= htmlspecialchars($sem['academic_year']) ?>
                            -
                            <?= htmlspecialchars($sem['semester_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

            </form>
        </div>

        <!-- CURRENT SEMESTER -->

        <div class="notice semester-info">
            <strong>
                <?= htmlspecialchars($semester['semester_name'] ?? 'Semester') ?>
            </strong>

            <?php if (!empty($semester['academic_year'])): ?>
                <br>
                Academic Year:
                <?= htmlspecialchars($semester['academic_year']) ?>
            <?php endif; ?>
        </div>

        <!-- TOTAL STUDENT SUMMARY -->

        <div class="student-summary">

            <div class="summary-box">
                <span class="summary-label">Total Students</span>
                <strong><?= number_format($totalStudents) ?></strong>
            </div>

            <div class="summary-box">
                <span class="summary-label">Passed</span>
                <strong><?= number_format($totalPassed) ?></strong>
            </div>

            <div class="summary-box">
                <span class="summary-label">Failed</span>
                <strong><?= number_format($totalFailed) ?></strong>
            </div>

        </div>

        <!-- PRINT BUTTON -->

        <div class="print-controls no-print">
            <button
                type="button"
                onclick="window.print()"
                class="print-button"
            >
                🖨 Print Summary
            </button>
        </div>

        <!-- GRADING FORMULA -->

        <div class="notice no-print">
            <strong>Grading Formula</strong>
            <br><br>

            Final Grade =
            (Midterm × 1/3) +
            (Final Term × 2/3)

            <br><br>

            <strong>
                75% and above = PASS;
                below 75% = FAILED.
            </strong>
        </div>

        <!-- GRADE TABLE -->

        <div class="card" id="grade-summary-table">

            <table>

                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Student No.</th>
                        <th>Student Name</th>
                        <th>Midterm</th>
                        <th>Final Term</th>
                        <th>Final Grade</th>
                        <th>Remark</th>
                        <th class="no-print">Action</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (empty($rows)): ?>

                        <tr>
                            <td colspan="8" class="empty-message">
                                No students found.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php $no = 1; ?>

                        <?php foreach ($rows as $r): ?>

                            <?php
                            $midterm = (float)$r['midterm'];
                            $finalTerm = (float)$r['final_term'];

                            $finalGrade =
                                ($midterm / 3) +
                                ($finalTerm * 2 / 3);

                            $remark = $finalGrade >= 75
                                ? 'PASS'
                                : 'FAILED';
                            ?>

                            <tr>

                                <td><?= $no++ ?></td>

                                <td>
                                    <?= htmlspecialchars(
                                        (string)$r['student_no']
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($r['student_name']) ?>
                                </td>

                                <td><?= number_format($midterm, 2) ?>%</td>

                                <td><?= number_format($finalTerm, 2) ?>%</td>

                                <td>
                                    <strong>
                                        <?= number_format($finalGrade, 2) ?>%
                                    </strong>
                                </td>

                                <td>
                                    <span class="<?= $remark === 'PASS'
                                        ? 'pass'
                                        : 'failed' ?>">
                                        <?= $remark ?>
                                    </span>
                                </td>

                                <td class="no-print">

                                    <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>

                                        <form
                                            method="post"
                                            onsubmit="return confirm(
                                                'Delete ALL grades for this student in the selected semester? Student information will NOT be deleted.'
                                            );"
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

                                            <input
                                                type="hidden"
                                                name="semester_id"
                                                value="<?= (int)$semester_id ?>"
                                            >

                                            <button type="submit" class="btn btn-danger">
                                                Delete Grades
                                            </button>

                                        </form>

                                    <?php else: ?>

                                        <span>View Only</span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

        <!-- FOOTER: HIDDEN ON SCREEN, VISIBLE WHEN PRINTING -->

        <div class="report-footer">

            <div class="print-footer">
                <div>Prepared by: </div>
                <div class="prepared-line"></div>
                <div>IM 101a Instructor</div>
            </div>

        </div>

    </div>

</div>

<?php require 'footer.php'; ?>

<style>

/* SUMMARY CARDS */

.student-summary {
    display: flex;
    gap: 15px;
    margin: 20px 0;
    flex-wrap: wrap;
}

.summary-box {
    flex: 1;
    min-width: 130px;
    padding: 18px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: #f8f9fa;
    text-align: center;
}

.summary-label {
    display: block;
    font-size: 14px;
    margin-bottom: 8px;
}

.summary-box strong {
    font-size: 25px;
}

.report-heading {
    text-align: center;
    margin-bottom: 20px;
}

.report-heading h3,
.report-heading h4 {
    margin: 5px 0;
}

.report-footer {
    margin-top: 25px;
}

/* FOOTER IS HIDDEN ON THE NORMAL WEBPAGE */

.print-footer {
    display: none;
}

.prepared-line {
    display: block;
    width: 220px;
    height: 20px;
    border-bottom: 1px solid #000;
    margin: 0 auto 5px;
}

.empty-message {
    text-align: center;
    padding: 20px;
}

#grade-summary-table table {
    width: 100%;
    border-collapse: collapse;
}

#grade-summary-table th,
#grade-summary-table td {
    padding: 8px;
    border: 1px solid #ddd;
    text-align: left;
}

#grade-summary-table th {
    background: #f1f3f5;
}

#grade-summary-table tfoot td {
    background: #f1f3f5;
    padding: 12px;
}

/* PRINT SETTINGS */

@media print {

    @page {
        size: landscape;
        margin: 12mm;
    }

    body * {
        visibility: hidden;
    }

    #print-grade-summary,
    #print-grade-summary * {
        visibility: visible;
    }

    #print-grade-summary {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        margin: 0;
        padding: 0;
    }

    .no-print {
        display: none !important;
    }

    .student-summary {
        display: flex;
        gap: 10px;
        margin: 10px 0;
    }

    .summary-box {
        padding: 8px;
        border: 1px solid #000;
        border-radius: 0;
        background: transparent;
    }

    .summary-box strong {
        font-size: 16px;
    }

    .summary-label {
        font-size: 11px;
        margin-bottom: 4px;
    }

    #grade-summary-table {
        width: 100%;
        margin: 0;
        padding: 0;
        border: none;
        box-shadow: none;
    }

    #grade-summary-table table {
        width: 100%;
        border-collapse: collapse;
    }

    #grade-summary-table th,
    #grade-summary-table td {
        border: 1px solid #000;
        padding: 6px;
        color: #000;
    }

    #grade-summary-table th,
    #grade-summary-table tfoot td {
        background: transparent;
        font-weight: bold;
    }

    .report-heading {
        text-align: center;
        margin-bottom: 12px;
    }

    .report-heading h3 {
        font-size: 14pt;
    }

    .report-heading h4 {
        font-size: 12pt;
    }

    .semester-info {
        border: none;
        padding: 0;
        margin-bottom: 10px;
    }

    /* SHOW AND CENTER FOOTER ONLY WHEN PRINTING */

    .report-footer {
        width: 100%;
        margin-top: 20px;
        text-align: center;
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .print-footer {
        display: block !important;
        width: 100%;
        text-align: center !important;
        margin-top: 20px;
        color: #000;
    }

    .print-footer > div {
        text-align: center !important;
    }

    .prepared-line {
        display: block;
        width: 220px;
        height: 20px;
        border-bottom: 1px solid #000;
        margin: 0 auto 5px !important;
    }

    tr {
        page-break-inside: avoid;
    }

}

</style>
