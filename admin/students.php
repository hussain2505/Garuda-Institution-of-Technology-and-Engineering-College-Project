<?php
require_once __DIR__ . '/../includes/auth-check.php';
requireRole('Super Admin', 'Admin');

$departments = $pdo->query("SELECT department_id, department_name FROM departments ORDER BY department_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_student') {
    verifyCsrf();

    $roll     = trim($_POST['roll_number'] ?? '');
    $first    = trim($_POST['first_name'] ?? '');
    $last     = trim($_POST['last_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $deptId   = (int)($_POST['department_id'] ?? 0);
    $semester = (int)($_POST['current_semester'] ?? 1);
    $section  = trim($_POST['section'] ?? 'A');

    if ($roll === '' || $first === '' || $deptId <= 0 || !isValidEmail($email)) {
        setFlash('error', 'Please fill in all required fields with a valid email.');
    } else {
        try {
            // create a login account too, with a temporary password
            $tempPassword = bin2hex(random_bytes(5));
            $username = strtolower(preg_replace('/[^a-z0-9]/i', '', $roll));
            $hash = password_hash($tempPassword, PASSWORD_DEFAULT);
            $studentRoleId = $pdo->query("SELECT role_id FROM roles WHERE role_name = 'Student'")->fetchColumn();

            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO users (username, email, password_hash, role_id) VALUES (?, ?, ?, ?)")
                ->execute([$username, $email, $hash, $studentRoleId]);
            $userId = $pdo->lastInsertId();

            $pdo->prepare(
                "INSERT INTO students (user_id, roll_number, first_name, last_name, email, department_id, current_semester, section, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')"
            )->execute([$userId, $roll, $first, $last, $email, $deptId, $semester, $section]);

            $pdo->commit();
            logAudit($pdo, 'student_created', 'students', $userId, "Admin added student $roll");
            setFlash('success', "Student added. Temporary login — username: $username, password: $tempPassword");
        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlash('error', 'Could not add student. Roll number, email or username may already be in use.');
        }
    }
    redirect(BASE_URL . '/admin/students.php');
}

$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $stmt = $pdo->prepare(
        "SELECT s.*, d.department_name FROM students s
         JOIN departments d ON d.department_id = s.department_id
         WHERE s.roll_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?
         ORDER BY s.roll_number LIMIT 100"
    );
    $like = "%$search%";
    $stmt->execute([$like, $like, $like]);
} else {
    $stmt = $pdo->query(
        "SELECT s.*, d.department_name FROM students s
         JOIN departments d ON d.department_id = s.department_id
         ORDER BY s.student_id DESC LIMIT 100"
    );
}
$students = $stmt->fetchAll();

$pageTitle = APP_NAME . ' | Manage Students';
include __DIR__ . '/../includes/header.php';
?>

<h2>Manage Students</h2>

<div class="card">
    <h3>Add New Student</h3>
    <form method="post">
        <?php csrfField(); ?>
        <input type="hidden" name="action" value="add_student">
        <div class="form-row">
            <div><label>Roll Number</label><input type="text" name="roll_number" required></div>
            <div><label>Section</label><input type="text" name="section" value="A"></div>
        </div>
        <div class="form-row">
            <div><label>First Name</label><input type="text" name="first_name" required></div>
            <div><label>Last Name</label><input type="text" name="last_name"></div>
        </div>
        <div class="form-row">
            <div><label>Email</label><input type="email" name="email" required></div>
            <div>
                <label>Department</label>
                <select name="department_id" required>
                    <option value="">-- Select --</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int)$d['department_id'] ?>"><?= e($d['department_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <label>Semester</label>
        <input type="number" name="current_semester" min="1" max="8" value="1" style="max-width:100px">
        <button type="submit" class="btn btn-primary">Add Student</button>
    </form>
</div>

<div class="card">
    <h3>Student Directory</h3>
    <form method="get" class="filter-form">
        <input type="text" name="q" placeholder="Search by roll number or name" value="<?= e($search) ?>">
        <button type="submit" class="btn btn-outline">Search</button>
    </form>

    <table class="data-table">
        <tr><th>Roll No</th><th>Name</th><th>Department</th><th>Sem</th><th>Status</th></tr>
        <?php foreach ($students as $s): ?>
            <tr>
                <td><?= e($s['roll_number']) ?></td>
                <td><?= e($s['first_name']) ?> <?= e($s['last_name']) ?></td>
                <td><?= e($s['department_name']) ?></td>
                <td><?= e($s['current_semester']) ?></td>
                <td><span class="badge badge-<?= e($s['status']) ?>"><?= e(ucfirst($s['status'])) ?></span></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$students): ?>
            <tr><td colspan="5" class="muted">No students found.</td></tr>
        <?php endif; ?>
    </table>
</div>

<a href="<?= BASE_URL ?>/admin/dashboard.php" class="btn btn-outline">&larr; Back to Dashboard</a>

<?php include __DIR__ . '/../includes/footer.php'; ?>
