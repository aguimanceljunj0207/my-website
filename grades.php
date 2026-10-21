<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config/db.php';

/*
|--------------------------------------------------------------------------
| IM 101a GRADEBOOK - ENHANCED GRADES PAGE
|--------------------------------------------------------------------------
| Existing tables preserved:
| students, semesters, terms, assessment_categories, scores
|
| Grading rules:
| Category percentage = Earned / Maximum * 100
| Term grade = weighted average of categories with saved scores
| Overall = (Midterm / 3) + (Final * 2 / 3)
| Attendance / Class Participation maximum = 14 points
|--------------------------------------------------------------------------
*/

$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

$message = '';
$messageType = 'success';

$student_id = (int)($_GET['student_id'] ?? $_POST['student_id'] ?? 0);
$semester_id = (int)($_GET['semester_id'] ?? $_POST['semester_id'] ?? 1);
$term_id = (int)($_GET['term_id'] ?? $_POST['term_id'] ?? 1);

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function isAttendanceCategory(string $categoryName): bool
{
    $name = strtolower(trim($categoryName));

    return strpos($name, 'attendance') !== false
        || strpos($name, 'participation') !== false;
}

function isValidNumber($value): bool
{
    if (!is_scalar($value) || $value === '') {
        return false;
    }

    $number = filter_var($value, FILTER_VALIDATE_FLOAT);

    return $number !== false && is_finite((float)$number);
}

/*
|--------------------------------------------------------------------------
| LOAD EXISTING DATA
|--------------------------------------------------------------------------
*/

try {
    $students = $pdo->query("
        SELECT student_id, student_no, last_name, first_name,
               middle_name, institute, program
        FROM students
        ORDER BY last_name, first_name, middle_name
    ")->fetchAll(PDO::FETCH_ASSOC);

    $semesters = $pdo->query("
        SELECT semester_id, academic_year, semester_name, is_active
        FROM semesters
        ORDER BY academic_year DESC, semester_id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $terms = $pdo->query("
        SELECT term_id, term_name
        FROM terms
        ORDER BY term_id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $cats = $pdo->query("
        SELECT category_id, category_name, weight
        FROM assessment_categories
        ORDER BY category_id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Gradebook data loading error: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to load gradebook data. Please check your database configuration.');
}

/*
|--------------------------------------------------------------------------
| SAVE / UPDATE GRADES
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'save'
) {
    if (!$isAdmin) {
        http_response_code(403);
        exit('Access denied. Only administrators can save or update grades.');
    }

    $postedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($postedToken)
        || !hash_equals($csrfToken, $postedToken)
    ) {
        http_response_code(403);
        exit('Invalid security token. Refresh the page and try again.');
    }

    $student_id = (int)($_POST['student_id'] ?? 0);
    $semester_id = (int)($_POST['semester_id'] ?? 0);
    $term_id = (int)($_POST['term_id'] ?? 0);

    $validStudent = false;
    $validSemester = false;
    $validTerm = false;

    foreach ($students as $s) {
        if ((int)$s['student_id'] === $student_id) {
            $validStudent = true;
            break;
        }
    }

    foreach ($semesters as $s) {
        if ((int)$s['semester_id'] === $semester_id) {
            $validSemester = true;
            break;
        }
    }

    foreach ($terms as $t) {
        if ((int)$t['term_id'] === $term_id) {
            $validTerm = true;
            break;
        }
    }

    if (!$validStudent || !$validSemester || !$validTerm) {
        $message = 'Please select a valid student, semester, and term.';
        $messageType = 'error';

    } elseif (count($cats) === 0) {
        $message = 'No assessment categories have been configured.';
        $messageType = 'error';

    } else {
        try {
            /*
             * Validate every category before saving anything.
             * A validation error rolls back the entire operation.
             */
            $validatedScores = [];

            foreach ($cats as $c) {
                $cid = (int)$c['category_id'];

                $earnedInput = $_POST['earned_' . $cid] ?? null;
                $maxInput = $_POST['max_' . $cid] ?? null;

                if (
                    !isValidNumber($earnedInput)
                    || !isValidNumber($maxInput)
                ) {
                    throw new RuntimeException(
                        'Enter valid numeric scores for ' .
                        $c['category_name'] . '.'
                    );
                }

                $earned = (float)$earnedInput;
                $submittedMax = (float)$maxInput;

                $maxScore = isAttendanceCategory($c['category_name'])
                    ? 14.0
                    : $submittedMax;

                if (
                    !is_finite($earned)
                    || !is_finite($submittedMax)
                    || $earned < 0
                    || $maxScore <= 0
                ) {
                    throw new RuntimeException(
                        'Scores must be non-negative and maximum points must be greater than zero for ' .
                        $c['category_name'] . '.'
                    );
                }

                if ($earned > $maxScore) {
                    throw new RuntimeException(
                        $c['category_name'] .
                        ' cannot exceed ' .
                        number_format($maxScore, 2) . ' points.'
                    );
                }

                $validatedScores[] = [
                    'category_id' => $cid,
                    'earned' => $earned,
                    'max_score' => $maxScore
                ];
            }

            $pdo->beginTransaction();

            /*
             * Keep compatibility with the existing scores table.
             * If legacy duplicate records exist, update the latest one.
             */
            $check = $pdo->prepare("
                SELECT score_id
                FROM scores
                WHERE student_id = ?
                  AND semester_id = ?
                  AND term_id = ?
                  AND category_id = ?
                ORDER BY score_id DESC
                LIMIT 1
            ");

            $update = $pdo->prepare("
                UPDATE scores
                SET earned = ?, max_score = ?, updated_at = NOW()
                WHERE score_id = ?
            ");

            $insert = $pdo->prepare("
                INSERT INTO scores
                    (student_id, semester_id, term_id,
                     category_id, earned, max_score)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            foreach ($validatedScores as $score) {
                $check->execute([
                    $student_id,
                    $semester_id,
                    $term_id,
                    $score['category_id']
                ]);

                $existingScoreId = $check->fetchColumn();

                if ($existingScoreId) {
                    $update->execute([
                        $score['earned'],
                        $score['max_score'],
                        (int)$existingScoreId
                    ]);
                } else {
                    $insert->execute([
                        $student_id,
                        $semester_id,
                        $term_id,
                        $score['category_id'],
                        $score['earned'],
                        $score['max_score']
                    ]);
                }
            }

            $pdo->commit();

            $message = 'Grades saved successfully.';
            $messageType = 'success';

        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $message = $e->getMessage();
            $messageType = 'error';

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Grade save error: ' . $e->getMessage());

            $message = 'Unable to save grades. Please check your database configuration and try again.';
            $messageType = 'error';
        }
    }
}

/*
|--------------------------------------------------------------------------
| LOAD EXISTING SCORES FOR SELECTED STUDENT / SEMESTER / TERM
|--------------------------------------------------------------------------
*/

$existing = [];

if ($student_id > 0 && $semester_id > 0 && $term_id > 0) {
    $stmt = $pdo->prepare("
        SELECT score_id, category_id, earned, max_score, updated_at
        FROM scores
        WHERE student_id = ?
          AND semester_id = ?
          AND term_id = ?
        ORDER BY score_id ASC
    ");

    $stmt->execute([$student_id, $semester_id, $term_id]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        // Later records replace earlier duplicate records.
        $existing[(int)$row['category_id']] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| SELECTED RECORDS
|--------------------------------------------------------------------------
*/

$selected_student = null;
$selected_semester = null;
$selected_term = null;

foreach ($students as $s) {
    if ((int)$s['student_id'] === $student_id) {
        $selected_student = $s;
        break;
    }
}

foreach ($semesters as $s) {
    if ((int)$s['semester_id'] === $semester_id) {
        $selected_semester = $s;
        break;
    }
}

foreach ($terms as $t) {
    if ((int)$t['term_id'] === $term_id) {
        $selected_term = $t;
        break;
    }
}

/*
|--------------------------------------------------------------------------
| CALCULATE TERM GRADES
|--------------------------------------------------------------------------
| Only categories with saved scores contribute to the term grade,
| preserving the calculation used in the uploaded file.
|--------------------------------------------------------------------------
*/

$termGrades = [];
$midtermGrade = null;
$finalGrade = null;
$overallGrade = null;

if ($student_id > 0 && $semester_id > 0) {
    /*
     * Load scores for all terms. If duplicate records exist,
     * use the latest score for each term/category combination.
     */
    $stmt = $pdo->prepare("
        SELECT
            s.score_id,
            s.term_id,
            s.category_id,
            s.earned,
            s.max_score
        FROM scores s
        WHERE s.student_id = ?
          AND s.semester_id = ?
        ORDER BY s.score_id ASC
    ");

    $stmt->execute([$student_id, $semester_id]);

    $allScores = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = (int)$row['term_id'] . '_' . (int)$row['category_id'];
        $allScores[$key] = $row;
    }

    foreach ($terms as $t) {
        $tid = (int)$t['term_id'];

        $termGrades[$tid] = [
            'term_name' => $t['term_name'],
            'weighted_total' => 0.0,
            'weight_total' => 0.0,
            'categories' => 0,
            'total_categories' => count($cats)
        ];

        foreach ($cats as $c) {
            $cid = (int)$c['category_id'];
            $key = $tid . '_' . $cid;

            if (!isset($allScores[$key])) {
                continue;
            }

            $score = $allScores[$key];
            $earned = (float)$score['earned'];
            $max = (float)$score['max_score'];
            $weight = (float)$c['weight'];

            if ($max <= 0 || $weight < 0) {
                continue;
            }

            $percentage = ($earned / $max) * 100;

            $termGrades[$tid]['weighted_total'] += $percentage * $weight;
            $termGrades[$tid]['weight_total'] += $weight;
            $termGrades[$tid]['categories']++;
        }
    }

    foreach ($termGrades as $grade) {
        if ($grade['weight_total'] <= 0) {
            continue;
        }

        $calculatedGrade =
            $grade['weighted_total'] / $grade['weight_total'];

        $name = strtolower(trim($grade['term_name']));

        if (strpos($name, 'midterm') !== false) {
            $midtermGrade = $calculatedGrade;
        }

        if (strpos($name, 'final') !== false) {
            $finalGrade = $calculatedGrade;
        }
    }

    if ($midtermGrade !== null && $finalGrade !== null) {
        $overallGrade =
            ($midtermGrade / 3) + (($finalGrade * 2) / 3);
    }
}

/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

require __DIR__ . '/header.php';
?>

<style>
:root {
    --gb-primary: #0f766e;
    --gb-primary-dark: #115e59;
    --gb-text: #0f172a;
    --gb-muted: #64748b;
    --gb-border: #e2e8f0;
    --gb-panel: #ffffff;
    --gb-bg: #f8fafc;
    --gb-success: #15803d;
    --gb-danger: #b91c1c;
}

.gradebook-page {
    max-width: 1450px;
    margin: 28px auto;
    padding: 0 22px;
    color: var(--gb-text);
}

.gradebook-page * {
    box-sizing: border-box;
}

.gradebook-page .hero {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 18px;
    flex-wrap: wrap;
    margin-bottom: 24px;
}

.gradebook-page h1 {
    font-size: clamp(25px, 3vw, 34px);
    margin: 0 0 8px;
    letter-spacing: -.5px;
}

.gradebook-page h2 {
    font-size: 19px;
    margin: 0 0 15px;
}

.gradebook-page p {
    line-height: 1.6;
}

.gradebook-page .muted {
    color: var(--gb-muted);
    margin: 0;
}

.gradebook-page .eyebrow {
    color: var(--gb-primary);
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    font-weight: 800;
    margin-bottom: 8px;
}

.gradebook-page .card {
    background: var(--gb-panel);
    border: 1px solid var(--gb-border);
    border-radius: 15px;
    padding: 23px;
    margin-bottom: 22px;
    box-shadow: 0 6px 22px rgba(15, 23, 42, .045);
}

.gradebook-page .grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 17px;
}

.gradebook-page label {
    display: block;
    color: #334155;
    font-weight: 700;
    font-size: 13px;
}

.gradebook-page input,
.gradebook-page select {
    width: 100%;
    min-height: 43px;
    padding: 10px 12px;
    margin-top: 7px;
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    color: #0f172a;
    font: inherit;
}

.gradebook-page input:focus,
.gradebook-page select:focus {
    outline: 3px solid rgba(13, 148, 136, .15);
    border-color: var(--gb-primary);
}

.gradebook-page .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 41px;
    padding: 10px 15px;
    border: 0;
    border-radius: 8px;
    font-weight: 750;
    font-size: 13px;
    text-decoration: none;
    cursor: pointer;
}

.gradebook-page .btn-primary {
    background: var(--gb-primary);
    color: #fff;
}

.gradebook-page .btn-primary:hover {
    background: var(--gb-primary-dark);
}

.gradebook-page .btn-light {
    color: #334155;
    background: #f1f5f9;
    border: 1px solid var(--gb-border);
}

.gradebook-page .btn-print {
    background: #0f172a;
    color: #fff;
}

.gradebook-page .notice {
    padding: 14px 17px;
    border-radius: 9px;
    margin-bottom: 20px;
    font-weight: 600;
}

.gradebook-page .notice.success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.gradebook-page .notice.error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.gradebook-page .student-info {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 15px;
}

.gradebook-page .info-item {
    background: var(--gb-bg);
    border: 1px solid var(--gb-border);
    border-radius: 10px;
    padding: 14px;
    min-width: 0;
}

.gradebook-page .info-label {
    display: block;
    font-size: 11px;
    color: var(--gb-muted);
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 7px;
}

.gradebook-page .info-value {
    font-weight: 750;
    overflow-wrap: anywhere;
}

.gradebook-page .table-wrap {
    width: 100%;
    overflow-x: auto;
}

.gradebook-page table {
    width: 100%;
    border-collapse: collapse;
    min-width: 720px;
}

.gradebook-page th,
.gradebook-page td {
    padding: 13px 12px;
    border-bottom: 1px solid var(--gb-border);
    text-align: left;
    vertical-align: middle;
    font-size: 13px;
}

.gradebook-page th {
    background: #f1f5f9;
    color: #475569;
    text-transform: uppercase;
    font-size: 11px;
    letter-spacing: .5px;
}

.gradebook-page tbody tr:hover {
    background: #f8fafc;
}

.gradebook-page .score-input {
    max-width: 125px;
    min-width: 85px;
    margin: 0;
}

.gradebook-page .weight-pill {
    display: inline-block;
    padding: 5px 9px;
    background: #ccfbf1;
    color: #115e59;
    border-radius: 20px;
    font-weight: 800;
}

.gradebook-page .status {
    display: inline-block;
    padding: 5px 8px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
}

.gradebook-page .status-pass {
    color: #166534;
    background: #dcfce7;
}

.gradebook-page .status-low {
    color: #991b1b;
    background: #fee2e2;
}

.gradebook-page .status-incomplete {
    color: #92400e;
    background: #fef3c7;
}

.gradebook-page .summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 15px;
}

.gradebook-page .summary-card {
    border: 1px solid var(--gb-border);
    border-radius: 12px;
    padding: 18px;
    background: #fff;
}

.gradebook-page .summary-card .label {
    font-size: 12px;
    font-weight: 750;
    color: var(--gb-muted);
}

.gradebook-page .summary-card .value {
    font-size: clamp(23px, 3vw, 30px);
    font-weight: 850;
    margin-top: 10px;
    overflow-wrap: anywhere;
}

.gradebook-page .formula {
    padding: 13px 15px;
    background: #f8fafc;
    border-left: 4px solid var(--gb-primary);
    border-radius: 6px;
    color: #475569;
    font-size: 13px;
}

.gradebook-page .print-header {
    display: none;
}

@media (max-width: 900px) {
    .gradebook-page .student-info,
    .gradebook-page .summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {
    .gradebook-page {
        padding: 0 12px;
        margin: 18px auto;
    }

    .gradebook-page .card {
        padding: 15px;
    }

    .gradebook-page .grid,
    .gradebook-page .student-info,
    .gradebook-page .summary-grid {
        grid-template-columns: 1fr;
    }

    .gradebook-page .hero {
        align-items: flex-start;
    }
}

@page {
    size: A4 portrait;
    margin: 14mm 12mm 24mm;
}

@media print {
    body {
        background: #fff !important;
        color: #000 !important;
    }

    body * {
        visibility: hidden !important;
    }

    #print-grade-report,
    #print-grade-report * {
        visibility: visible !important;
    }

    #print-grade-report {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        margin: 0 !important;
        padding: 0 !important;
        border: 0 !important;
        box-shadow: none !important;
        background: #fff !important;
    }

    #print-grade-report .no-print {
        display: none !important;
    }

    #print-grade-report .print-header {
        display: block !important;
        text-align: center;
        margin-bottom: 18px;
        color: #000 !important;
    }

    #print-grade-report .print-header h1 {
        font-size: 17pt;
        margin: 0 0 5px;
    }

    #print-grade-report .print-header p {
        margin: 3px 0;
        font-size: 10pt;
    }

    #print-grade-report .card,
    #print-grade-report .info-item,
    #print-grade-report .summary-card {
        box-shadow: none !important;
        border: 1px solid #777 !important;
        border-radius: 0 !important;
        break-inside: avoid;
    }

    #print-grade-report table {
        min-width: 0 !important;
        width: 100% !important;
        font-size: 9pt;
    }

    #print-grade-report th,
    #print-grade-report td {
        padding: 7px 6px !important;
        border: 1px solid #777 !important;
        color: #000 !important;
        background: #fff !important;
        font-size: 9pt !important;
    }

    #print-grade-report .weight-pill,
    #print-grade-report .status {
        color: #000 !important;
        background: #fff !important;
        border: 1px solid #777 !important;
    }

    /* FIX: Footer appears after the report instead of repeating on every page. */
    #print-grade-report .print-footer {
        display: block !important;
        position: static !important;
        width: 100%;
        margin-top: 35px;
        text-align: center;
        font-size: 10pt;
        color: #000 !important;
        background: #fff !important;
        padding-top: 5px;
        page-break-inside: avoid;
        break-inside: avoid;
    }

    #print-grade-report .prepared-line {
        display: block;
        width: 220px;
        height: 20px;
        border-bottom: 1px solid #000;
        margin: 0 auto 5px;
    }

    #print-grade-report .formula {
        border: 1px solid #777 !important;
        background: #fff !important;
        color: #000 !important;
    }

}
</style>

<div class="gradebook-page">

    <div class="hero no-print">
        <div>
            <div class="eyebrow">Academic Management System</div>
            <h1>Grade Entry & Assessment</h1>
            <p class="muted">
                IM 101a · Fundamentals of Database Systems
            </p>
        </div>

        <button
            type="button"
            class="btn btn-print"
            onclick="window.print()">
            &#128424; Print Grade Report
        </button>
    </div>

    <?php if ($message !== ''): ?>
        <div class="notice <?= e($messageType) ?> no-print" role="status">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <section class="card no-print">
        <h2>Select Grade Record</h2>

        <form method="get" class="grid">
            <div>
                <label for="student_id">Student</label>
                <select name="student_id" id="student_id" required>
                    <option value="">-- Select Student --</option>

                    <?php foreach ($students as $s): ?>
                        <option
                            value="<?= (int)$s['student_id'] ?>"
                            <?= $student_id === (int)$s['student_id'] ? 'selected' : '' ?>>
                            <?= e(
                                $s['student_no'] . ' - ' .
                                $s['last_name'] . ', ' .
                                $s['first_name'] .
                                (!empty($s['middle_name']) ? ' ' . $s['middle_name'] : '') .
                                (!empty($s['program']) ? ' - ' . $s['program'] : '')
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="semester_id">Semester / Academic Year</label>
                <select name="semester_id" id="semester_id" required>
                    <?php foreach ($semesters as $s): ?>
                        <option
                            value="<?= (int)$s['semester_id'] ?>"
                            <?= $semester_id === (int)$s['semester_id'] ? 'selected' : '' ?>>
                            <?= e($s['academic_year'] . ' - ' . $s['semester_name']) ?>
                            <?= (int)$s['is_active'] === 1 ? ' (Active)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="term_id">Term</label>
                <select name="term_id" id="term_id" required>
                    <?php foreach ($terms as $t): ?>
                        <option
                            value="<?= (int)$t['term_id'] ?>"
                            <?= $term_id === (int)$t['term_id'] ? 'selected' : '' ?>>
                            <?= e($t['term_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <button type="submit" class="btn btn-primary">
                    Load Grade Record
                </button>
            </div>
        </form>
    </section>

    <?php if ($student_id > 0 && $selected_student): ?>

        <section id="print-grade-report">

            <div class="print-header">
                <h1>IM 101a Grade Report</h1>
                <p>Fundamentals of Database Systems</p>
                <p>
                    <?= e(
                        ($selected_semester['academic_year'] ?? '') .
                        ' - ' .
                        ($selected_semester['semester_name'] ?? '')
                    ) ?>
                </p>
            </div>

            <section class="card">
                <h2>Student Information</h2>

                <div class="student-info">
                    <div class="info-item">
                        <span class="info-label">Student Number</span>
                        <div class="info-value">
                            <?= e($selected_student['student_no']) ?>
                        </div>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Student Name</span>
                        <div class="info-value">
                            <?= e(
                                $selected_student['last_name'] . ', ' .
                                $selected_student['first_name'] .
                                (!empty($selected_student['middle_name'])
                                    ? ' ' . $selected_student['middle_name']
                                    : '')
                            ) ?>
                        </div>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Program</span>
                        <div class="info-value">
                            <?= e($selected_student['program'] ?? '—') ?>
                        </div>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Semester</span>
                        <div class="info-value">
                            <?= e(
                                ($selected_semester['academic_year'] ?? '') .
                                ' - ' .
                                ($selected_semester['semester_name'] ?? '')
                            ) ?>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:15px;">
                    <div>
                        <h2 style="margin-bottom:5px;">
                            <?= e($selected_term['term_name'] ?? 'Grade Entry') ?>
                        </h2>
                        <p class="muted">
                            <?= $isAdmin
                                ? 'Enter scores below. Percentages update automatically.'
                                : 'View-only access. Contact an administrator to update grades.' ?>
                        </p>
                    </div>

                    <span class="status <?= count($existing) > 0 ? 'status-pass' : 'status-incomplete' ?>">
                        <?= count($existing) > 0 ? 'Existing Grades Found' : 'No Saved Grades' ?>
                    </span>
                </div>

                <?php if ($isAdmin): ?>
                    <form method="post" action="grades.php">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="student_id" value="<?= $student_id ?>">
                        <input type="hidden" name="semester_id" value="<?= $semester_id ?>">
                        <input type="hidden" name="term_id" value="<?= $term_id ?>">
                <?php endif; ?>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Assessment Category</th>
                                <th>Weight</th>
                                <th>Earned Points</th>
                                <th>Maximum Points</th>
                                <th>Percentage</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($cats as $c): ?>
                            <?php
                            $cid = (int)$c['category_id'];
                            $isAttendance = isAttendanceCategory($c['category_name']);

                            $hasSavedScore = isset($existing[$cid]);

                            if ($hasSavedScore) {
                                $earned = (float)$existing[$cid]['earned'];
                                $max = (float)$existing[$cid]['max_score'];
                            } else {
                                $earned = 0;
                                $max = 500;
                            }

                            if ($isAttendance) {
                                $max = 14;
                            }

                            $percentage = $max > 0
                                ? ($earned / $max) * 100
                                : 0;
                            ?>

                            <tr>
                                <td>
                                    <strong><?= e($c['category_name']) ?></strong>

                                    <?php if ($isAttendance): ?>
                                        <br>
                                        <small>Maximum: 14 points</small>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class="weight-pill">
                                        <?= number_format((float)$c['weight'] * 100, 2) ?>%
                                    </span>
                                </td>

                                <td>
                                    <?php if ($isAdmin): ?>
                                        <input
                                            class="score-input grade-earned"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="<?= e((string)$max) ?>"
                                            name="earned_<?= $cid ?>"
                                            data-category="<?= $cid ?>"
                                            value="<?= e((string)$earned) ?>"
                                            required>
                                    <?php else: ?>
                                        <?= number_format($earned, 2) ?>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if ($isAdmin): ?>
                                        <input
                                            class="score-input grade-max"
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            <?= $isAttendance ? 'max="14" readonly' : '' ?>
                                            name="max_<?= $cid ?>"
                                            data-category="<?= $cid ?>"
                                            value="<?= e((string)$max) ?>"
                                            required>
                                    <?php else: ?>
                                        <?= number_format($max, 2) ?>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span id="percentage_<?= $cid ?>">
                                        <?= number_format($percentage, 2) ?>%
                                    </span>
                                </td>

                                <td>
                                    <span
                                        id="status_<?= $cid ?>"
                                        class="status <?= !$hasSavedScore ? 'status-incomplete' : ($percentage >= 75 ? 'status-pass' : 'status-low') ?>">
                                        <?= !$hasSavedScore
                                            ? 'NOT SAVED'
                                            : ($percentage >= 75 ? 'PASS' : 'BELOW 75%') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($isAdmin): ?>
                    <div class="no-print" style="display:flex;gap:10px;flex-wrap:wrap;margin-top:20px;">
                        <button type="submit" class="btn btn-primary">
                            Save / Update Grades
                        </button>

                        <a
                            class="btn btn-light"
                            href="grades.php?student_id=<?= $student_id ?>&semester_id=<?= $semester_id ?>&term_id=<?= $term_id ?>">
                            Reload Grades
                        </a>
                    </div>
                    </form>
                <?php endif; ?>

                <p class="muted" style="font-size:12px;margin-top:15px;">
                    Category percentage is calculated using earned points divided by maximum points.
                    Only categories with saved scores contribute to the term grade.
                </p>
            </section>

            <section class="card">
                <h2>Term Grade Summary</h2>

                <div class="summary-grid">
                    <?php foreach ($termGrades as $grade): ?>
                        <?php
                        $termGradeValue = null;

                        if ($grade['weight_total'] > 0) {
                            $termGradeValue =
                                $grade['weighted_total'] / $grade['weight_total'];
                        }

                        $complete = $grade['categories'] === $grade['total_categories']
                            && $grade['total_categories'] > 0;
                        ?>
                        <div class="summary-card">
                            <div class="label"><?= e($grade['term_name']) ?></div>

                            <div class="value">
                                <?= $termGradeValue !== null
                                    ? number_format($termGradeValue, 2) . '%'
                                    : '—' ?>
                            </div>

                            <span class="status <?= $complete ? 'status-pass' : 'status-incomplete' ?>">
                                <?= $complete
                                    ? 'Complete'
                                    : $grade['categories'] . ' / ' . $grade['total_categories'] . ' categories saved' ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="summary-grid" style="margin-top:15px;">
                    <div class="summary-card">
                        <div class="label">Midterm Grade (1/3)</div>
                        <div class="value">
                            <?= $midtermGrade !== null
                                ? number_format($midtermGrade, 2) . '%'
                                : 'Incomplete' ?>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="label">Final Grade (2/3)</div>
                        <div class="value">
                            <?= $finalGrade !== null
                                ? number_format($finalGrade, 2) . '%'
                                : 'Incomplete' ?>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="label">Overall Grade</div>
                        <div class="value">
                            <?= $overallGrade !== null
                                ? number_format($overallGrade, 2) . '%'
                                : 'Incomplete' ?>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="label">Remarks</div>
                        <div class="value" style="font-size:20px;">
                            <?php if ($overallGrade === null): ?>
                                <span style="color:#92400e;">INCOMPLETE</span>
                            <?php elseif ($overallGrade >= 75): ?>
                                <span style="color:#15803d;">PASSED</span>
                            <?php else: ?>
                                <span style="color:#b91c1c;">FAILED</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="formula" style="margin-top:20px;">
                    <strong>Overall Grade Formula:</strong>
                    (Midterm Grade × 1/3) + (Final Grade × 2/3).
                    The overall grade is shown only when both term grades are available.
                </div>
            </section>

            <div class="print-footer" style="display:none;">
                <div>Prepared by:</div>
                <div class="prepared-line"></div>
                <div>IM 101a Instructor</div>
            </div>

        </section>

    <?php elseif ($student_id > 0): ?>

        <div class="notice error">
            The selected student could not be found. Please select a valid student.
        </div>

    <?php else: ?>

        <section class="card no-print">
            <h2>Welcome to the Gradebook</h2>
            <p class="muted">
                Select a student, semester, and term above to view assessment scores
                and calculate grades.
            </p>
        </section>

    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const earnedInputs = document.querySelectorAll('.grade-earned');

    earnedInputs.forEach(function (earnedInput) {
        const category = earnedInput.dataset.category;

        const maxInput = document.querySelector(
            '.grade-max[data-category="' + category + '"]'
        );

        const percentageDisplay = document.getElementById(
            'percentage_' + category
        );

        const statusDisplay = document.getElementById(
            'status_' + category
        );

        if (!maxInput || !percentageDisplay || !statusDisplay) {
            return;
        }

        function updatePercentage() {
            const earned = parseFloat(earnedInput.value);
            const max = parseFloat(maxInput.value);

            if (
                earnedInput.value.trim() === '' ||
                maxInput.value.trim() === '' ||
                !Number.isFinite(earned) ||
                !Number.isFinite(max) ||
                max <= 0
            ) {
                percentageDisplay.textContent = '—';
                statusDisplay.textContent = 'INCOMPLETE';
                statusDisplay.className = 'status status-incomplete';
                earnedInput.setCustomValidity('');
                maxInput.setCustomValidity(
                    max <= 0 ? 'Maximum points must be greater than zero.' : ''
                );
                return;
            }

            const percentage = (earned / max) * 100;

            percentageDisplay.textContent = percentage.toFixed(2) + '%';

            if (earned > max) {
                statusDisplay.textContent = 'OVER MAXIMUM';
                statusDisplay.className = 'status status-low';
                earnedInput.setCustomValidity(
                    'Earned points cannot exceed the maximum of ' + max + '.'
                );
            } else {
                earnedInput.setCustomValidity('');

                statusDisplay.textContent =
                    percentage >= 75 ? 'PASS' : 'BELOW 75%';

                statusDisplay.className =
                    percentage >= 75
                        ? 'status status-pass'
                        : 'status status-low';
            }

            maxInput.setCustomValidity(
                max <= 0 ? 'Maximum points must be greater than zero.' : ''
            );
        }

        earnedInput.addEventListener('input', updatePercentage);
        maxInput.addEventListener('input', updatePercentage);

        updatePercentage();
    });
});
</script>

<?php require __DIR__ . '/footer.php'; ?>
