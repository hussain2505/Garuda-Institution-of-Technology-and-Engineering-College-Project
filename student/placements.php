<?php
require_once __DIR__ . '/../includes/auth-check.php';
requireRole('Student');

$stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();
if (!$student) die("Student profile not found.");
$studentId = $student['student_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply') {
    verifyCsrf();
    $driveId = (int)$_POST['drive_id'];

    $drive = $pdo->prepare("SELECT * FROM placement_drives WHERE drive_id = ? AND status = 'open'");
    $drive->execute([$driveId]);
    if (!$drive->fetch()) {
        setFlash('error', 'This drive is not currently open for applications.');
    } else {
        try {
            $pdo->prepare("INSERT INTO placement_applications (drive_id, student_id) VALUES (?, ?)")
                ->execute([$driveId, $studentId]);
            logAudit($pdo, 'placement_applied', 'placements', $driveId, "Student #$studentId applied");
            setFlash('success', 'Application submitted!');
        } catch (PDOException $e) {
            setFlash('error', 'You have already applied to this drive.');
        }
    }
    redirect(BASE_URL . '/student/placements.php');
}

$drives = $pdo->query(
    "SELECT pd.*, c.company_name FROM placement_drives pd
     JOIN companies c ON c.company_id = pd.company_id
     WHERE pd.status IN ('open','upcoming')
     ORDER BY pd.drive_date"
)->fetchAll();

$myApps = $pdo->prepare(
    "SELECT pa.drive_id, pa.status FROM placement_applications pa WHERE pa.student_id = ?"
);
$myApps->execute([$studentId]);
$appliedDrives = array_column($myApps->fetchAll(), 'status', 'drive_id');

$pageTitle = APP_NAME . ' | Placements';
include __DIR__ . '/../includes/header.php';
?>

<h2>Placement Drives</h2>

<div class="card">
    <table class="data-table">
        <tr><th>Company</th><th>Role</th><th>Package</th><th>Date</th><th>Eligibility</th><th>Status</th><th></th></tr>
        <?php foreach ($drives as $d):
            $applied = $appliedDrives[$d['drive_id']] ?? null; ?>
            <tr>
                <td><?= e($d['company_name']) ?></td>
                <td><?= e($d['job_role']) ?></td>
                <td><?= $d['package_amount'] ? '₹' . number_format($d['package_amount'], 0) : '-' ?></td>
                <td><?= e($d['drive_date'] ? date('d M Y', strtotime($d['drive_date'])) : '-') ?></td>
                <td><?= e($d['eligibility']) ?></td>
                <td><span class="badge badge-<?= e($d['status']) ?>"><?= e(ucfirst($d['status'])) ?></span></td>
                <td>
                    <?php if ($applied): ?>
                        <span class="badge badge-<?= e($applied) ?>">You: <?= e(ucfirst($applied)) ?></span>
                    <?php elseif ($d['status'] === 'open'): ?>
                        <form method="post">
                            <?php csrfField(); ?>
                            <input type="hidden" name="action" value="apply">
                            <input type="hidden" name="drive_id" value="<?= (int)$d['drive_id'] ?>">
                            <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                        </form>
                    <?php else: ?>
                        <span class="muted small">Not open yet</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$drives): ?><tr><td colspan="7" class="muted">No active placement drives right now.</td></tr><?php endif; ?>
    </table>
</div>

<a href="<?= BASE_URL ?>/student/dashboard.php" class="btn btn-outline">&larr; Back to Dashboard</a>

<?php include __DIR__ . '/../includes/footer.php'; ?>
