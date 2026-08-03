<?php
require_once __DIR__ . '/../includes/auth-check.php';
requireRole('Super Admin', 'Admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_company') {
        $name = trim($_POST['company_name'] ?? '');
        $industry = trim($_POST['industry'] ?? '');
        $email = trim($_POST['contact_email'] ?? '');
        if ($name === '') {
            setFlash('error', 'Please enter a company name.');
        } else {
            $pdo->prepare("INSERT INTO companies (company_name, industry, contact_email) VALUES (?, ?, ?)")
                ->execute([$name, $industry, $email]);
            logAudit($pdo, 'company_added', 'placements', $pdo->lastInsertId(), $name);
            setFlash('success', "Company \"$name\" added.");
        }
    }

    if ($action === 'add_drive') {
        $companyId = (int)$_POST['company_id'];
        $title = trim($_POST['drive_title'] ?? '');
        $date = $_POST['drive_date'] ?: null;
        $role = trim($_POST['job_role'] ?? '');
        $package = (float)($_POST['package_amount'] ?? 0);
        $eligibility = trim($_POST['eligibility'] ?? '');
        $status = $_POST['status'] ?? 'upcoming';

        if (!$companyId || $title === '') {
            setFlash('error', 'Please select a company and enter a drive title.');
        } else {
            $pdo->prepare(
                "INSERT INTO placement_drives (company_id, drive_title, drive_date, job_role, package_amount, eligibility, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            )->execute([$companyId, $title, $date, $role, $package, $eligibility, $status]);
            logAudit($pdo, 'placement_drive_created', 'placements', $pdo->lastInsertId(), $title);
            setFlash('success', "Drive \"$title\" created.");
        }
    }

    if ($action === 'update_application') {
        $appId = (int)$_POST['application_id'];
        $status = $_POST['status'];
        if (in_array($status, ['applied','shortlisted','selected','rejected'], true)) {
            $pdo->prepare("UPDATE placement_applications SET status = ? WHERE application_id = ?")->execute([$status, $appId]);
            logAudit($pdo, 'placement_application_updated', 'placements', $appId, "Status -> $status");
            setFlash('success', 'Application status updated.');
        }
    }

    redirect(BASE_URL . '/admin/placements.php');
}

$companies = $pdo->query("SELECT * FROM companies ORDER BY company_name")->fetchAll();
$drives = $pdo->query(
    "SELECT pd.*, c.company_name,
            (SELECT COUNT(*) FROM placement_applications pa WHERE pa.drive_id = pd.drive_id) AS applicant_count
     FROM placement_drives pd JOIN companies c ON c.company_id = pd.company_id
     ORDER BY pd.drive_date DESC"
)->fetchAll();

$driveId = (int)($_GET['drive_id'] ?? 0);
$applications = [];
if ($driveId) {
    $apps = $pdo->prepare(
        "SELECT pa.*, s.roll_number, s.first_name, s.last_name, d.department_name
         FROM placement_applications pa
         JOIN students s ON s.student_id = pa.student_id
         JOIN departments d ON d.department_id = s.department_id
         WHERE pa.drive_id = ?
         ORDER BY pa.applied_at"
    );
    $apps->execute([$driveId]);
    $applications = $apps->fetchAll();
}

$pageTitle = APP_NAME . ' | Placement Management';
include __DIR__ . '/../includes/header.php';
?>

<h2>Placement Management</h2>

<div class="two-col">
    <div class="card">
        <h3>Add a Company</h3>
        <form method="post">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="add_company">
            <label>Company Name</label>
            <input type="text" name="company_name" required>
            <div class="form-row">
                <div><label>Industry</label><input type="text" name="industry"></div>
                <div><label>Contact Email</label><input type="email" name="contact_email"></div>
            </div>
            <button type="submit" class="btn btn-primary">Add Company</button>
        </form>
    </div>

    <div class="card">
        <h3>Create a Placement Drive</h3>
        <form method="post">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="add_drive">
            <label>Company</label>
            <select name="company_id" required>
                <option value="">-- Select --</option>
                <?php foreach ($companies as $c): ?>
                    <option value="<?= (int)$c['company_id'] ?>"><?= e($c['company_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <label>Drive Title</label>
            <input type="text" name="drive_title" required placeholder="e.g. Campus Drive 2026 - Software Engineer">
            <div class="form-row">
                <div><label>Job Role</label><input type="text" name="job_role"></div>
                <div><label>Package (₹ per annum)</label><input type="number" name="package_amount" step="1000" min="0"></div>
            </div>
            <div class="form-row">
                <div><label>Drive Date</label><input type="date" name="drive_date"></div>
                <div>
                    <label>Status</label>
                    <select name="status">
                        <option value="upcoming">Upcoming</option>
                        <option value="open">Open for Applications</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
            </div>
            <label>Eligibility</label>
            <textarea name="eligibility" rows="2" placeholder="e.g. CSE/ECE, no active backlogs"></textarea>
            <button type="submit" class="btn btn-primary">Create Drive</button>
        </form>
    </div>
</div>

<div class="card">
    <h3>All Drives</h3>
    <table class="data-table">
        <tr><th>Drive</th><th>Company</th><th>Role</th><th>Package</th><th>Date</th><th>Status</th><th>Applicants</th></tr>
        <?php foreach ($drives as $d): ?>
            <tr>
                <td><?= e($d['drive_title']) ?></td>
                <td><?= e($d['company_name']) ?></td>
                <td><?= e($d['job_role']) ?></td>
                <td><?= $d['package_amount'] ? '₹' . number_format($d['package_amount'], 0) : '-' ?></td>
                <td><?= e($d['drive_date'] ? date('d M Y', strtotime($d['drive_date'])) : '-') ?></td>
                <td><span class="badge badge-<?= e($d['status']) ?>"><?= e(ucfirst($d['status'])) ?></span></td>
                <td><a href="?drive_id=<?= (int)$d['drive_id'] ?>"><?= (int)$d['applicant_count'] ?> applicant(s)</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$drives): ?><tr><td colspan="7" class="muted">No drives yet.</td></tr><?php endif; ?>
    </table>
</div>

<?php if ($driveId): ?>
<div class="card">
    <h3>Applicants</h3>
    <table class="data-table">
        <tr><th>Student</th><th>Department</th><th>Applied</th><th>Status</th><th>Update</th></tr>
        <?php foreach ($applications as $a): ?>
            <tr>
                <td><?= e($a['roll_number']) ?> - <?= e($a['first_name']) ?> <?= e($a['last_name']) ?></td>
                <td><?= e($a['department_name']) ?></td>
                <td><?= e(date('d M Y', strtotime($a['applied_at']))) ?></td>
                <td><span class="badge badge-<?= e($a['status']) ?>"><?= e(ucfirst($a['status'])) ?></span></td>
                <td>
                    <form method="post" style="display:flex;gap:6px">
                        <?php csrfField(); ?>
                        <input type="hidden" name="action" value="update_application">
                        <input type="hidden" name="application_id" value="<?= (int)$a['application_id'] ?>">
                        <select name="status" style="width:130px">
                            <?php foreach (['applied','shortlisted','selected','rejected'] as $st): ?>
                                <option value="<?= $st ?>" <?= $a['status'] === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-sm btn-primary">Update</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$applications): ?><tr><td colspan="5" class="muted">No applicants yet.</td></tr><?php endif; ?>
    </table>
</div>
<?php endif; ?>

<a href="<?= BASE_URL ?>/admin/dashboard.php" class="btn btn-outline">&larr; Back to Dashboard</a>

<?php include __DIR__ . '/../includes/footer.php'; ?>
