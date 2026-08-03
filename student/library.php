<?php
require_once __DIR__ . '/../includes/auth-check.php';
requireRole('Student');

$stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();
if (!$student) die("Student profile not found.");
$studentId = $student['student_id'];

$issued = $pdo->prepare(
    "SELECT bi.*, b.title, b.author
     FROM book_issues bi JOIN books b ON b.book_id = bi.book_id
     WHERE bi.student_id = ? AND bi.status = 'issued'
     ORDER BY bi.due_date"
);
$issued->execute([$studentId]);
$issuedRows = $issued->fetchAll();

$history = $pdo->prepare(
    "SELECT bi.*, b.title, b.author
     FROM book_issues bi JOIN books b ON b.book_id = bi.book_id
     WHERE bi.student_id = ? AND bi.status = 'returned'
     ORDER BY bi.returned_date DESC LIMIT 20"
);
$history->execute([$studentId]);
$historyRows = $history->fetchAll();

$pageTitle = APP_NAME . ' | My Library';
include __DIR__ . '/../includes/header.php';
$today = date('Y-m-d');
?>

<h2>My Library</h2>

<div class="card">
    <h3>Currently Borrowed</h3>
    <table class="data-table">
        <tr><th>Book</th><th>Author</th><th>Issued</th><th>Due</th><th>Status</th></tr>
        <?php foreach ($issuedRows as $r):
            $overdue = $r['due_date'] < $today; ?>
            <tr>
                <td><?= e($r['title']) ?></td>
                <td><?= e($r['author']) ?></td>
                <td><?= e(date('d M Y', strtotime($r['issued_date']))) ?></td>
                <td><?= e(date('d M Y', strtotime($r['due_date']))) ?></td>
                <td><span class="badge badge-<?= $overdue ? 'overdue' : 'active' ?>"><?= $overdue ? 'Overdue' : 'On Time' ?></span></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$issuedRows): ?><tr><td colspan="5" class="muted">You have no books currently borrowed.</td></tr><?php endif; ?>
    </table>
</div>

<div class="card">
    <h3>Borrowing History</h3>
    <table class="data-table">
        <tr><th>Book</th><th>Returned</th><th>Fine</th></tr>
        <?php foreach ($historyRows as $r): ?>
            <tr>
                <td><?= e($r['title']) ?></td>
                <td><?= e(date('d M Y', strtotime($r['returned_date']))) ?></td>
                <td><?= $r['fine_amount'] > 0 ? '₹' . number_format($r['fine_amount'], 2) : '&mdash;' ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$historyRows): ?><tr><td colspan="3" class="muted">No history yet.</td></tr><?php endif; ?>
    </table>
</div>

<a href="<?= BASE_URL ?>/student/dashboard.php" class="btn btn-outline">&larr; Back to Dashboard</a>

<?php include __DIR__ . '/../includes/footer.php'; ?>
