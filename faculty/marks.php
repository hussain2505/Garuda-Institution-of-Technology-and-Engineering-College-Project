<?php
require_once __DIR__ . '/../includes/auth-check.php';
requireRole('Faculty');

$stmt = $pdo->prepare("SELECT * FROM faculty WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$faculty = $stmt->fetch();
if (!$faculty) die("Faculty profile not found.");

$subjects = $pdo->prepare("SELECT subject_id, subject_code, subject_name FROM subjects WHERE department_id = ? ORDER BY subject_name");
$subjects->execute([$faculty['department_id']]);
$subjectRows = $subjects->fetchAll();

$exams = $pdo->query("SELECT exam_id, exam_name FROM exams ORDER BY exam_id DESC")->fetchAll();

$selectedSubject = (int)($_GET['subject_id'] ?? ($_POST['subject_id'] ?? ($subjectRows[0]['subject_id'] ?? 0)));
$selectedExam = (int)($_GET['exam_id'] ?? ($_POST['exam_id'] ?? ($exams[0]['exam_id'] ?? 0)));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $subjectId = (int)$_POST['subject_id'];
    $examId = (int)$_POST['exam_id'];
    $marksArr = $_POST['marks'] ?? [];
    $maxMarks = (float)($_POST['maximum_marks'] ?? 100);

    $chk = $pdo->prepare("SELECT 1 FROM subjects WHERE subject_id = ? AND department_id = ?");
    $chk->execute([$subjectId, $faculty['department_id']]);

    if ($chk->fetchColumn() && $subjectId && $examId) {
        $upsert = $pdo->prepare(
            "INSERT INTO marks (exam_id, student_id, subject_id, marks_obtained, maximum_marks, grade)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE marks_obtained = VALUES(marks_obtained), maximum_marks = VALUES(maximum_marks), grade = VALUES(grade)"
        );
        foreach ($marksArr as $studentId => $obtained) {
            if ($obtained === '') continue;
            $obtained = (float)$obtained;
            $pct = $maxMarks > 0 ? ($obtained / $maxMarks) * 100 : 0;
            $grade = match (true) {
                $pct >= 90 => 'A+', $pct >= 80 => 'A', $pct >= 70 => 'B+',
                $pct >= 60 => 'B', $pct >= 50 => 'C', $pct >= 35 => 'D',
                default => 'F',
            };
            $upsert->execute([$examId, (int)$studentId, $subjectId, $obtained, $maxMarks, $grade]);
        }
        logAudit($pdo, 'marks_entered', 'exams', $examId, "Marks entered for subject #$subjectId, exam #$examId");
        setFlash('success', 'Marks saved successfully.');
    } else {
        setFlash('error', 'Please select a valid subject and exam.');
    }
    redirect(BASE_URL . "/faculty/marks.php?subject_id=$subjectId&exam_id=$examId");
}

$students = [];
if ($selectedSubject && $selectedExam) {
    $s = $pdo->prepare(
        "SELECT s.student_id, s.roll_number, s.first_name, s.last_name, m.marks_obtained
         FROM students s
         LEFT JOIN marks m ON m.student_id = s.student_id AND m.subject_id = ? AND m.exam_id = ?
         WHERE s.department_id = ? AND s.status = 'active'
         ORDER BY s.roll_number"
    );
    $s->execute([$selectedSubject, $selectedExam, $faculty['department_id']]);
    $students = $s->fetchAll();
}

$pageTitle = APP_NAME . ' | Enter Marks';
include __DIR__ . '/../includes/header.php';
?>

<h2>Enter Marks</h2>

<?php if (!$exams): ?>
    <div class="alert alert-info">No exams exist yet. Ask an admin to create one, or add a row to the <code>exams</code> table.</div>
<?php endif; ?>

<form method="get" class="filter-form">
    <label>Subject</label>
    <select name="subject_id" onchange="this.form.submit()">
        <?php foreach ($subjectRows as $s): ?>
            <option value="<?= (int)$s['subject_id'] ?>" <?= $s['subject_id'] == $selectedSubject ? 'selected' : '' ?>>
                <?= e($s['subject_code']) ?> - <?= e($s['subject_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <label>Exam</label>
    <select name="exam_id" onchange="this.form.submit()">
        <?php foreach ($exams as $ex): ?>
            <option value="<?= (int)$ex['exam_id'] ?>" <?= $ex['exam_id'] == $selectedExam ? 'selected' : '' ?>>
                <?= e($ex['exam_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

<?php if ($students): ?>
<form method="post" class="card">
    <?php csrfField(); ?>
    <input type="hidden" name="subject_id" value="<?= (int)$selectedSubject ?>">
    <input type="hidden" name="exam_id" value="<?= (int)$selectedExam ?>">

    <label>Maximum Marks</label>
    <input type="number" name="maximum_marks" value="100" step="0.5" style="max-width:120px">

    <table class="data-table">
        <tr><th>Roll No</th><th>Name</th><th>Marks Obtained</th></tr>
        <?php foreach ($students as $s): ?>
            <tr>
                <td><?= e($s['roll_number']) ?></td>
                <td><?= e($s['first_name']) ?> <?= e($s['last_name']) ?></td>
                <td>
                    <input type="number" step="0.5" min="0" name="marks[<?= (int)$s['student_id'] ?>]"
                           value="<?= e($s['marks_obtained']) ?>" style="max-width:100px">
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    <button type="submit" class="btn btn-primary">Save Marks</button>
</form>
<?php else: ?>
    <div class="card"><p class="muted">Select a subject and exam to enter marks.</p></div>
<?php endif; ?>

<a href="<?= BASE_URL ?>/faculty/dashboard.php" class="btn btn-outline">&larr; Back to Dashboard</a>

<?php include __DIR__ . '/../includes/footer.php'; ?>
