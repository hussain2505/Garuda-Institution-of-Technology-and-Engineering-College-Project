<?php
require_once __DIR__ . '/../includes/auth-check.php';
requireRole('Student');

$stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();
if (!$student) die("Student profile not found.");
$studentId = $student['student_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $category = trim($_POST['category'] ?? '');
    $subject  = trim($_POST['subject'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';

    if ($subject === '' || $desc === '') {
        setFlash('error', 'Please fill in the subject and description.');
    } else {
        $pdo->prepare(
            "INSERT INTO grievances (student_id, category, subject, description, priority)
             VALUES (?, ?, ?, ?, ?)"
        )->execute([$studentId, $category, $subject, $desc, $priority]);

        logAudit($pdo, 'grievance_submitted', 'grievances', $pdo->lastInsertId(), $subject);
        setFlash('success', 'Your grievance has been submitted.');
    }
    redirect(BASE_URL . '/student/grievances.php');
}

$list = $pdo->prepare(
    "SELECT * FROM grievances WHERE student_id = ? ORDER BY created_at DESC"
);
$list->execute([$studentId]);
$rows = $list->fetchAll();

$pageTitle = APP_NAME . ' | Grievances';
include __DIR__ . '/../includes/header.php';
?>

<h2>Grievance &amp; Complaint Portal</h2>

<div class="card">
    <h3>Submit a New Grievance</h3>
    <form method="post">
        <?php csrfField(); ?>
        <div class="form-row">
            <div>
                <label>Category</label>
                <select name="category">
                    <option>Academic</option>
                    <option>Hostel</option>
                    <option>Fees</option>
                    <option>Library</option>
                    <option>Infrastructure</option>
                    <option>Other</option>
                </select>
            </div>
            <div>
                <label>Priority</label>
                <select name="priority">
                    <option value="low">Low</option>
                    <option value="medium" selected>Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>
        </div>
        <label>Subject</label>
        <input type="text" name="subject" required maxlength="255">
        <label>Description</label>
        <textarea name="description" rows="4" required></textarea>
        <button type="submit" class="btn btn-primary">Submit Grievance</button>
    </form>
</div>

<div class="card">
    <h3>My Grievances</h3>
    <?php if ($rows): ?>
        <table class="data-table">
            <tr><th>Subject</th><th>Category</th><th>Priority</th><th>Status</th><th>Submitted</th></tr>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e($r['subject']) ?></td>
                    <td><?= e($r['category']) ?></td>
                    <td><?= e(ucfirst($r['priority'])) ?></td>
                    <td><span class="badge badge-<?= e($r['status']) ?>"><?= e(ucwords(str_replace('_',' ', $r['status']))) ?></span></td>
                    <td><?= e(date('d M Y', strtotime($r['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p class="muted">You haven't submitted any grievances yet.</p>
    <?php endif; ?>
</div>

<a href="<?= BASE_URL ?>/student/dashboard.php" class="btn btn-outline">&larr; Back to Dashboard</a>

<?php include __DIR__ . '/../includes/footer.php'; ?>
