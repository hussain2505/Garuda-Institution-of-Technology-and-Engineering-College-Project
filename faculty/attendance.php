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

$selectedSubject = (int)($_GET['subject_id'] ?? ($_POST['subject_id'] ?? ($subjectRows[0]['subject_id'] ?? 0)));
$selectedDate = $_GET['date'] ?? ($_POST['attendance_date'] ?? date('Y-m-d'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $subjectId = (int)$_POST['subject_id'];
    $date = $_POST['attendance_date'];
    $statuses = $_POST['status'] ?? []; // [student_id => status]

    // confirm this subject belongs to faculty's department
    $chk = $pdo->prepare("SELECT 1 FROM subjects WHERE subject_id = ? AND department_id = ?");
    $chk->execute([$subjectId, $faculty['department_id']]);

    if ($chk->fetchColumn() && $statuses) {
        $upsert = $pdo->prepare(
            "INSERT INTO attendance (student_id, subject_id, faculty_id, attendance_date, status)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status = VALUES(status), faculty_id = VALUES(faculty_id)"
        );
        foreach ($statuses as $studentId => $status) {
            if (!in_array($status, ['present','absent','late','excused'], true)) continue;
            $upsert->execute([(int)$studentId, $subjectId, $faculty['faculty_id'], $date, $status]);
        }
        logAudit($pdo, 'attendance_marked', 'attendance', $subjectId, "Marked attendance for subject #$subjectId on $date");
        setFlash('success', 'Attendance saved successfully.');
    } else {
        setFlash('error', 'Could not save attendance. Please check the subject and try again.');
    }
    redirect(BASE_URL . "/faculty/attendance.php?subject_id=$subjectId&date=$date");
}

// students in this department, with today's existing status for the selected subject/date
$students = [];
if ($selectedSubject) {
    $studStmt = $pdo->prepare(
        "SELECT s.student_id, s.roll_number, s.first_name, s.last_name,
                a.status AS existing_status
         FROM students s
         LEFT JOIN attendance a
                ON a.student_id = s.student_id
               AND a.subject_id = ?
               AND a.attendance_date = ?
         WHERE s.department_id = ? AND s.status = 'active'
         ORDER BY s.roll_number"
    );
    $studStmt->execute([$selectedSubject, $selectedDate, $faculty['department_id']]);
    $students = $studStmt->fetchAll();
}

$pageTitle = APP_NAME . ' | Mark Attendance';
include __DIR__ . '/../includes/header.php';
?>

<h2>Mark Attendance</h2>

<form method="get" class="filter-form">
    <label>Subject</label>
    <select name="subject_id" onchange="this.form.submit()">
        <?php foreach ($subjectRows as $s): ?>
            <option value="<?= (int)$s['subject_id'] ?>" <?= $s['subject_id'] == $selectedSubject ? 'selected' : '' ?>>
                <?= e($s['subject_code']) ?> - <?= e($s['subject_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <label>Date</label>
    <input type="date" name="date" value="<?= e($selectedDate) ?>" onchange="this.form.submit()">
</form>

<?php if ($students): ?>
<form method="post" class="card">
    <?php csrfField(); ?>
    <input type="hidden" name="subject_id" value="<?= (int)$selectedSubject ?>">
    <input type="hidden" name="attendance_date" value="<?= e($selectedDate) ?>">

    <table class="data-table">
        <tr><th>Roll No</th><th>Name</th><th>Status</th></tr>
        <?php foreach ($students as $s): ?>
            <tr>
                <td><?= e($s['roll_number']) ?></td>
                <td><?= e($s['first_name']) ?> <?= e($s['last_name']) ?></td>
                <td>
                    <select name="status[<?= (int)$s['student_id'] ?>]">
                        <?php foreach (['present','absent','late','excused'] as $opt): ?>
                            <option value="<?= $opt ?>" <?= ($s['existing_status'] ?? 'present') === $opt ? 'selected' : '' ?>>
                                <?= ucfirst($opt) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    <button type="submit" class="btn btn-primary">Save Attendance</button>
</form>
<?php else: ?>
    <div class="card"><p class="muted">No students found, or no subject selected.</p></div>
<?php endif; ?>

<a href="<?= BASE_URL ?>/faculty/dashboard.php" class="btn btn-outline">&larr; Back to Dashboard</a>

<?php include __DIR__ . '/../includes/footer.php'; ?>
