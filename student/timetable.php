<?php
require_once __DIR__ . '/../includes/auth-check.php';
requireRole('Student');

$stmt = $pdo->prepare("SELECT department_id, current_semester, section FROM students WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();
if (!$student) die("Student profile not found.");

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

$slots = $pdo->prepare(
    "SELECT ts.*, sub.subject_name, f.first_name, f.last_name
     FROM timetable_slots ts
     JOIN subjects sub ON sub.subject_id = ts.subject_id
     LEFT JOIN faculty f ON f.faculty_id = ts.faculty_id
     WHERE ts.department_id = ? AND ts.semester = ? AND ts.section = ?"
);
$slots->execute([$student['department_id'], $student['current_semester'], $student['section']]);
$slotRows = $slots->fetchAll();

$grid = [];
foreach ($slotRows as $s) { $grid[$s['day_of_week']][$s['period_number']] = $s; }
$maxPeriod = max(1, ...array_map(fn($s) => (int)$s['period_number'], $slotRows ?: [['period_number' => 6]]));

$pageTitle = APP_NAME . ' | My Timetable';
include __DIR__ . '/../includes/header.php';
?>

<h2>My Timetable</h2>
<p class="muted">Semester <?= (int)$student['current_semester'] ?> · Section <?= e($student['section']) ?></p>

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
                            <div class="timetable-cell-faculty"><?= e($slot['first_name'] ?? '') ?> <?= e($slot['last_name'] ?? '') ?></div>
                            <div class="timetable-cell-faculty"><?= e(substr($slot['start_time'],0,5)) ?>-<?= e(substr($slot['end_time'],0,5)) ?></div>
                        <?php else: ?>
                            <span class="timetable-cell-empty">&mdash;</span>
                        <?php endif; ?>
                    </td>
                <?php endforeach; ?>
            </tr>
        <?php endfor; ?>
    </table>
    <?php if (!$slotRows): ?><p class="muted">No timetable has been published for your section yet.</p><?php endif; ?>
</div>

<a href="<?= BASE_URL ?>/student/dashboard.php" class="btn btn-outline">&larr; Back to Dashboard</a>

<?php include __DIR__ . '/../includes/footer.php'; ?>
