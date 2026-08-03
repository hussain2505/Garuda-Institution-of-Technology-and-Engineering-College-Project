<?php
require_once __DIR__ . '/../includes/auth-check.php';
requireRole('Super Admin', 'Admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'assign_fee') {
        $feeStructureId = (int)$_POST['fee_structure_id'];
        $target = $_POST['assign_to'] ?? 'one'; // 'one' or 'all'

        $fs = $pdo->prepare("SELECT * FROM fee_structures WHERE fee_structure_id = ?");
        $fs->execute([$feeStructureId]);
        $fs = $fs->fetch();

        if (!$fs) {
            setFlash('error', 'Please select a valid fee.');
        } else {
            if ($target === 'all') {
                $studentIds = $pdo->query("SELECT student_id FROM students WHERE status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
            } else {
                $studentIds = [(int)$_POST['student_id']];
            }

            $insert = $pdo->prepare(
                "INSERT INTO student_fees (student_id, fee_structure_id, amount_due, due_date, status)
                 SELECT ?, ?, ?, ?, 'pending'
                 WHERE NOT EXISTS (
                    SELECT 1 FROM student_fees WHERE student_id = ? AND fee_structure_id = ?
                 )"
            );
            $count = 0;
            foreach ($studentIds as $sid) {
                if (!$sid) continue;
                $insert->execute([$sid, $feeStructureId, $fs['amount'], $fs['due_date'], $sid, $feeStructureId]);
                $count += $insert->rowCount();
            }
            logAudit($pdo, 'fee_assigned', 'fees', $feeStructureId, "Assigned to $count student(s)");
            setFlash('success', "Fee assigned to $count student(s). (Students who already had this fee were skipped.)");
        }
    }

    if ($action === 'add_fee_structure') {
        $name = trim($_POST['fee_name'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $dueDate = $_POST['due_date'] ?? null;
        $academicYearId = (int)($_POST['academic_year_id'] ?? 0);

        if ($name === '' || $amount <= 0 || $academicYearId <= 0) {
            setFlash('error', 'Please fill in the fee name, a positive amount, and academic year.');
        } else {
            $pdo->prepare(
                "INSERT INTO fee_structures (academic_year_id, fee_name, amount, due_date) VALUES (?, ?, ?, ?)"
            )->execute([$academicYearId, $name, $amount, $dueDate ?: null]);
            logAudit($pdo, 'fee_structure_created', 'fees', $pdo->lastInsertId(), $name);
            setFlash('success', "Fee type \"$name\" created.");
        }
    }

    if ($action === 'record_payment') {
        $studentFeeId = (int)$_POST['student_fee_id'];
        $amount = (float)($_POST['amount'] ?? 0);
        $method = $_POST['payment_method'] ?? 'cash';

        $fee = $pdo->prepare("SELECT * FROM student_fees WHERE student_fee_id = ?");
        $fee->execute([$studentFeeId]);
        $fee = $fee->fetch();

        $balance = $fee ? ($fee['amount_due'] - $fee['amount_paid']) : 0;

        if (!$fee || $amount <= 0 || $amount > $balance + 0.01) {
            setFlash('error', 'Invalid payment amount for that fee record.');
        } else {
            $pdo->beginTransaction();
            try {
                $ref = 'GITEC-ADM-' . strtoupper(bin2hex(random_bytes(5)));
                $pdo->prepare(
                    "INSERT INTO payments (student_fee_id, transaction_reference, amount, payment_method, payment_status, paid_at)
                     VALUES (?, ?, ?, ?, 'success', NOW())"
                )->execute([$studentFeeId, $ref, $amount, $method]);

                $newPaid = $fee['amount_paid'] + $amount;
                $newStatus = $newPaid >= $fee['amount_due'] ? 'paid' : 'partial';
                $pdo->prepare(
                    "UPDATE student_fees SET amount_paid = ?, status = ? WHERE student_fee_id = ?"
                )->execute([$newPaid, $newStatus, $studentFeeId]);

                $pdo->commit();
                logAudit($pdo, 'fee_payment_recorded', 'fees', $studentFeeId, "₹$amount via $method (ref $ref)");
                setFlash('success', "Payment of ₹" . number_format($amount, 2) . " recorded.");
            } catch (Exception $ex) {
                $pdo->rollBack();
                setFlash('error', 'Could not record the payment.');
            }
        }
    }

    redirect(BASE_URL . '/admin/fees.php');
}

$feeStructures = $pdo->query("SELECT * FROM fee_structures ORDER BY due_date")->fetchAll();
$academicYears = $pdo->query("SELECT * FROM academic_years ORDER BY start_date DESC")->fetchAll();
$students = $pdo->query(
    "SELECT student_id, roll_number, first_name, last_name FROM students WHERE status = 'active' ORDER BY roll_number"
)->fetchAll();

$search = trim($_GET['q'] ?? '');
$sql = "SELECT sf.*, fs.fee_name, s.roll_number, s.first_name, s.last_name
        FROM student_fees sf
        JOIN fee_structures fs ON fs.fee_structure_id = sf.fee_structure_id
        JOIN students s ON s.student_id = sf.student_id";
$params = [];
if ($search !== '') {
    $sql .= " WHERE s.roll_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?";
    $like = "%$search%";
    $params = [$like, $like, $like];
}
$sql .= " ORDER BY sf.due_date DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$studentFees = $stmt->fetchAll();

$pageTitle = APP_NAME . ' | Fee Management';
include __DIR__ . '/../includes/header.php';
?>

<h2>Fee Management</h2>

<div class="two-col">
    <div class="card">
        <h3>Create a Fee Type</h3>
        <form method="post">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="add_fee_structure">
            <label>Fee Name</label>
            <input type="text" name="fee_name" required placeholder="e.g. Semester 2 Tuition Fee">
            <div class="form-row">
                <div><label>Amount (₹)</label><input type="number" name="amount" min="0" step="0.01" required></div>
                <div><label>Due Date</label><input type="date" name="due_date"></div>
            </div>
            <label>Academic Year</label>
            <select name="academic_year_id" required>
                <?php foreach ($academicYears as $ay): ?>
                    <option value="<?= (int)$ay['academic_year_id'] ?>"><?= e($ay['year_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Create Fee Type</button>
        </form>
    </div>

    <div class="card">
        <h3>Assign a Fee</h3>
        <form method="post" id="assignFeeForm">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="assign_fee">
            <label>Fee</label>
            <select name="fee_structure_id" required>
                <option value="">-- Select a fee --</option>
                <?php foreach ($feeStructures as $fs): ?>
                    <option value="<?= (int)$fs['fee_structure_id'] ?>">
                        <?= e($fs['fee_name']) ?> — ₹<?= number_format($fs['amount'], 2) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Assign To</label>
            <select name="assign_to" id="assignTo" onchange="document.getElementById('studentPicker').style.display = this.value === 'one' ? 'block' : 'none'">
                <option value="all">All Active Students</option>
                <option value="one">A Specific Student</option>
            </select>

            <div id="studentPicker" style="display:none">
                <label>Student</label>
                <select name="student_id">
                    <option value="">-- Select a student --</option>
                    <?php foreach ($students as $s): ?>
                        <option value="<?= (int)$s['student_id'] ?>">
                            <?= e($s['roll_number']) ?> - <?= e($s['first_name']) ?> <?= e($s['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Assign Fee</button>
            <p class="muted small">Students who already have this fee assigned are skipped automatically.</p>
        </form>
    </div>
</div>

<div class="card">
    <h3>Student Fee Records</h3>
    <form method="get" class="filter-form">
        <input type="text" name="q" placeholder="Search by roll number or name" value="<?= e($search) ?>">
        <button type="submit" class="btn btn-outline">Search</button>
    </form>

    <table class="data-table">
        <tr><th>Student</th><th>Fee</th><th>Due</th><th>Amount Due</th><th>Paid</th><th>Status</th><th>Record Payment</th></tr>
        <?php foreach ($studentFees as $f):
            $balance = round($f['amount_due'] - $f['amount_paid'], 2); ?>
            <tr>
                <td><?= e($f['roll_number']) ?> - <?= e($f['first_name']) ?> <?= e($f['last_name']) ?></td>
                <td><?= e($f['fee_name']) ?></td>
                <td><?= e($f['due_date'] ? date('d M Y', strtotime($f['due_date'])) : '-') ?></td>
                <td>₹<?= number_format($f['amount_due'], 2) ?></td>
                <td>₹<?= number_format($f['amount_paid'], 2) ?></td>
                <td><span class="badge badge-<?= e($f['status']) ?>"><?= e(ucfirst($f['status'])) ?></span></td>
                <td>
                    <?php if ($balance > 0.01): ?>
                        <form method="post" class="inline-payment-form">
                            <?php csrfField(); ?>
                            <input type="hidden" name="action" value="record_payment">
                            <input type="hidden" name="student_fee_id" value="<?= (int)$f['student_fee_id'] ?>">
                            <input type="number" name="amount" step="any" min="0.01" max="<?= $balance ?>"
                                   value="<?= $balance ?>" style="width:110px;display:inline-block">
                            <select name="payment_method" style="width:110px;display:inline-block">
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="upi">UPI</option>
                                <option value="bank_transfer">Bank</option>
                            </select>
                            <button type="submit" class="btn btn-sm btn-primary">Record</button>
                        </form>
                    <?php else: ?>
                        &mdash;
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$studentFees): ?><tr><td colspan="7" class="muted">No fee records found.</td></tr><?php endif; ?>
    </table>
</div>

<a href="<?= BASE_URL ?>/admin/dashboard.php" class="btn btn-outline">&larr; Back to Dashboard</a>

<?php include __DIR__ . '/../includes/footer.php'; ?>
