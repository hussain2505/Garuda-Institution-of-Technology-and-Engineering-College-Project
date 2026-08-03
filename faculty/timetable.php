<?php
require_once __DIR__ . '/../includes/auth-check.php';
requireRole('Faculty');

$stmt = $pdo->prepare("SELECT faculty_id FROM faculty WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$faculty = $stmt->fetch();
if (!$faculty) die("Faculty profile not found.");

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

$slots = $pdo->prepare(
    "SELECT ts.*, sub.subject_name, d.department_name
     FROM timetable_slots ts
     JOIN subjects sub ON sub.subject_id = ts.subject_id
     JOIN departments d ON d.department_id = ts.department_id
     WHERE ts.faculty_id = ?
     ORDER BY FIELD(ts.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), ts.period_number"
);
$slots->execute([$faculty['faculty_id']]);
$slotRows = $slots->fetchAll();

$grid = [];
foreach ($slotRows as $s) { $grid[$s['day_of_week']][$s['period_number']] = $s; }
$maxPeriod = max(1, ...array_map(fn($s) => (int)$s['period_number'], $slotRows ?: [['period_number' => 6]]));

$pageTitle = APP_NAME . ' | My Teaching Timetable';
include __DIR__ . '/../includes/header.php';
?>

<h2>My Teaching Timetable</h2>

<div class="card">
    <table class="timetable-grid">
        <tr><th>Period</th><?php foreach ($days as $d): ?><th><?= $d ?></th><?php endforeach; ?></tr>
        <?php for ($p = 1; $p <= $maxPeriod; $p++): ?>
            <tr>
                <td><strong>P<?= $p ?></strong></td>
                <?php foreach ($days as $d): $slot = $grid[$d][$p] ?? null; ?>
                    <td>
                        <?php if ($slot): ?>
                            <div class="timetable-cell-subject"><?= e($slot['subject_name']) ?></div>
                            <div class="timetable-cell-faculty"><?= e($slot['department_name']) ?></div>
                            <div class="timetable-cell-faculty"><?= e(substr($slot['start_time'],0,5)) ?>-<?= e(substr($slot['end_time'],0,5)) ?></div>
                        <?php else: ?>
                            <span class="timetable-cell-empty">&mdash;</span>
                        <?php endif; ?>
                    </td>
                <?php endforeach; ?>
            </tr>
        <?php endfor; ?>
    </table>
    <?php if (!$slotRows): ?><p class="muted">You have no timetable slots assigned yet.</p><?php endif; ?>
</div>

<a href="<?= BASE_URL ?>/faculty/dashboard.php" class="btn btn-outline">&larr; Back to Dashboard</a>

<?php include __DIR__ . '/../includes/footer.php'; ?>
