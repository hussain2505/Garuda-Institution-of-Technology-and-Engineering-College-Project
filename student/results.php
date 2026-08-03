<?php
require_once __DIR__ . '/../includes/auth-check.php';
requireRole('Student');

$stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();
if (!$student) die("Student profile not found.");

$marks = $pdo->prepare(
    "SELECT e.exam_name, e.exam_type, sub.subject_name, m.marks_obtained, m.maximum_marks, m.grade
     FROM marks m
     JOIN exams e ON e.exam_id = m.exam_id
     JOIN subjects sub ON sub.subject_id = m.subject_id
     WHERE m.student_id = ?
     ORDER BY e.exam_id DESC, sub.subject_name"
);
$marks->execute([$student['student_id']]);
$rows = $marks->fetchAll();

// group by exam
$grouped = [];
foreach ($rows as $r) {
    $grouped[$r['exam_name']][] = $r;
}

$pageTitle = APP_NAME . ' | My Results';
include __DIR__ . '/../includes/header.php';
?>

<h2>My Results</h2>

<?php if (!$grouped): ?>
    <div class="card"><p class="muted">No results published yet.</p></div>
<?php endif; ?>

<?php foreach ($grouped as $examName => $subjectRows): ?>
    <div class="card">
        <h3><?= e($examName) ?></h3>
        <table class="data-table">
            <tr><th>Subject</th><th>Marks Obtained</th><th>Maximum</th><th>Grade</th></tr>
            <?php foreach ($subjectRows as $r): ?>
                <tr>
                    <td><?= e($r['subject_name']) ?></td>
                    <td><?= e($r['marks_obtained']) ?></td>
                    <td><?= e($r['maximum_marks']) ?></td>
                    <td><?= e($r['grade'] ?: '-') ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php endforeach; ?>

<a href="<?= BASE_URL ?>/student/dashboard.php" class="btn btn-outline">&larr; Back to Dashboard</a>

<?php include __DIR__ . '/../includes/footer.php'; ?>
