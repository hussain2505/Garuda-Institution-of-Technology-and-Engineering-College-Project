<?php
require_once __DIR__ . '/../includes/auth-check.php';
requireRole('Parent');

$stmt = $pdo->prepare("SELECT * FROM parents WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$parent = $stmt->fetch();
if (!$parent) die("Parent profile not found. Please contact the administrator.");

// Only children actually linked to this parent account are ever shown
$children = $pdo->prepare(
    "SELECT s.student_id, s.roll_number, s.first_name, s.last_name, s.current_semester, d.department_name
     FROM student_parents sp
     JOIN students s ON s.student_id = sp.student_id
     JOIN departments d ON d.department_id = s.department_id
     WHERE sp.parent_id = ?"
);
$children->execute([$parent['parent_id']]);
$childRows = $children->fetchAll();

$pageTitle = APP_NAME . ' | Parent Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<h2>Welcome, <?= e($parent['first_name']) ?> <?= e($parent['last_name']) ?></h2>

<?php if (!$childRows): ?>
    <div class="card"><p class="muted">No student accounts are linked to your profile yet. Please contact the administrator.</p></div>
<?php endif; ?>

<?php foreach ($childRows as $child):
    $att = $pdo->prepare("SELECT COUNT(*) AS total, SUM(status='present') AS present FROM attendance WHERE student_id = ?");
    $att->execute([$child['student_id']]);
    $attRow = $att->fetch();
    $pct = calculateAttendancePercentage((int)($attRow['present'] ?? 0), (int)($attRow['total'] ?? 0));

    $fees = $pdo->prepare("SELECT SUM(amount_due - amount_paid) AS balance FROM student_fees WHERE student_id = ?");
    $fees->execute([$child['student_id']]);
    $balance = (float)($fees->fetch()['balance'] ?? 0);
?>
    <div class="card">
        <h3><?= e($child['first_name']) ?> <?= e($child['last_name']) ?> (<?= e($child['roll_number']) ?>)</h3>
        <p class="muted"><?= e($child['department_name']) ?> · Semester <?= e($child['current_semester']) ?></p>
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-label">Attendance</span>
                <span class="stat-value <?= $pct < 75 ? 'stat-danger' : 'stat-good' ?>"><?= $pct ?>%</span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Fee Balance</span>
                <span class="stat-value">₹<?= number_format(max($balance, 0), 2) ?></span>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
