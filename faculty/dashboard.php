<?php
require_once __DIR__ . '/../includes/auth-check.php';
requireRole('Faculty');

$stmt = $pdo->prepare("SELECT * FROM faculty WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$faculty = $stmt->fetch();
if (!$faculty) die("Faculty profile not found. Please contact the administrator.");

$subjects = $pdo->prepare(
    "SELECT subject_id, subject_code, subject_name, semester FROM subjects WHERE department_id = ? ORDER BY semester, subject_name"
);
$subjects->execute([$faculty['department_id']]);
$subjectRows = $subjects->fetchAll();

$studentCount = $pdo->prepare("SELECT COUNT(*) FROM students WHERE department_id = ? AND status='active'");
$studentCount->execute([$faculty['department_id']]);
$totalStudents = $studentCount->fetchColumn();

$pageTitle = APP_NAME . ' | Faculty Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<h2>Welcome, <?= e($faculty['first_name']) ?> <?= e($faculty['last_name']) ?></h2>
<p class="muted"><?= e($faculty['designation']) ?> · Employee ID: <?= e($faculty['employee_id']) ?></p>

<div class="stats-grid">
    <div class="stat-card">
        <span class="stat-label">Subjects Handled</span>
        <span class="stat-value"><?= count($subjectRows) ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Department Students</span>
        <span class="stat-value"><?= (int)$totalStudents ?></span>
    </div>
</div>

<div class="quick-links">
    <a href="<?= BASE_URL ?>/faculty/attendance.php" class="btn btn-outline">Mark Attendance</a>
    <a href="<?= BASE_URL ?>/faculty/marks.php" class="btn btn-outline">Enter Marks</a>
</div>

<div class="card">
    <h3>My Subjects</h3>
    <table class="data-table">
        <tr><th>Code</th><th>Subject</th><th>Semester</th></tr>
        <?php foreach ($subjectRows as $s): ?>
            <tr>
                <td><?= e($s['subject_code']) ?></td>
                <td><?= e($s['subject_name']) ?></td>
                <td><?= e($s['semester']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$subjectRows): ?>
            <tr><td colspan="3" class="muted">No subjects assigned to your department yet.</td></tr>
        <?php endif; ?>
    </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
