<?php
require_once __DIR__ . '/../includes/auth-check.php';
requireRole('Student');

$stmt = $pdo->prepare("SELECT student_id, first_name, last_name FROM students WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();
if (!$student) die("Student profile not found.");
$studentId = $student['student_id'];

$studentFeeId = (int)($_GET['id'] ?? $_POST['student_fee_id'] ?? 0);

$feeStmt = $pdo->prepare(
    "SELECT sf.*, fs.fee_name
     FROM student_fees sf JOIN fee_structures fs ON fs.fee_structure_id = sf.fee_structure_id
     WHERE sf.student_fee_id = ? AND sf.student_id = ?"
);
$feeStmt->execute([$studentFeeId, $studentId]);
$fee = $feeStmt->fetch();

if (!$fee) {
    redirect(BASE_URL . '/student/fees.php');
}

$balance = round($fee['amount_due'] - $fee['amount_paid'], 2);
if ($balance <= 0) {
    redirect(BASE_URL . '/student/fees.php');
}

// ---------- Confirm payment ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $pdo->beginTransaction();
    try {
        $ref = 'GITEC-' . strtoupper(bin2hex(random_bytes(6)));

        $pdo->prepare(
            "INSERT INTO payments (student_fee_id, transaction_reference, amount, payment_method, payment_status, paid_at)
             VALUES (?, ?, ?, 'online', 'success', NOW())"
        )->execute([$studentFeeId, $ref, $balance]);

        $newPaid = $fee['amount_paid'] + $balance;
        $pdo->prepare(
            "UPDATE student_fees SET amount_paid = ?, status = 'paid' WHERE student_fee_id = ?"
        )->execute([$newPaid, $studentFeeId]);

        $pdo->commit();
        logAudit($pdo, 'fee_payment', 'fees', $studentFeeId, "Paid ₹$balance online (ref $ref)");
        setFlash('success', "Payment of ₹" . number_format($balance, 2) . " successful. Reference: $ref");
    } catch (Exception $ex) {
        $pdo->rollBack();
        setFlash('error', 'Payment failed. Please try again.');
    }
    redirect(BASE_URL . '/student/fees.php');
}

// Build a UPI-style payment string and let a public QR API render it as an image.
// This is a simulated payment for demo purposes — no real money moves.
$payeeVpa = 'gitec.fees@simulatedbank';
$upiString = "upi://pay?pa=" . urlencode($payeeVpa)
    . "&pn=" . urlencode('GITEC College')
    . "&am=" . urlencode(number_format($balance, 2, '.', ''))
    . "&cu=INR&tn=" . urlencode($fee['fee_name'] . ' - ' . $student['first_name']);

$qrImageUrl = "https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=" . urlencode($upiString);

$pageTitle = APP_NAME . ' | Pay Online';
include __DIR__ . '/../includes/header.php';
?>

<h2>Pay Online</h2>

<div class="card auth-card" style="max-width:420px;margin:0 auto;text-align:center">
    <h3><?= e($fee['fee_name']) ?></h3>
    <p class="muted">Amount to Pay</p>
    <p style="font-size:2rem;font-weight:800;color:var(--navy);margin:0 0 16px">₹<?= number_format($balance, 2) ?></p>

    <img src="<?= e($qrImageUrl) ?>" alt="Scan to pay QR code" style="border:1px solid var(--border);border-radius:8px;margin-bottom:16px" width="220" height="220">

    <p class="muted small">Scan with any UPI app, or use the demo confirm button below
        (this is a simulated payment — no real transaction takes place).</p>

    <form method="post">
        <?php csrfField(); ?>
        <input type="hidden" name="student_fee_id" value="<?= (int)$studentFeeId ?>">
        <button type="submit" class="btn btn-primary btn-block">I've Completed the Payment</button>
    </form>
    <a href="<?= BASE_URL ?>/student/fees.php" class="btn btn-outline btn-block" style="margin-top:8px">Cancel</a>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
