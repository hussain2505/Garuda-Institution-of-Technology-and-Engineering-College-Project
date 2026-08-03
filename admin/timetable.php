<?php
require_once __DIR__ . '/../includes/auth-check.php';
requireRole('Super Admin', 'Admin');

$departments = $pdo->query("SELECT department_id, department_name FROM departments ORDER BY department_name")->fetchAll();
$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_slot') {
        $deptId = (int)$_POST['department_id'];
        $semester = (int)$_POST['semester'];
        $section = trim($_POST['section']);
        $day = $_POST['day_of_week'];
        $period = (int)$_POST['period_number'];
        $start = $_POST['start_time'];
        $end = $_POST['end_time'];
        $subjectId = (int)$_POST['subject_id'];
        $facultyId = $_POST['faculty_id'] !== '' ? (int)$_POST['faculty_id'] : null;

        try {
            $pdo->prepare(
                "INSERT INTO timetable_slots (department_id, semester, section, day_of_week, period_number, start_time, end_time, subject_id, faculty_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE subject_id = VALUES(subject_id), faculty_id = VALUES(faculty_id),
                     start_time = VALUES(start_time), end_time = VALUES(end_time)"
            )->execute([$deptId, $semester, $section, $day, $period, $start, $end, $subjectId, $facultyId]);
            logAudit($pdo, 'timetable_slot_saved', 'timetable', $deptId, "$day period $period, dept #$deptId sem $semester sec $section");
            setFlash('success', 'Timetable slot saved.');
        } catch (PDOException $e) {
            setFlash('error', 'Could not save that slot. Check the values and try again.');
        }
    }

    if ($action === 'delete_slot') {
        $slotId = (int)$_POST['slot_id'];
        $pdo->prepare("DELETE FROM timetable_slots WHERE slot_id = ?")->execute([$slotId]);
        setFlash('success', 'Slot removed.');
    }

    redirect(BASE_URL . '/admin/timetable.php?department_id=' . ($_POST['department_id'] ?? '') . '&semester=' . ($_POST['semester'] ?? '') . '&section=' . urlencode($_POST['section'] ?? ''));
}

$deptId = (int)($_GET['department_id'] ?? ($departments[0]['department_id'] ?? 0));
$semester = (int)($_GET['semester'] ?? 3);
$section = $_GET['section'] ?? 'A';

$subjects = $pdo->prepare("SELECT subject_id, subject_code, subject_name FROM subjects WHERE department_id = ? ORDER BY subject_name");
$subjects->execute([$deptId]);
$subjectRows = $subjects->fetchAll();

$facultyList = $pdo->prepare("SELECT faculty_id, first_name, last_name FROM faculty WHERE department_id = ? ORDER BY first_name");
$facultyList->execute([$deptId]);
$facultyRows = $facultyList->fetchAll();

$slots = $pdo->prepare(
    "SELECT ts.*, sub.subject_name, f.first_name, f.last_name
     FROM timetable_slots ts
     JOIN subjects sub ON sub.subject_id = ts.subject_id
     LEFT JOIN faculty f ON f.faculty_id = ts.faculty_id
     WHERE ts.department_id = ? AND ts.semester = ? AND ts.section = ?"
);
$slots->execute([$deptId, $semester, $section]);
$slotRows = $slots->fetchAll();

$grid = [];
foreach ($slotRows as $s) {
    $grid[$s['day_of_week']][$s['period_number']] = $s;
}
$maxPeriod = max(1, ...array_map(fn($s) => (int)$s['period_number'], $slotRows ?: [['period_number' => 6]]));

$pageTitle = APP_NAME . ' | Timetable Management';
include __DIR__ . '/../includes/header.php';
?>

<h2>Timetable Management</h2>

<form method="get" class="filter-form">
    <label>Department</label>
    <select name="department_id" onchange="this.form.submit()">
        <?php foreach ($departments as $d): ?>
            <option value="<?= (int)$d['department_id'] ?>" <?= $d['department_id'] == $deptId ? 'selected' : '' ?>><?= e($d['department_name']) ?></option>
        <?php endforeach; ?>
    </select>
    <label>Semester</label>
    <input type="number" name="semester" min="1" max="8" value="<?= (int)$semester ?>" style="max-width:80px" onchange="this.form.submit()">
    <label>Section</label>
    <input type="text" name="section" value="<?= e($section) ?>" style="max-width:80px" onchange="this.form.submit()">
</form>

<div class="card">
    <h3>Add / Update a Slot</h3>
    <form method="post">
        <?php csrfField(); ?>
        <input type="hidden" name="action" value="add_slot">
        <input type="hidden" name="department_id" value="<?= (int)$deptId ?>">
        <input type="hidden" name="semester" value="<?= (int)$semester ?>">
        <input type="hidden" name="section" value="<?= e($section) ?>">

        <div class="form-row">
            <div>
                <label>Day</label>
                <select name="day_of_week" required>
                    <?php foreach ($days as $d): ?><option><?= $d ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label>Period Number</label><input type="number" name="period_number" min="1" max="8" value="1" required></div>
        </div>
        <div class="form-row">
            <div><label>Start Time</label><input type="time" name="start_time" required></div>
            <div><label>End Time</label><input type="time" name="end_time" required></div>
        </div>
        <div class="form-row">
            <div>
                <label>Subject</label>
                <select name="subject_id" required>
                    <?php foreach ($subjectRows as $s): ?>
                        <option value="<?= (int)$s['subject_id'] ?>"><?= e($s['subject_code']) ?> - <?= e($s['subject_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Faculty (optional)</label>
                <select name="faculty_id">
                    <option value="">-- Not assigned --</option>
                    <?php foreach ($facultyRows as $f): ?>
                        <option value="<?= (int)$f['faculty_id'] ?>"><?= e($f['first_name']) ?> <?= e($f['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Save Slot</button>
        <p class="muted small">Saving a slot for the same day/period again will update it (no duplicates).</p>
    </form>
</div>

<div class="card">
    <h3>Weekly Timetable — Semester <?= (int)$semester ?>, Section <?= e($section) ?></h3>
    <table class="timetable-grid">
        <tr>
            <th>Period</th>
            <?php foreach ($days as $d): ?><th><?= $d ?></th><?php endforeach; ?>
        </tr>
        <?php for ($p = 1; $p <= $maxPeriod; $p++): ?>
            <tr>
                <td><strong>P<?= $p ?></strong></td>
                <?php foreach ($days as $d):
                    $slot = $grid[$d][$p] ?? null; ?>
                    <td>
                        <?php if ($slot): ?>
                            <div class="timetable-cell-subject"><?= e($slot['subject_name']) ?></div>
                            <div class="timetable-cell-faculty"><?= e($slot['first_name'] ?? '') ?> <?= e($slot['last_name'] ?? '') ?></div>
                            <div class="timetable-cell-faculty"><?= e(substr($slot['start_time'],0,5)) ?>-<?= e(substr($slot['end_time'],0,5)) ?></div>
                            <form method="post" style="margin-top:4px">
                                <?php csrfField(); ?>
                                <input type="hidden" name="action" value="delete_slot">
                                <input type="hidden" name="slot_id" value="<?= (int)$slot['slot_id'] ?>">
                                <input type="hidden" name="department_id" value="<?= (int)$deptId ?>">
                                <input type="hidden" name="semester" value="<?= (int)$semester ?>">
                                <input type="hidden" name="section" value="<?= e($section) ?>">
                                <button type="submit" class="btn btn-sm btn-outline" onclick="return confirm('Remove this slot?')">&times;</button>
                            </form>
                        <?php else: ?>
                            <span class="timetable-cell-empty">&mdash;</span>
                        <?php endif; ?>
                    </td>
                <?php endforeach; ?>
            </tr>
        <?php endfor; ?>
    </table>
</div>

<a href="<?= BASE_URL ?>/admin/dashboard.php" class="btn btn-outline">&larr; Back to Dashboard</a>

<?php include __DIR__ . '/../includes/footer.php'; ?>
