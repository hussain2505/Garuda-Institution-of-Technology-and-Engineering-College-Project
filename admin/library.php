<?php
require_once __DIR__ . '/../includes/auth-check.php';
requireRole('Super Admin', 'Admin');

$FINE_PER_DAY = 2.00; // ₹2/day late fine
$LOAN_DAYS = 14;

// ---------- Handle actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_book') {
        $title = trim($_POST['title'] ?? '');
        $author = trim($_POST['author'] ?? '');
        $isbn = trim($_POST['isbn'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $copies = max(0, (int)($_POST['total_copies'] ?? 0));
        $shelf = trim($_POST['shelf_location'] ?? '');

        if ($title === '' || $copies <= 0) {
            setFlash('error', 'Please enter a title and at least 1 copy.');
        } else {
            $pdo->prepare(
                "INSERT INTO books (isbn, title, author, category, total_copies, available_copies, shelf_location)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            )->execute([$isbn, $title, $author, $category, $copies, $copies, $shelf]);
            logAudit($pdo, 'book_added', 'library', $pdo->lastInsertId(), $title);
            setFlash('success', "Book \"$title\" added with $copies copies.");
        }
    }

    if ($action === 'issue_book') {
        $bookId = (int)$_POST['book_id'];
        $studentId = (int)$_POST['student_id'];

        $book = $pdo->prepare("SELECT * FROM books WHERE book_id = ?");
        $book->execute([$bookId]);
        $book = $book->fetch();

        if (!$book || $book['available_copies'] <= 0) {
            setFlash('error', 'That book has no available copies right now.');
        } else {
            $pdo->beginTransaction();
            try {
                $issued = date('Y-m-d');
                $due = date('Y-m-d', strtotime("+$LOAN_DAYS days"));

                $pdo->prepare(
                    "INSERT INTO book_issues (book_id, student_id, issued_date, due_date, status)
                     VALUES (?, ?, ?, ?, 'issued')"
                )->execute([$bookId, $studentId, $issued, $due]);

                $pdo->prepare("UPDATE books SET available_copies = available_copies - 1 WHERE book_id = ?")
                    ->execute([$bookId]);

                $pdo->commit();
                logAudit($pdo, 'book_issued', 'library', $bookId, "Issued to student #$studentId, due $due");
                setFlash('success', "\"{$book['title']}\" issued. Due back $due.");
            } catch (Exception $ex) {
                $pdo->rollBack();
                setFlash('error', 'Could not issue the book. Please try again.');
            }
        }
    }

    if ($action === 'return_book') {
        $issueId = (int)$_POST['issue_id'];

        $issue = $pdo->prepare("SELECT * FROM book_issues WHERE issue_id = ? AND status = 'issued'");
        $issue->execute([$issueId]);
        $issue = $issue->fetch();

        if (!$issue) {
            setFlash('error', 'That issue record was not found or is already returned.');
        } else {
            $today = new DateTime();
            $due = new DateTime($issue['due_date']);
            $lateDays = max(0, (int)$today->diff($due)->format('%r%a') * -1);
            $fine = $lateDays > 0 ? $lateDays * $FINE_PER_DAY : 0;

            $pdo->beginTransaction();
            try {
                $pdo->prepare(
                    "UPDATE book_issues SET returned_date = CURDATE(), status = 'returned', fine_amount = ? WHERE issue_id = ?"
                )->execute([$fine, $issueId]);

                $pdo->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE book_id = ?")
                    ->execute([$issue['book_id']]);

                $pdo->commit();
                logAudit($pdo, 'book_returned', 'library', $issueId, $fine > 0 ? "Returned, fine ₹$fine" : "Returned on time");
                setFlash('success', $fine > 0
                    ? "Book returned. Late fine: ₹" . number_format($fine, 2)
                    : "Book returned on time — no fine.");
            } catch (Exception $ex) {
                $pdo->rollBack();
                setFlash('error', 'Could not process the return. Please try again.');
            }
        }
    }

    redirect(BASE_URL . '/admin/library.php');
}

// ---------- Data for the page ----------
$books = $pdo->query("SELECT * FROM books ORDER BY title")->fetchAll();

$students = $pdo->query(
    "SELECT student_id, roll_number, first_name, last_name FROM students WHERE status = 'active' ORDER BY roll_number"
)->fetchAll();

$issuedBooks = $pdo->query(
    "SELECT bi.*, b.title, s.roll_number, s.first_name, s.last_name
     FROM book_issues bi
     JOIN books b ON b.book_id = bi.book_id
     JOIN students s ON s.student_id = bi.student_id
     WHERE bi.status = 'issued'
     ORDER BY bi.due_date"
)->fetchAll();

$history = $pdo->query(
    "SELECT bi.*, b.title, s.roll_number, s.first_name, s.last_name
     FROM book_issues bi
     JOIN books b ON b.book_id = bi.book_id
     JOIN students s ON s.student_id = bi.student_id
     WHERE bi.status = 'returned'
     ORDER BY bi.returned_date DESC LIMIT 20"
)->fetchAll();

$pageTitle = APP_NAME . ' | Library Management';
include __DIR__ . '/../includes/header.php';
$today = date('Y-m-d');
?>

<h2>Library Management</h2>

<div class="two-col">
    <div class="card">
        <h3>Add a Book</h3>
        <form method="post">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="add_book">
            <label>Title</label>
            <input type="text" name="title" required>
            <div class="form-row">
                <div><label>Author</label><input type="text" name="author"></div>
                <div><label>Category</label><input type="text" name="category"></div>
            </div>
            <div class="form-row">
                <div><label>ISBN</label><input type="text" name="isbn"></div>
                <div><label>Shelf Location</label><input type="text" name="shelf_location" placeholder="e.g. A1-12"></div>
            </div>
            <label>Total Copies</label>
            <input type="number" name="total_copies" min="1" value="1" style="max-width:120px">
            <button type="submit" class="btn btn-primary">Add Book</button>
        </form>
    </div>

    <div class="card">
        <h3>Issue a Book</h3>
        <form method="post">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="issue_book">
            <label>Book</label>
            <select name="book_id" required>
                <option value="">-- Select a book --</option>
                <?php foreach ($books as $b): ?>
                    <option value="<?= (int)$b['book_id'] ?>" <?= $b['available_copies'] <= 0 ? 'disabled' : '' ?>>
                        <?= e($b['title']) ?> (<?= (int)$b['available_copies'] ?>/<?= (int)$b['total_copies'] ?> available)
                    </option>
                <?php endforeach; ?>
            </select>
            <label>Student</label>
            <select name="student_id" required>
                <option value="">-- Select a student --</option>
                <?php foreach ($students as $s): ?>
                    <option value="<?= (int)$s['student_id'] ?>">
                        <?= e($s['roll_number']) ?> - <?= e($s['first_name']) ?> <?= e($s['last_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Issue Book (14-day loan)</button>
        </form>
    </div>
</div>

<div class="card">
    <h3>All Books</h3>
    <table class="data-table">
        <tr><th>Title</th><th>Author</th><th>Category</th><th>Available</th><th>Total</th><th>Shelf</th></tr>
        <?php foreach ($books as $b): ?>
            <tr>
                <td><?= e($b['title']) ?></td>
                <td><?= e($b['author']) ?></td>
                <td><?= e($b['category']) ?></td>
                <td><?= (int)$b['available_copies'] ?></td>
                <td><?= (int)$b['total_copies'] ?></td>
                <td><?= e($b['shelf_location']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$books): ?><tr><td colspan="6" class="muted">No books yet.</td></tr><?php endif; ?>
    </table>
</div>

<div class="card">
    <h3>Currently Issued</h3>
    <table class="data-table">
        <tr><th>Book</th><th>Student</th><th>Issued</th><th>Due</th><th>Status</th><th></th></tr>
        <?php foreach ($issuedBooks as $i):
            $overdue = $i['due_date'] < $today; ?>
            <tr>
                <td><?= e($i['title']) ?></td>
                <td><?= e($i['roll_number']) ?> - <?= e($i['first_name']) ?> <?= e($i['last_name']) ?></td>
                <td><?= e(date('d M Y', strtotime($i['issued_date']))) ?></td>
                <td><?= e(date('d M Y', strtotime($i['due_date']))) ?></td>
                <td><span class="badge badge-<?= $overdue ? 'overdue' : 'active' ?>"><?= $overdue ? 'Overdue' : 'On Time' ?></span></td>
                <td>
                    <form method="post" style="display:inline">
                        <?php csrfField(); ?>
                        <input type="hidden" name="action" value="return_book">
                        <input type="hidden" name="issue_id" value="<?= (int)$i['issue_id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline" onclick="return confirm('Mark this book as returned?')">Return</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$issuedBooks): ?><tr><td colspan="6" class="muted">No books currently issued.</td></tr><?php endif; ?>
    </table>
</div>

<div class="card">
    <h3>Recent Returns</h3>
    <table class="data-table">
        <tr><th>Book</th><th>Student</th><th>Returned</th><th>Fine</th></tr>
        <?php foreach ($history as $h): ?>
            <tr>
                <td><?= e($h['title']) ?></td>
                <td><?= e($h['roll_number']) ?> - <?= e($h['first_name']) ?> <?= e($h['last_name']) ?></td>
                <td><?= e(date('d M Y', strtotime($h['returned_date']))) ?></td>
                <td><?= $h['fine_amount'] > 0 ? '₹' . number_format($h['fine_amount'], 2) : '&mdash;' ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$history): ?><tr><td colspan="4" class="muted">No returns yet.</td></tr><?php endif; ?>
    </table>
</div>

<a href="<?= BASE_URL ?>/admin/dashboard.php" class="btn btn-outline">&larr; Back to Dashboard</a>

<?php include __DIR__ . '/../includes/footer.php'; ?>
