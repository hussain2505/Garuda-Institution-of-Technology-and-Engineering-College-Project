<?php
require_once __DIR__ . '/../includes/auth-check.php';
requireRole('Super Admin', 'Admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $type = trim($_POST['notification_type'] ?? 'general');

    if ($title === '' || $message === '') {
        setFlash('error', 'Please fill in both the title and message.');
    } else {
        $pdo->prepare(
            "INSERT INTO notifications (title, message, notification_type, sender_id) VALUES (?, ?, ?, ?)"
        )->execute([$title, $message, $type, $_SESSION['user_id']]);

        $notifId = $pdo->lastInsertId();

        // fan out to every active user
        $userIds = $pdo->query("SELECT user_id FROM users WHERE status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
        $insertRecipient = $pdo->prepare("INSERT INTO notification_recipients (notification_id, user_id) VALUES (?, ?)");
        foreach ($userIds as $uid) {
            $insertRecipient->execute([$notifId, $uid]);
        }

        logAudit($pdo, 'notice_created', 'notifications', $notifId, $title);
        setFlash('success', 'Notice published to all users.');
    }
    redirect(BASE_URL . '/admin/notices.php');
}

$notices = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 25")->fetchAll();

$pageTitle = APP_NAME . ' | Notices';
include __DIR__ . '/../includes/header.php';
?>

<h2>Notices &amp; Announcements</h2>

<div class="card">
    <h3>Publish a Notice</h3>
    <form method="post">
        <?php csrfField(); ?>
        <label>Title</label>
        <input type="text" name="title" required maxlength="255">
        <label>Type</label>
        <select name="notification_type">
            <option value="general">General</option>
            <option value="exam">Examination</option>
            <option value="fee">Fees</option>
            <option value="event">Event</option>
            <option value="placement">Placement</option>
        </select>
        <label>Message</label>
        <textarea name="message" rows="4" required></textarea>
        <button type="submit" class="btn btn-primary">Publish</button>
    </form>
</div>

<div class="card">
    <h3>Published Notices</h3>
    <table class="data-table">
        <tr><th>Title</th><th>Type</th><th>Date</th></tr>
        <?php foreach ($notices as $n): ?>
            <tr>
                <td><?= e($n['title']) ?></td>
                <td><?= e(ucfirst($n['notification_type'])) ?></td>
                <td><?= e(date('d M Y H:i', strtotime($n['created_at']))) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<a href="<?= BASE_URL ?>/admin/dashboard.php" class="btn btn-outline">&larr; Back to Dashboard</a>

<?php include __DIR__ . '/../includes/footer.php'; ?>
