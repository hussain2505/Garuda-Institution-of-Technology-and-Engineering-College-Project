<?php
require_once __DIR__ . '/../includes/auth-check.php';
requireRole('Student');

$stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();

if (!$student) {
    die("Student profile not found. Please contact the administrator.");
}
$studentId = $student['student_id'];

// Attendance summary
$att = $pdo->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(status = 'present') AS present
     FROM attendance WHERE student_id = ?"
);
$att->execute([$studentId]);
$attRow = $att->fetch();
$attendancePct = calculateAttendancePercentage((int)($attRow['present'] ?? 0), (int)($attRow['total'] ?? 0));

// Fee summary
$fees = $pdo->prepare(
    "SELECT fs.fee_name, sf.amount_due, sf.amount_paid, sf.due_date, sf.status
     FROM student_fees sf
     JOIN fee_structures fs ON fs.fee_structure_id = sf.fee_structure_id
     WHERE sf.student_id = ?
     ORDER BY sf.due_date"
);
$fees->execute([$studentId]);
$feeRows = $fees->fetchAll();
$totalDue = array_sum(array_column($feeRows, 'amount_due')) - array_sum(array_column($feeRows, 'amount_paid'));

// Recent marks
$marks = $pdo->prepare(
    "SELECT sub.subject_name, m.marks_obtained, m.maximum_marks, e.exam_name
     FROM marks m
     JOIN subjects sub ON sub.subject_id = m.subject_id
     JOIN exams e ON e.exam_id = m.exam_id
     WHERE m.student_id = ?
     ORDER BY m.mark_id DESC LIMIT 5"
);
$marks->execute([$studentId]);
$markRows = $marks->fetchAll();

// Notices
$notices = $pdo->query("SELECT title, message, created_at FROM notifications ORDER BY created_at DESC LIMIT 5")->fetchAll();

$pageTitle = APP_NAME . ' | Student Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<h2>Welcome, <?= e($student['first_name']) ?> <?= e($student['last_name']) ?></h2>
<p class="muted">Roll No: <?= e($student['roll_number']) ?> · Semester <?= e($student['current_semester']) ?> · Section <?= e($student['section']) ?></p>

<div class="stats-grid">
    <div class="stat-card">
        <span class="stat-label">Attendance</span>
        <span class="stat-value <?= $attendancePct < 75 ? 'stat-danger' : 'stat-good' ?>"><?= $attendancePct ?>%</span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Fee Balance</span>
        <span class="stat-value"><?= '₹' . number_format(max($totalDue, 0), 2) ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Recent Assessments</span>
        <span class="stat-value"><?= count($markRows) ?></span>
    </div>
</div>

<div class="quick-links">
    <a href="<?= BASE_URL ?>/student/attendance.php" class="btn btn-outline">View Attendance</a>
    <a href="<?= BASE_URL ?>/student/results.php" class="btn btn-outline">View Results</a>
    <a href="<?= BASE_URL ?>/student/fees.php" class="btn btn-outline">Fee Details</a>
    <a href="<?= BASE_URL ?>/student/library.php" class="btn btn-outline">My Library</a>
    <a href="<?= BASE_URL ?>/student/timetable.php" class="btn btn-outline">Timetable</a>
    <a href="<?= BASE_URL ?>/student/hostel.php" class="btn btn-outline">Hostel</a>
    <a href="<?= BASE_URL ?>/student/placements.php" class="btn btn-outline">Placements</a>
    <a href="<?= BASE_URL ?>/student/grievances.php" class="btn btn-outline">Grievances</a>
</div>

<div class="two-col">
    <div class="chart-card">
        <h3>Attendance Snapshot</h3>
        <div class="chart-wrap"><canvas id="attChart"></canvas></div>
    </div>

    <div>
        <div class="card">
            <h3>Recent Marks</h3>
            <?php if ($markRows): ?>
                <table class="data-table">
                    <tr><th>Subject</th><th>Exam</th><th>Marks</th></tr>
                    <?php foreach ($markRows as $m): ?>
                        <tr>
                            <td><?= e($m['subject_name']) ?></td>
                            <td><?= e($m['exam_name']) ?></td>
                            <td><?= e($m['marks_obtained']) ?> / <?= e($m['maximum_marks']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p class="muted">No marks recorded yet.</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Notices</h3>
            <?php if ($notices): ?>
                <ul class="plain-list">
                    <?php foreach ($notices as $n): ?>
                        <li><strong><?= e($n['title']) ?></strong><br>
                            <small><?= e(date('d M Y', strtotime($n['created_at']))) ?></small></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="muted">No notices.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
new Chart(document.getElementById('attChart'), {
    type: 'doughnut',
    data: {
        labels: ['Present', 'Absent/Other'],
        datasets: [{
            data: [<?= (int)($attRow['present'] ?? 0) ?>, <?= max(0, (int)($attRow['total'] ?? 0) - (int)($attRow['present'] ?? 0)) ?>],
            backgroundColor: ['#1e8e5a', '#c0392b']
        }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
