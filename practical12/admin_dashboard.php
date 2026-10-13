<?php

declare(strict_types=1);

require_once __DIR__ . '/../api/auth.php';
require_once __DIR__ . '/../api/db.php';

// Server-side authorization check: only admins allowed
studenthub_require_role('admin', $pdo);

$userStatement = $pdo->prepare('SELECT fullname, enrollment, email FROM students WHERE id = :id LIMIT 1');
$userStatement->execute(['id' => (int) $_SESSION['user_id']]);
$userData = $userStatement->fetch();

$fullName = $userData['fullname'] ?? ($_SESSION['fullname'] ?? 'Administrator');
$enrollment = $userData['enrollment'] ?? ($_SESSION['enrollment'] ?? 'ADMIN');
$csrfToken = studenthub_get_csrf_token();

$addStudentMessage = '';
$addStudentError = '';

// Handle Admin Adding a New Student / User directly
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_student') {
    $submittedCsrf = $_POST['csrf_token'] ?? '';
    if (!studenthub_verify_csrf_token((string) $submittedCsrf)) {
        $addStudentError = 'Invalid CSRF token.';
    } else {
        $newFullname = trim((string) ($_POST['new_fullname'] ?? ''));
        $newEnrollment = trim((string) ($_POST['new_enrollment'] ?? ''));
        $newEmail = trim((string) ($_POST['new_email'] ?? ''));
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $newRole = (($_POST['new_role'] ?? 'student') === 'admin') ? 'admin' : 'student';

        if ($newFullname === '' || $newEnrollment === '' || $newEmail === '' || strlen($newPassword) < 6) {
            $addStudentError = 'Please fill all fields properly. Password must be at least 6 characters.';
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $addStudentError = 'Please enter a valid email address.';
        } else {
            try {
                $checkStmt = $pdo->prepare('SELECT id FROM students WHERE enrollment = :enrollment OR email = :email LIMIT 1');
                $checkStmt->execute(['enrollment' => $newEnrollment, 'email' => $newEmail]);
                if ($checkStmt->fetch()) {
                    $addStudentError = 'An account with this enrollment number or email already exists.';
                } else {
                    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $ins = $pdo->prepare(
                        'INSERT INTO students (fullname, enrollment, email, role, password_hash)
                         VALUES (:fullname, :enrollment, :email, :role, :password_hash)'
                    );
                    $ins->execute([
                        'fullname' => $newFullname,
                        'enrollment' => $newEnrollment,
                        'email' => $newEmail,
                        'role' => $newRole,
                        'password_hash' => $hash,
                    ]);
                    $addStudentMessage = "Account for '{$newFullname}' ({$newEnrollment}) added successfully!";
                }
            } catch (PDOException $e) {
                $addStudentError = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Fetch summary metrics for Admin view
$totalStudents = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE role = 'student'")->fetchColumn();
$totalAdmins = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE role = 'admin'")->fetchColumn();
$activeTokens = (int) $pdo->query("SELECT COUNT(*) FROM remember_tokens WHERE expires_at > NOW()")->fetchColumn();
$totalEvents = (int) $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();

// Fetch user list
$allUsersStmt = $pdo->query("SELECT id, fullname, enrollment, email, role, created_at FROM students ORDER BY id ASC");
$allUsers = $allUsersStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - StudentHub Portal</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .badge-admin { background: #b13c34; color: #fff; padding: 3px 8px; border-radius: 4px; font-size: 0.85em; font-weight: bold; }
        .badge-student { background: #16805d; color: #fff; padding: 3px 8px; border-radius: 4px; font-size: 0.85em; }
        .stats-grid { display: flex; gap: 16px; margin: 20px 0; flex-wrap: wrap; }
        .stat-card { flex: 1 1 200px; padding: 18px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; }
        .stat-card h4 { margin: 0 0 8px 0; color: #475569; font-size: 0.95em; }
        .stat-card p { margin: 0; font-size: 1.8em; font-weight: bold; color: #0f172a; }
        .user-table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        .user-table th, .user-table td { border: 1px solid #cbd5e1; padding: 10px 12px; text-align: left; }
        .user-table th { background: #f1f5f9; }
        .add-student-box { background: #f1f5f9; border: 1px solid #cbd5e1; padding: 20px; border-radius: 6px; margin: 24px 0; }
        .add-student-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-top: 12px; }
        .add-student-form input, .add-student-form select { padding: 8px 10px; border: 1px solid #94a3b8; border-radius: 4px; font: inherit; }
        .add-student-form button { grid-column: 1 / -1; padding: 10px; background: #16805d; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; }
        .alert-success { background: #dcfce7; color: #166534; padding: 10px 14px; border-radius: 4px; margin-bottom: 12px; }
        .alert-error { background: #fee2e2; color: #991b1b; padding: 10px 14px; border-radius: 4px; margin-bottom: 12px; }
    </style>
</head>

<body>

    <header>
        <div>
            <img src="../images/logo.png" alt="StudentHub Portal Logo" width="100">
            <h1>studenthub-portal (Admin Console)</h1>
        </div>
    </header>

    <button id="hamburger" class="hamburger" aria-label="Toggle navigation">☰</button>
    <nav id="mainNav" class="nav-menu">
        <ul>
            <li><a href="index.html">Home</a></li>
            <li><a href="admin_dashboard.php" class="active">Admin Dashboard</a></li>
            <li><a href="../admin/students.php">Manage Students (CRUD)</a></li>
            <li><a href="../admin/events.php">Manage Events (CRUD)</a></li>
            <li><a href="login.html">Login</a></li>
            <li><a href="register.html">Register</a></li>
            <li><a href="contact.html">Contact</a></li>
        </ul>
    </nav>

    <main>
        <section>
            <h2>System Administration Dashboard</h2>
            <p>Welcome, <strong id="sessionUser"><?= htmlspecialchars((string) $fullName, ENT_QUOTES, 'UTF-8') ?></strong> (<?= htmlspecialchars((string) $enrollment, ENT_QUOTES, 'UTF-8') ?>).</p>
            <p>
                <span class="badge-admin">Role: Administrator</span>
            </p>
            <form method="POST" action="../api/logout.php" style="margin-top: 10px;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <button id="logoutButton" type="submit">Logout</button>
            </form>
        </section>

        <section>
            <h3>Portal Metrics & Role Summary</h3>
            <div class="stats-grid">
                <div class="stat-card">
                    <h4>Registered Students</h4>
                    <p><?= $totalStudents ?></p>
                </div>
                <div class="stat-card">
                    <h4>Administrators</h4>
                    <p><?= $totalAdmins ?></p>
                </div>
                <div class="stat-card">
                    <h4>Active Remember-Me Sessions</h4>
                    <p><?= $activeTokens ?></p>
                </div>
                <div class="stat-card">
                    <h4>Scheduled Events</h4>
                    <p><?= $totalEvents ?></p>
                </div>
                <div class="stat-card" style="background: #edf7f2; border-color: #16805d;">
                    <h4 style="color: #16805d;">Manage Students</h4>
                    <p style="font-size: 1.1em; margin: 8px 0;">
                        <a href="../admin/students.php" style="color: #16805d; font-weight: bold; text-decoration: underline;">&rarr; Manage Students</a>
                    </p>
                    <small style="color: #475569;">Search, filter, paginate, edit &amp; delete student records</small>
                </div>
                <div class="stat-card" style="background: #eef2ff; border-color: #4f46e5;">
                    <h4 style="color: #4f46e5;">Manage Events</h4>
                    <p style="font-size: 1.1em; margin: 8px 0;">
                        <a href="../admin/events.php" style="color: #4f46e5; font-weight: bold; text-decoration: underline;">&rarr; Manage Events</a>
                    </p>
                    <small style="color: #475569;">Add, edit, delete events, poster upload &amp; audit log</small>
                </div>
            </div>
        </section>

        <!-- Section: Add New Student / User -->
        <section class="add-student-box">
            <h3>Add New Student / User</h3>
            <p>Directly register a new student or administrator into the database:</p>

            <?php if ($addStudentMessage !== ''): ?>
                <div class="alert-success"><?= htmlspecialchars($addStudentMessage, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if ($addStudentError !== ''): ?>
                <div class="alert-error"><?= htmlspecialchars($addStudentError, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="POST" class="add-student-form">
                <input type="hidden" name="action" value="add_student">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <div>
                    <label for="new_fullname">Full Name</label><br>
                    <input type="text" id="new_fullname" name="new_fullname" placeholder="e.g. Rahul Sharma" required>
                </div>

                <div>
                    <label for="new_enrollment">Enrollment Number</label><br>
                    <input type="text" id="new_enrollment" name="new_enrollment" placeholder="e.g. 25dce050" required>
                </div>

                <div>
                    <label for="new_email">Email Address</label><br>
                    <input type="email" id="new_email" name="new_email" placeholder="e.g. rahul@studenthub.edu" required>
                </div>

                <div>
                    <label for="new_password">Password</label><br>
                    <input type="password" id="new_password" name="new_password" placeholder="At least 6 characters" required>
                </div>

                <div>
                    <label for="new_role">User Role</label><br>
                    <select id="new_role" name="new_role">
                        <option value="student" selected>Student</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>

                <button type="submit">+ Add Student to Database</button>
            </form>
        </section>

        <section>
            <h3>User Directory & Access Roles</h3>
            <p>All registered accounts and their assigned RBAC permissions:</p>

            <table class="user-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Enrollment</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Registered On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allUsers as $u): ?>
                        <tr>
                            <td><?= (int) $u['id'] ?></td>
                            <td><?= htmlspecialchars((string) $u['fullname'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) $u['enrollment'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) $u['email'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span class="badge-admin">admin</span>
                                <?php else: ?>
                                    <span class="badge-student">student</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars(date('d-M-Y', strtotime((string) $u['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>

    <footer>
        <p>&copy; 2026 StudentHub Portal. All Rights Reserved.</p>
    </footer>

    <script src="../js/auth-guard.js"></script>
</body>

</html>
