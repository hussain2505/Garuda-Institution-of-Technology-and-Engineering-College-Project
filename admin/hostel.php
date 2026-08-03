<?php
require_once __DIR__ . '/../includes/auth-check.php';
requireRole('Super Admin', 'Admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_hostel') {
        $name = trim($_POST['hostel_name'] ?? '');
        $type = $_POST['hostel_type'] ?? 'other';
        $capacity = (int)($_POST['capacity'] ?? 0);
        if ($name === '') {
            setFlash('error', 'Please enter a hostel name.');
        } else {
            $pdo->prepare("INSERT INTO hostels (hostel_name, hostel_type, capacity) VALUES (?, ?, ?)")
                ->execute([$name, $type, $capacity]);
            logAudit($pdo, 'hostel_added', 'hostel', $pdo->lastInsertId(), $name);
            setFlash('success', "Hostel \"$name\" added.");
        }
    }

    if ($action === 'add_room') {
        $hostelId = (int)$_POST['hostel_id'];
        $roomNumber = trim($_POST['room_number'] ?? '');
        $capacity = max(1, (int)($_POST['capacity'] ?? 1));
        if (!$hostelId || $roomNumber === '') {
            setFlash('error', 'Please select a hostel and enter a room number.');
        } else {
            try {
                $pdo->prepare("INSERT INTO hostel_rooms (hostel_id, room_number, capacity) VALUES (?, ?, ?)")
                    ->execute([$hostelId, $roomNumber, $capacity]);
                logAudit($pdo, 'hostel_room_added', 'hostel', $pdo->lastInsertId(), "Room $roomNumber");
                setFlash('success', "Room $roomNumber added.");
            } catch (PDOException $e) {
                setFlash('error', 'That room number already exists in this hostel.');
            }
        }
    }

    if ($action === 'allocate') {
        $roomId = (int)$_POST['room_id'];
        $studentId = (int)$_POST['student_id'];

        $room = $pdo->prepare("SELECT * FROM hostel_rooms WHERE room_id = ?");
        $room->execute([$roomId]);
        $room = $room->fetch();

        if (!$room || $room['occupied_beds'] >= $room['capacity']) {
            setFlash('error', 'That room is full or does not exist.');
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare(
                    "INSERT INTO hostel_allocations (student_id, room_id, allocated_date, status) VALUES (?, ?, CURDATE(), 'active')"
                )->execute([$studentId, $roomId]);
                $pdo->prepare("UPDATE hostel_rooms SET occupied_beds = occupied_beds + 1, status = IF(occupied_beds + 1 >= capacity, 'full', 'available') WHERE room_id = ?")
                    ->execute([$roomId]);
                $pdo->commit();
                logAudit($pdo, 'hostel_allocated', 'hostel', $roomId, "Allocated student #$studentId");
                setFlash('success', 'Room allocated.');
            } catch (Exception $ex) {
                $pdo->rollBack();
                setFlash('error', 'Could not allocate. The student may already have an active allocation.');
            }
        }
    }

    if ($action === 'vacate') {
        $allocationId = (int)$_POST['allocation_id'];
        $alloc = $pdo->prepare("SELECT * FROM hostel_allocations WHERE allocation_id = ? AND status = 'active'");
        $alloc->execute([$allocationId]);
        $alloc = $alloc->fetch();

        if ($alloc) {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE hostel_allocations SET status = 'vacated', vacated_date = CURDATE() WHERE allocation_id = ?")
                    ->execute([$allocationId]);
                $pdo->prepare("UPDATE hostel_rooms SET occupied_beds = GREATEST(0, occupied_beds - 1), status = 'available' WHERE room_id = ?")
                    ->execute([$alloc['room_id']]);
                $pdo->commit();
                setFlash('success', 'Allocation vacated.');
            } catch (Exception $ex) {
                $pdo->rollBack();
                setFlash('error', 'Could not process vacate.');
            }
        }
    }

    redirect(BASE_URL . '/admin/hostel.php');
}

$hostels = $pdo->query("SELECT * FROM hostels ORDER BY hostel_name")->fetchAll();
$rooms = $pdo->query(
    "SELECT hr.*, h.hostel_name FROM hostel_rooms hr JOIN hostels h ON h.hostel_id = hr.hostel_id ORDER BY h.hostel_name, hr.room_number"
)->fetchAll();
$students = $pdo->query("SELECT student_id, roll_number, first_name, last_name FROM students WHERE status = 'active' ORDER BY roll_number")->fetchAll();

$allocations = $pdo->query(
    "SELECT ha.*, s.roll_number, s.first_name, s.last_name, hr.room_number, h.hostel_name
     FROM hostel_allocations ha
     JOIN students s ON s.student_id = ha.student_id
     JOIN hostel_rooms hr ON hr.room_id = ha.room_id
     JOIN hostels h ON h.hostel_id = hr.hostel_id
     WHERE ha.status = 'active'
     ORDER BY h.hostel_name, hr.room_number"
)->fetchAll();

$pageTitle = APP_NAME . ' | Hostel Management';
include __DIR__ . '/../includes/header.php';
?>

<h2>Hostel Management</h2>

<div class="two-col">
    <div class="card">
        <h3>Add a Hostel</h3>
        <form method="post">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="add_hostel">
            <label>Hostel Name</label>
            <input type="text" name="hostel_name" required>
            <div class="form-row">
                <div>
                    <label>Type</label>
                    <select name="hostel_type">
                        <option value="boys">Boys</option>
                        <option value="girls">Girls</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div><label>Capacity</label><input type="number" name="capacity" min="0" value="100"></div>
            </div>
            <button type="submit" class="btn btn-primary">Add Hostel</button>
        </form>
    </div>

    <div class="card">
        <h3>Add a Room</h3>
        <form method="post">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="add_room">
            <label>Hostel</label>
            <select name="hostel_id" required>
                <option value="">-- Select --</option>
                <?php foreach ($hostels as $h): ?>
                    <option value="<?= (int)$h['hostel_id'] ?>"><?= e($h['hostel_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="form-row">
                <div><label>Room Number</label><input type="text" name="room_number" required></div>
                <div><label>Bed Capacity</label><input type="number" name="capacity" min="1" value="2"></div>
            </div>
            <button type="submit" class="btn btn-primary">Add Room</button>
        </form>
    </div>
</div>

<div class="card">
    <h3>Allocate a Student to a Room</h3>
    <form method="post">
        <?php csrfField(); ?>
        <input type="hidden" name="action" value="allocate">
        <div class="form-row">
            <div>
                <label>Room</label>
                <select name="room_id" required>
                    <option value="">-- Select --</option>
                    <?php foreach ($rooms as $r): ?>
                        <option value="<?= (int)$r['room_id'] ?>" <?= $r['status'] === 'full' ? 'disabled' : '' ?>>
                            <?= e($r['hostel_name']) ?> - Room <?= e($r['room_number']) ?> (<?= (int)$r['occupied_beds'] ?>/<?= (int)$r['capacity'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Student</label>
                <select name="student_id" required>
                    <option value="">-- Select --</option>
                    <?php foreach ($students as $s): ?>
                        <option value="<?= (int)$s['student_id'] ?>"><?= e($s['roll_number']) ?> - <?= e($s['first_name']) ?> <?= e($s['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Allocate</button>
    </form>
</div>

<div class="card">
    <h3>Current Allocations</h3>
    <table class="data-table">
        <tr><th>Student</th><th>Hostel</th><th>Room</th><th>Since</th><th></th></tr>
        <?php foreach ($allocations as $a): ?>
            <tr>
                <td><?= e($a['roll_number']) ?> - <?= e($a['first_name']) ?> <?= e($a['last_name']) ?></td>
                <td><?= e($a['hostel_name']) ?></td>
                <td><?= e($a['room_number']) ?></td>
                <td><?= e(date('d M Y', strtotime($a['allocated_date']))) ?></td>
                <td>
                    <form method="post">
                        <?php csrfField(); ?>
                        <input type="hidden" name="action" value="vacate">
                        <input type="hidden" name="allocation_id" value="<?= (int)$a['allocation_id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline" onclick="return confirm('Vacate this allocation?')">Vacate</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$allocations): ?><tr><td colspan="5" class="muted">No active allocations.</td></tr><?php endif; ?>
    </table>
</div>

<a href="<?= BASE_URL ?>/admin/dashboard.php" class="btn btn-outline">&larr; Back to Dashboard</a>

<?php include __DIR__ . '/../includes/footer.php'; ?>
