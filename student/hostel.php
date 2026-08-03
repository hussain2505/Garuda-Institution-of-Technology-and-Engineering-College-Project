<?php
require_once __DIR__ . '/../includes/auth-check.php';
requireRole('Student');

$stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();
if (!$student) die("Student profile not found.");
$studentId = $student['student_id'];

$alloc = $pdo->prepare(
    "SELECT ha.*, hr.room_number, hr.capacity, h.hostel_name, h.hostel_type
     FROM hostel_allocations ha
     JOIN hostel_rooms hr ON hr.room_id = ha.room_id
     JOIN hostels h ON h.hostel_id = hr.hostel_id
     WHERE ha.student_id = ? AND ha.status = 'active'"
);
$alloc->execute([$studentId]);
$allocation = $alloc->fetch();

$roommates = [];
if ($allocation) {
    $rm = $pdo->prepare(
        "SELECT s.roll_number, s.first_name, s.last_name
         FROM hostel_allocations ha
         JOIN students s ON s.student_id = ha.student_id
         WHERE ha.room_id = ? AND ha.status = 'active' AND ha.student_id != ?"
    );
    $rm->execute([$allocation['room_id'], $studentId]);
    $roommates = $rm->fetchAll();
}

$pageTitle = APP_NAME . ' | My Hostel';
include __DIR__ . '/../includes/header.php';
?>

<h2>My Hostel</h2>

<?php if ($allocation): ?>
    <div class="card">
        <h3><?= e($allocation['hostel_name']) ?> — Room <?= e($allocation['room_number']) ?></h3>
        <p class="muted">Type: <?= e(ucfirst($allocation['hostel_type'])) ?> · Allocated since <?= e(date('d M Y', strtotime($allocation['allocated_date']))) ?></p>

        <h4>Roommates</h4>
        <?php if ($roommates): ?>
            <ul class="plain-list">
                <?php foreach ($roommates as $r): ?>
                    <li><?= e($r['roll_number']) ?> - <?= e($r['first_name']) ?> <?= e($r['last_name']) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="muted">No roommates currently allocated to this room.</p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="card">
        <p class="muted">You don't have a hostel room allocated yet. Please contact the hostel administration office.</p>
    </div>
<?php endif; ?>

<a href="<?= BASE_URL ?>/student/dashboard.php" class="btn btn-outline">&larr; Back to Dashboard</a>

<?php include __DIR__ . '/../includes/footer.php'; ?>
