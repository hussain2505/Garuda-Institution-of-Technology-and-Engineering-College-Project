<?php
require_once __DIR__ . '/../includes/auth-check.php';
requireRole('Student');

$stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();
if (!$student) die("Student profile not found.");
$studentId = $student['student_id'];

$fees = $pdo->prepare(
    "SELECT sf.student_fee_id, fs.fee_name, sf.amount_due, sf.amount_paid, sf.due_date, sf.status
     FROM student_fees sf
     JOIN fee_structures fs ON fs.fee_structure_id = sf.fee_structure_id
     WHERE sf.student_id = ?
     ORDER BY sf.due_date"
);
$fees->execute([$studentId]);
$feeRows = $fees->fetchAll();

$payments = $pdo->prepare(
    "SELECT p.transaction_reference, p.amount, p.payment_method, p.payment_status, p.paid_at
     FROM payments p
     JOIN student_fees sf ON sf.student_fee_id = p.student_fee_id
     WHERE sf.student_id = ?
     ORDER BY p.paid_at DESC"
);
$payments->execute([$studentId]);
$paymentRows = $payments->fetchAll();

$pageTitle = APP_NAME . ' | My Fees';
include __DIR__ . '/../includes/header.php';
?>

<h2>Fee Details</h2>

<div class="card">
    <table class="data-table">
        <tr><th>Fee</th><th>Due Date</th><th>Amount Due</th><th>Paid</th><th>Status</th><th></th></tr>
        <?php foreach ($feeRows as $f):
            $balance = $f['amount_due'] - $f['amount_paid']; ?>
            <tr>
                <td><?= e($f['fee_name']) ?></td>
                <td><?= e($f['due_date'] ? date('d M Y', strtotime($f['due_date'])) : '-') ?></td>
                <td>₹<?= number_format($f['amount_due'], 2) ?></td>
                <td>₹<?= number_format($f['amount_paid'], 2) ?></td>
                <td><span class="badge badge-<?= e($f['status']) ?>"><?= e(ucfirst($f['status'])) ?></span></td>
                <td>
                    <?php if ($balance > 0): ?>
                        <a href="<?= BASE_URL ?>/student/pay-online.php?id=<?= (int)$f['student_fee_id'] ?>" class="btn btn-primary btn-sm">
                            Pay ₹<?= number_format($balance, 2) ?>
                        </a>
                    <?php else: ?>
                        &mdash;
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$feeRows): ?>
            <tr><td colspan="6" class="muted">No fee records yet.</td></tr>
        <?php endif; ?>
    </table>
</div>

<div class="card">
    <h3>Payment History</h3>
    <table class="data-table">
        <tr><th>Reference</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th></tr>
        <?php foreach ($paymentRows as $p): ?>
            <tr>
                <td><?= e($p['transaction_reference']) ?></td>
                <td>₹<?= number_format($p['amount'], 2) ?></td>
                <td><?= e(ucfirst($p['payment_method'])) ?></td>
                <td><?= e(ucfirst($p['payment_status'])) ?></td>
                <td><?= e(date('d M Y H:i', strtotime($p['paid_at']))) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$paymentRows): ?>
            <tr><td colspan="5" class="muted">No payments made yet.</td></tr>
        <?php endif; ?>
    </table>
</div>

<a href="<?= BASE_URL ?>/student/dashboard.php" class="btn btn-outline">&larr; Back to Dashboard</a>

<?php include __DIR__ . '/../includes/footer.php'; ?>
