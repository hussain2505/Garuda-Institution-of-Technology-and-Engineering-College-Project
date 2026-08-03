<?php
require_once __DIR__ . '/../includes/auth-check.php';
requireRole('Student');

$stmt = $pdo->prepare("SELECT student_id, first_name, last_name FROM students WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();
if (!$student) die("Student profile not found.");
$studentId = $student['student_id'];

// Per-subject breakdown
$bySubject = $pdo->prepare(
    "SELECT sub.subject_name,
            COUNT(*) AS total,
            SUM(a.status = 'present') AS present
     FROM attendance a
     JOIN subjects sub ON sub.subject_id = a.subject_id
     WHERE a.student_id = ?
     GROUP BY sub.subject_id, sub.subject_name
     ORDER BY sub.subject_name"
);
$bySubject->execute([$studentId]);
$subjects = $bySubject->fetchAll();

// Recent log
$log = $pdo->prepare(
    "SELECT a.attendance_date, sub.subject_name, a.status, a.remarks
     FROM attendance a
     JOIN subjects sub ON sub.subject_id = a.subject_id
     WHERE a.student_id = ?
     ORDER BY a.attendance_date DESC LIMIT 30"
);
$log->execute([$studentId]);
$logRows = $log->fetchAll();

$pageTitle = APP_NAME . ' | My Attendance';
include __DIR__ . '/../includes/header.php';
?>

<h2>My Attendance</h2>

<div class="card">
    <h3>Subject-wise Summary</h3>
    <?php if ($subjects): ?>
        <table class="data-table">
            <tr><th>Subject</th><th>Present</th><th>Total Classes</th><th>Percentage</th></tr>
            <?php foreach ($subjects as $s):
                $pct = calculateAttendancePercentage((int)$s['present'], (int)$s['total']); ?>
                <tr>
                    <td><?= e($s['subject_name']) ?></td>
                    <td><?= (int)$s['present'] ?></td>
                    <td><?= (int)$s['total'] ?></td>
                    <td class="<?= $pct < 75 ? 'stat-danger' : 'stat-good' ?>"><?= $pct ?>%</td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p class="muted">No attendance recorded yet.</p>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Recent Attendance Log</h3>
    <?php if ($logRows): ?>
        <table class="data-table">
            <tr><th>Date</th><th>Subject</th><th>Status</th><th>Remarks</th></tr>
            <?php foreach ($logRows as $r): ?>
                <tr>
                    <td><?= e(date('d M Y', strtotime($r['attendance_date']))) ?></td>
                    <td><?= e($r['subject_name']) ?></td>
                    <td><span class="badge badge-<?= e($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                    <td><?= e($r['remarks']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p class="muted">Nothing to show yet.</p>
    <?php endif; ?>
</div>

<a href="<?= BASE_URL ?>/student/dashboard.php" class="btn btn-outline">&larr; Back to Dashboard</a>

<?php include __DIR__ . '/../includes/footer.php'; ?>
