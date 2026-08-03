<?php
require_once __DIR__ . '/../includes/auth-check.php';
requireRole('Super Admin', 'Admin');

$counts = [
    'students'   => $pdo->query("SELECT COUNT(*) FROM students WHERE status='active'")->fetchColumn(),
    'faculty'    => $pdo->query("SELECT COUNT(*) FROM faculty WHERE status='active'")->fetchColumn(),
    'departments'=> $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn(),
    'grievances' => $pdo->query("SELECT COUNT(*) FROM grievances WHERE status NOT IN ('resolved','closed')")->fetchColumn(),
];

$security = [
    'successful_logins' => $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action='login_success'")->fetchColumn(),
    'failed_logins'     => $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action='login_failed'")->fetchColumn(),
    'locked_accounts'   => $pdo->query("SELECT COUNT(*) FROM users WHERE status='locked' OR (locked_until IS NOT NULL AND locked_until > NOW())")->fetchColumn(),
];

$feeCollection = $pdo->query(
    "SELECT COALESCE(SUM(amount_paid),0) AS collected, COALESCE(SUM(amount_due - amount_paid),0) AS pending FROM student_fees"
)->fetch();

$recentAudit = $pdo->query(
    "SELECT al.action, al.module, al.description, al.created_at, u.username
     FROM audit_logs al LEFT JOIN users u ON u.user_id = al.user_id
     ORDER BY al.created_at DESC LIMIT 10"
)->fetchAll();

// ---- Chart data ----
$deptDistribution = $pdo->query(
    "SELECT d.department_name, COUNT(s.student_id) AS total
     FROM departments d LEFT JOIN students s ON s.department_id = d.department_id AND s.status = 'active'
     GROUP BY d.department_id, d.department_name
     ORDER BY d.department_name"
)->fetchAll();

$attendanceTrend = $pdo->query(
    "SELECT attendance_date, SUM(status='present') AS present, COUNT(*) AS total
     FROM attendance
     WHERE attendance_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
     GROUP BY attendance_date
     ORDER BY attendance_date"
)->fetchAll();

$pageTitle = APP_NAME . ' | Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<h2>Admin Dashboard</h2>
<p class="muted">Signed in as <?= e($_SESSION['username']) ?> (<?= e(currentRole()) ?>)</p>

<div class="stats-grid">
    <div class="stat-card"><span class="stat-label">Active Students</span><span class="stat-value"><?= (int)$counts['students'] ?></span></div>
    <div class="stat-card"><span class="stat-label">Faculty</span><span class="stat-value"><?= (int)$counts['faculty'] ?></span></div>
    <div class="stat-card"><span class="stat-label">Departments</span><span class="stat-value"><?= (int)$counts['departments'] ?></span></div>
    <div class="stat-card"><span class="stat-label">Open Grievances</span><span class="stat-value"><?= (int)$counts['grievances'] ?></span></div>
</div>

<div class="quick-links">
    <a href="<?= BASE_URL ?>/admin/students.php" class="btn btn-outline">Manage Students</a>
    <a href="<?= BASE_URL ?>/admin/library.php" class="btn btn-outline">Library Management</a>
    <a href="<?= BASE_URL ?>/admin/fees.php" class="btn btn-outline">Fee Management</a>
    <a href="<?= BASE_URL ?>/admin/timetable.php" class="btn btn-outline">Timetable</a>
    <a href="<?= BASE_URL ?>/admin/hostel.php" class="btn btn-outline">Hostel</a>
    <a href="<?= BASE_URL ?>/admin/placements.php" class="btn btn-outline">Placements</a>
    <a href="<?= BASE_URL ?>/admin/notices.php" class="btn btn-outline">Post Notice</a>
    <a href="<?= BASE_URL ?>/admin/audit-log.php" class="btn btn-outline">Security &amp; Audit Log</a>
</div>

<div class="chart-grid">
    <div class="chart-card">
        <h3>Students by Department</h3>
        <div class="chart-wrap"><canvas id="deptChart"></canvas></div>
    </div>
    <div class="chart-card">
        <h3>Fee Collection</h3>
        <div class="chart-wrap"><canvas id="feeChart"></canvas></div>
    </div>
</div>

<div class="chart-card">
    <h3>Attendance — Last 14 Days</h3>
    <div class="chart-wrap"><canvas id="attendanceChart"></canvas></div>
</div>

<div class="two-col">
    <div class="card">
        <h3>Security Center</h3>
        <table class="data-table">
            <tr><td>Successful Logins</td><td><?= (int)$security['successful_logins'] ?></td></tr>
            <tr><td>Failed Logins</td><td><?= (int)$security['failed_logins'] ?></td></tr>
            <tr><td>Locked Accounts</td><td><?= (int)$security['locked_accounts'] ?></td></tr>
        </table>
    </div>
    <div class="card">
        <h3>Fee Collection</h3>
        <table class="data-table">
            <tr><td>Collected</td><td>₹<?= number_format($feeCollection['collected'], 2) ?></td></tr>
            <tr><td>Pending</td><td>₹<?= number_format($feeCollection['pending'], 2) ?></td></tr>
        </table>
    </div>
</div>

<div class="card">
    <h3>Recent Activity</h3>
    <table class="data-table">
        <tr><th>User</th><th>Action</th><th>Module</th><th>Details</th><th>Time</th></tr>
        <?php foreach ($recentAudit as $a): ?>
            <tr>
                <td><?= e($a['username'] ?? 'system') ?></td>
                <td><?= e($a['action']) ?></td>
                <td><?= e($a['module']) ?></td>
                <td><?= e($a['description']) ?></td>
                <td><?= e(date('d M Y H:i', strtotime($a['created_at']))) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$recentAudit): ?>
            <tr><td colspan="5" class="muted">No activity recorded yet.</td></tr>
        <?php endif; ?>
    </table>
</div>

<script>
new Chart(document.getElementById('deptChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($deptDistribution, 'department_name')) ?>,
        datasets: [{
            data: <?= json_encode(array_map('intval', array_column($deptDistribution, 'total'))) ?>,
            backgroundColor: ['#0f2547', '#17356b', '#d4a017', '#2563eb', '#1e8e5a', '#c0392b']
        }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
});

new Chart(document.getElementById('feeChart'), {
    type: 'bar',
    data: {
        labels: ['Collected', 'Pending'],
        datasets: [{
            data: [<?= (float)$feeCollection['collected'] ?>, <?= (float)$feeCollection['pending'] ?>],
            backgroundColor: ['#1e8e5a', '#c0392b']
        }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
});

new Chart(document.getElementById('attendanceChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_map(fn($r) => date('d M', strtotime($r['attendance_date'])), $attendanceTrend)) ?>,
        datasets: [{
            label: 'Attendance %',
            data: <?= json_encode(array_map(fn($r) => calculateAttendancePercentage((int)$r['present'], (int)$r['total']), $attendanceTrend)) ?>,
            borderColor: '#0f2547',
            backgroundColor: 'rgba(15,37,71,0.1)',
            tension: 0.3,
            fill: true
        }]
    },
    options: { responsive: true, maintainAspectRatio: false, scales: { y: { min: 0, max: 100 } }, plugins: { legend: { display: false } } }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
