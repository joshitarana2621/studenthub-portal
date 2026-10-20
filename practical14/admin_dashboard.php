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
$toggleMessage = '';
$toggleError = '';

// Handle Active / Inactive Status Toggle (Practical 14 Intermediate Extension)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $submittedCsrf = $_POST['csrf_token'] ?? '';
    if (!studenthub_verify_csrf_token((string) $submittedCsrf)) {
        $toggleError = 'Invalid CSRF token.';
    } else {
        $targetId = (int) ($_POST['target_id'] ?? 0);
        $currentStatus = (string) ($_POST['current_status'] ?? 'Active');
        $newStatus = ($currentStatus === 'Active') ? 'Inactive' : 'Active';
        try {
            $updateStmt = $pdo->prepare("UPDATE students SET status = :status WHERE id = :id AND role != 'admin'");
            $updateStmt->execute(['status' => $newStatus, 'id' => $targetId]);
            $toggleMessage = "Student account #{$targetId} status updated to '{$newStatus}'.";
        } catch (PDOException $e) {
            $toggleError = 'Failed to update status: ' . $e->getMessage();
        }
    }
}

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

// Fetch summary metrics for Admin view (Practical 14 Core Metrics)
$totalStudents = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE role = 'student'")->fetchColumn();
$totalAdmins = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE role = 'admin'")->fetchColumn();
$activeTokens = (int) $pdo->query("SELECT COUNT(*) FROM remember_tokens WHERE expires_at > NOW()")->fetchColumn();
$totalEvents = (int) $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
$totalRegistrations = (int) $pdo->query("SELECT COUNT(*) FROM registrations")->fetchColumn();

// Fetch Latest Registrations with Student and Event info (Practical 14 Core Requirement)
$latestRegStmt = $pdo->query("
    SELECT r.id, s.fullname, s.enrollment, e.title AS event_title, e.category, r.registered_at, r.status
    FROM registrations r
    JOIN students s ON r.student_id = s.id
    JOIN events e ON r.event_id = e.id
    ORDER BY r.registered_at DESC
    LIMIT 6
");
$latestRegistrations = $latestRegStmt ? $latestRegStmt->fetchAll() : [];

// Fetch Recent Audit Log records (Practical 14 Core Requirement)
$recentAuditStmt = $pdo->query("
    SELECT id, event_title, admin_name, action_type, details, created_at
    FROM event_audit_log
    ORDER BY id DESC
    LIMIT 5
");
$recentAudits = $recentAuditStmt ? $recentAuditStmt->fetchAll() : [];

// Fetch Analytics: Registrations grouped by Event Category (Practical 14 Advanced Extension)
$analyticsStmt = $pdo->query("
    SELECT e.category, COUNT(r.id) AS count
    FROM events e
    LEFT JOIN registrations r ON e.id = r.event_id
    GROUP BY e.category
    ORDER BY count DESC
");
$categoryAnalytics = $analyticsStmt ? $analyticsStmt->fetchAll() : [];

// Fetch all users with status
$allUsersStmt = $pdo->query("SELECT id, fullname, enrollment, email, role, status, created_at FROM students ORDER BY id ASC");
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
        .badge-active { background: #dcfce7; color: #166534; padding: 3px 8px; border-radius: 4px; font-size: 0.82em; font-weight: 600; display: inline-block; }
        .badge-inactive { background: #fee2e2; color: #991b1b; padding: 3px 8px; border-radius: 4px; font-size: 0.82em; font-weight: 600; display: inline-block; }
        .badge-create { background: #dcfce7; color: #166534; padding: 3px 8px; border-radius: 4px; font-size: 0.80em; font-weight: bold; }
        .badge-update { background: #e0f2fe; color: #0369a1; padding: 3px 8px; border-radius: 4px; font-size: 0.80em; font-weight: bold; }
        .badge-delete { background: #fee2e2; color: #991b1b; padding: 3px 8px; border-radius: 4px; font-size: 0.80em; font-weight: bold; }
        .badge-confirmed { background: #ecfdf5; color: #047857; padding: 3px 8px; border-radius: 4px; font-size: 0.80em; font-weight: 600; }
        .btn-toggle { padding: 4px 10px; font-size: 0.80em; border-radius: 4px; border: 1px solid #cbd5e1; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-block; }
        .btn-toggle-deactivate { background: #fff1f2; color: #be123c; border-color: #fecdd3; }
        .btn-toggle-deactivate:hover { background: #ffe4e6; }
        .btn-toggle-activate { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
        .btn-toggle-activate:hover { background: #dcfce7; }
        .stats-grid { display: flex; gap: 16px; margin: 20px 0; flex-wrap: wrap; }
        .stat-card { flex: 1 1 200px; padding: 18px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; }
        .stat-card h4 { margin: 0 0 8px 0; color: #475569; font-size: 0.95em; }
        .stat-card p { margin: 0; font-size: 1.8em; font-weight: bold; color: #0f172a; }
        .user-table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        .user-table th, .user-table td { border: 1px solid #cbd5e1; padding: 10px 12px; text-align: left; }
        .user-table th { background: #f1f5f9; font-size: 0.9em; }
        .chart-card { background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 20px; margin: 20px 0; }
        .chart-row { display: flex; align-items: center; margin-bottom: 12px; gap: 12px; }
        .chart-label { width: 150px; font-size: 0.88em; font-weight: 600; color: #334155; }
        .chart-track { flex: 1; background: #f1f5f9; height: 22px; border-radius: 6px; overflow: hidden; border: 1px solid #e2e8f0; }
        .chart-fill { background: linear-gradient(90deg, #3b82f6, #1d4ed8); height: 100%; border-radius: 5px; transition: width 0.3s ease; }
        .chart-count { width: 80px; text-align: right; font-size: 0.88em; font-weight: 700; color: #0f172a; }
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
            <li><a href="../admin/events-ajax.php" style="color: #afdcf6; font-weight: bold;">⚡ AJAX Events</a></li>
            <li><a href="../admin/event-audit.php">Audit Log</a></li>
            <li><a href="events.php">Student Events View</a></li>
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

        <!-- Practical 14: Core Aggregation Metrics -->
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
                <div class="stat-card" style="background: #fffbeb; border-color: #f59e0b;">
                    <h4 style="color: #b45309;">Total Registrations</h4>
                    <p style="color: #b45309;"><?= $totalRegistrations ?></p>
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
                <div class="stat-card" style="background: #f5f3ff; border-color: #7c3aed;">
                    <h4 style="color: #7c3aed;">⚡ AJAX Events</h4>
                    <p style="font-size: 1.1em; margin: 8px 0;">
                        <a href="../admin/events-ajax.php" style="color: #7c3aed; font-weight: bold; text-decoration: underline;">&rarr; AJAX / Fetch CRUD</a>
                    </p>
                    <small style="color: #475569;">CRUD without page reload via Fetch API &amp; JSON</small>
                </div>
                <div class="stat-card" style="background: #f0fdf4; border-color: #10b981;">
                    <h4 style="color: #10b981;">Student Events Portal</h4>
                    <p style="font-size: 1.1em; margin: 8px 0;">
                        <a href="events.php" style="color: #10b981; font-weight: bold; text-decoration: underline;">&rarr; Student Events View</a>
                    </p>
                    <small style="color: #475569;">Public/Student-facing campus events directory</small>
                </div>
            </div>
        </section>

        <!-- Practical 14 Core Requirement: Latest Registrations Feed -->
        <section>
            <h3>📋 Latest Student Event Registrations</h3>
            <p>Recent event enrollments submitted by students across all departments:</p>
            <?php if (empty($latestRegistrations)): ?>
                <p><em>No student registrations recorded yet.</em></p>
            <?php else: ?>
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>Reg ID</th>
                            <th>Student Name</th>
                            <th>Enrollment</th>
                            <th>Event Title</th>
                            <th>Category</th>
                            <th>Registration Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($latestRegistrations as $reg): ?>
                            <tr>
                                <td>#<?= (int) $reg['id'] ?></td>
                                <td><strong><?= htmlspecialchars((string) $reg['fullname'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                <td><code><?= htmlspecialchars((string) $reg['enrollment'], ENT_QUOTES, 'UTF-8') ?></code></td>
                                <td><?= htmlspecialchars((string) $reg['event_title'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 0.85em;"><?= htmlspecialchars((string) $reg['category'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><?= htmlspecialchars(date('d-M-Y H:i', strtotime((string) $reg['registered_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="badge-confirmed">✓ <?= htmlspecialchars((string) $reg['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <!-- Practical 14 Advanced Extension: Registration Analytics Chart -->
        <section>
            <h3>📊 Event Registrations Analytics by Category</h3>
            <p>Visual breakdown of student registrations across event categories:</p>
            <div class="chart-card">
                <?php
                $maxCount = 1;
                foreach ($categoryAnalytics as $c) {
                    if ((int) $c['count'] > $maxCount) {
                        $maxCount = (int) $c['count'];
                    }
                }
                foreach ($categoryAnalytics as $cat):
                    $cnt = (int) $cat['count'];
                    $pct = ($maxCount > 0) ? round(($cnt / $maxCount) * 100) : 0;
                ?>
                    <div class="chart-row">
                        <div class="chart-label"><?= htmlspecialchars((string) ($cat['category'] ?: 'Uncategorized'), ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="chart-track">
                            <div class="chart-fill" style="width: <?= max(8, $pct) ?>%;"></div>
                        </div>
                        <div class="chart-count"><?= $cnt ?> <?= ($cnt === 1) ? 'student' : 'students' ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Practical 14 Core Requirement: Recent Audit Log Feed -->
        <section>
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                <h3>🛡️ Recent Administrative Audit Log</h3>
                <a href="../admin/event-audit.php" style="color: #003366; font-weight: bold; font-size: 0.9em;">View Full Audit Trail &rarr;</a>
            </div>
            <p>Traceability log of recent administrative event creation, updates, and removals:</p>

            <?php if (empty($recentAudits)): ?>
                <p><em>No administrative actions logged yet.</em></p>
            <?php else: ?>
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Event Affected</th>
                            <th>Admin Responsible</th>
                            <th>Details</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentAudits as $log): ?>
                            <tr>
                                <td>
                                    <?php
                                    $act = (string) $log['action_type'];
                                    $actClass = ($act === 'CREATE') ? 'badge-create' : (($act === 'UPDATE') ? 'badge-update' : 'badge-delete');
                                    ?>
                                    <span class="<?= $actClass ?>"><?= htmlspecialchars($act, ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td><strong><?= htmlspecialchars((string) $log['event_title'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                <td><?= htmlspecialchars((string) $log['admin_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><small style="color: #475569;"><?= htmlspecialchars((string) $log['details'], ENT_QUOTES, 'UTF-8') ?></small></td>
                                <td><?= htmlspecialchars(date('d-M-Y H:i', strtotime((string) $log['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
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

        <!-- Practical 14 Intermediate Extension: User Directory with Active/Inactive Toggle -->
        <section>
            <h3>User Directory & Access Control (RBAC)</h3>
            <p>Registered accounts, assigned roles, and interactive Active/Inactive status toggle:</p>

            <?php if ($toggleMessage !== ''): ?>
                <div class="alert-success"><?= htmlspecialchars($toggleMessage, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if ($toggleError !== ''): ?>
                <div class="alert-error"><?= htmlspecialchars($toggleError, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <table class="user-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Enrollment</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Account Status</th>
                        <th>Registered On</th>
                        <th>Status Toggle Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allUsers as $u): ?>
                        <tr>
                            <td><?= (int) $u['id'] ?></td>
                            <td><?= htmlspecialchars((string) $u['fullname'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><code><?= htmlspecialchars((string) $u['enrollment'], ENT_QUOTES, 'UTF-8') ?></code></td>
                            <td><?= htmlspecialchars((string) $u['email'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span class="badge-admin">admin</span>
                                <?php else: ?>
                                    <span class="badge-student">student</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (($u['status'] ?? 'Active') === 'Active'): ?>
                                    <span class="badge-active">Active</span>
                                <?php else: ?>
                                    <span class="badge-inactive">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars(date('d-M-Y', strtotime((string) $u['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php if ($u['role'] === 'admin'): ?>
                                    <small style="color: #64748b;">Protected (Admin)</small>
                                <?php else: ?>
                                    <form method="POST" style="margin: 0; display: inline;">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="target_id" value="<?= (int) $u['id'] ?>">
                                        <input type="hidden" name="current_status" value="<?= htmlspecialchars((string) ($u['status'] ?? 'Active'), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <?php if (($u['status'] ?? 'Active') === 'Active'): ?>
                                            <button type="submit" class="btn-toggle btn-toggle-deactivate" onclick="return confirm('Deactivate this student account?');">Deactivate</button>
                                        <?php else: ?>
                                            <button type="submit" class="btn-toggle btn-toggle-activate" onclick="return confirm('Re-activate this student account?');">Activate</button>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>
                            </td>
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
