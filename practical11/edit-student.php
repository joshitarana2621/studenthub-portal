<?php

declare(strict_types=1);

/**
 * StudentHub - Practical 11: Student Management CRUD with Search and Filter
 * Module: Edit Student Record & Form Processing
 */

require_once __DIR__ . '/../api/auth.php';
require_once __DIR__ . '/../api/db.php';

// Server-side authentication and role guard: only administrators allowed
studenthub_require_role('admin', $pdo);

$adminFullname = (string) ($_SESSION['fullname'] ?? 'Administrator');
$adminEnrollment = (string) ($_SESSION['enrollment'] ?? 'ADMIN');
$csrfToken = studenthub_get_csrf_token();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash_error'] = 'Invalid student ID specified.';
    header('Location: students.php');
    exit;
}

// Fetch existing student record via MySQLi prepared SELECT
$stmt = $conn->prepare("SELECT id, fullname, enrollment, email, course, status FROM students WHERE id = ? AND role = 'student' LIMIT 1");
if ($stmt === false) {
    die("Database error preparing select query: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8'));
}
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

if (!$student) {
    $_SESSION['flash_error'] = 'Student record not found or cannot be edited.';
    header('Location: students.php');
    exit;
}

$fullname = (string) $student['fullname'];
$enrollment = (string) $student['enrollment'];
$email = (string) $student['email'];
$course = (string) $student['course'];
$status = (string) $student['status'];
$errors = [];

// Available standard courses
$availableCourses = [
    'Computer Engineering',
    'Information Technology',
    'Artificial Intelligence & Machine Learning',
    'Mechanical Engineering',
    'Civil Engineering',
    'Electrical Engineering'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedCsrf = (string) ($_POST['csrf_token'] ?? '');
    if (!studenthub_verify_csrf_token($submittedCsrf)) {
        $errors[] = 'Invalid or expired CSRF security token. Please try again.';
    } else {
        $fullname = trim((string) ($_POST['fullname'] ?? ''));
        $enrollment = trim((string) ($_POST['enrollment'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $course = trim((string) ($_POST['course'] ?? ''));
        $status = (string) ($_POST['status'] ?? 'Active');

        // Server-side validation
        if ($fullname === '') {
            $errors[] = 'Full name is required.';
        } elseif (strlen($fullname) < 2 || strlen($fullname) > 120) {
            $errors[] = 'Full name must be between 2 and 120 characters.';
        }

        if ($enrollment === '') {
            $errors[] = 'Enrollment number is required.';
        } elseif (strlen($enrollment) < 4 || strlen($enrollment) > 32 || !preg_match('/^[A-Za-z0-9]+$/', $enrollment)) {
            $errors[] = 'Enrollment number must be 4–32 alphanumeric characters.';
        }

        if ($email === '') {
            $errors[] = 'Email address is required.';
        } elseif (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        if ($course === '') {
            $errors[] = 'Course / Department is required.';
        }

        if (!in_array($status, ['Active', 'Inactive'], true)) {
            $status = 'Active';
        }

        // Check for duplicates excluding the current record
        if (empty($errors)) {
            $dupStmt = $conn->prepare("SELECT id, enrollment, email FROM students WHERE (enrollment = ? OR email = ?) AND id != ? LIMIT 1");
            if ($dupStmt === false) {
                $errors[] = 'Database error checking duplicates: ' . $conn->error;
            } else {
                $dupStmt->bind_param('ssi', $enrollment, $email, $id);
                $dupStmt->execute();
                $dupRes = $dupStmt->get_result();
                if ($dupRes && $dup = $dupRes->fetch_assoc()) {
                    if (strcasecmp((string)$dup['enrollment'], $enrollment) === 0) {
                        $errors[] = "Enrollment number '{$enrollment}' is already assigned to another student.";
                    }
                    if (strcasecmp((string)$dup['email'], $email) === 0) {
                        $errors[] = "Email address '{$email}' is already assigned to another student.";
                    }
                }
                $dupStmt->close();
            }
        }

        // Execute UPDATE using MySQLi prepared statement
        if (empty($errors)) {
            $updateStmt = $conn->prepare(
                "UPDATE students 
                 SET fullname = ?, enrollment = ?, email = ?, course = ?, status = ?
                 WHERE id = ? AND role = 'student'"
            );
            if ($updateStmt === false) {
                $errors[] = 'Database error preparing update: ' . $conn->error;
            } else {
                $updateStmt->bind_param('sssssi', $fullname, $enrollment, $email, $course, $status, $id);
                if ($updateStmt->execute()) {
                    $updateStmt->close();
                    $_SESSION['flash_success'] = "Student '{$fullname}' ({$enrollment}) updated successfully!";
                    header('Location: students.php');
                    exit;
                } else {
                    $errors[] = 'Failed to update student record: ' . $updateStmt->error;
                    $updateStmt->close();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student - StudentHub Portal (Practical 11)</title>
    <link rel="stylesheet" href="../pages/css/style.css">
    <style>
        .form-card {
            max-width: 680px;
            margin: 0 auto;
            background: var(--surface);
            padding: 26px;
            border-radius: 10px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 12px var(--shadow);
        }
        .form-row {
            margin-bottom: 16px;
        }
        .form-row label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
            color: var(--text);
        }
        .form-row input,
        .form-row select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font: inherit;
            background-color: var(--input-bg);
            color: var(--text);
            box-sizing: border-box;
        }
        .form-row small {
            color: var(--muted);
            font-size: 0.85em;
        }
        .form-actions {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-top: 24px;
        }
    </style>
</head>

<body>

    <header>
        <div>
            <img src="../images/logo.png" alt="StudentHub Portal Logo" width="100">
            <h1>studenthub-portal (Admin Console)</h1>
        </div>
        <button id="themeToggle" type="button" aria-label="Toggle dark/light theme">Dark Mode</button>
    </header>

    <button id="hamburger" class="hamburger" aria-label="Toggle navigation">☰</button>
    <nav id="mainNav" class="nav-menu">
        <ul>
            <li><a href="../pages/index.html">Home</a></li>
            <li><a href="../pages/admin_dashboard.php">Admin Dashboard</a></li>
            <li><a href="students.php">Manage Students (CRUD)</a></li>
            <li><a href="add-student.php">+ Add Student</a></li>
            <li><a href="../pages/contact.html">Contact</a></li>
        </ul>
    </nav>

    <main>
        <div class="form-card">
            <h2>Edit Student Record</h2>
            <p style="color: var(--muted); margin-bottom: 20px;">
                Editing Student ID: <strong><?= (int) $id ?></strong> using MySQLi prepared statements.
            </p>

            <?php if (!empty($errors)): ?>
                <div class="alert-error" role="alert">
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="edit-student.php" novalidate>
                <input type="hidden" name="id" value="<?= (int) $id ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-row">
                    <label for="fullname">Full Name <span style="color: #b13c34;">*</span></label>
                    <input type="text" id="fullname" name="fullname"
                           value="<?= htmlspecialchars($fullname, ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="e.g. Aarav Sharma" required>
                    <small>Enter the student's legal full name.</small>
                </div>

                <div class="form-row">
                    <label for="enrollment">Enrollment Number <span style="color: #b13c34;">*</span></label>
                    <input type="text" id="enrollment" name="enrollment"
                           value="<?= htmlspecialchars($enrollment, ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="e.g. 25dce055" required>
                    <small>Must be unique, 4–32 alphanumeric characters.</small>
                </div>

                <div class="form-row">
                    <label for="email">Email Address <span style="color: #b13c34;">*</span></label>
                    <input type="email" id="email" name="email"
                           value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="e.g. aarav.sharma@studenthub.edu" required>
                    <small>Unique college or personal email address.</small>
                </div>

                <div class="form-row">
                    <label for="course">Course / Department <span style="color: #b13c34;">*</span></label>
                    <select id="course" name="course" required>
                        <?php foreach ($availableCourses as $c): ?>
                            <option value="<?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>"
                                <?= $course === $c ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <label for="status">Enrollment Status <span style="color: #b13c34;">*</span></label>
                    <select id="status" name="status" required>
                        <option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option>
                        <option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update Student Details</button>
                    <a href="students.php" class="btn btn-secondary">Cancel &amp; Return</a>
                </div>
            </form>
        </div>
    </main>

    <footer>
        <p>&copy; 2026 StudentHub Portal &middot; Practical 11: Student Management CRUD with Search and Filter.</p>
    </footer>

    <script>
        // Theme toggle
        const themeBtn = document.getElementById('themeToggle');
        if (themeBtn) {
            const currentTheme = localStorage.getItem('theme') || 'light';
            document.body.classList.toggle('dark', currentTheme === 'dark');
            themeBtn.textContent = currentTheme === 'dark' ? 'Light Mode' : 'Dark Mode';

            themeBtn.addEventListener('click', () => {
                const isDark = document.body.classList.toggle('dark');
                const nextTheme = isDark ? 'dark' : 'light';
                localStorage.setItem('theme', nextTheme);
                themeBtn.textContent = isDark ? 'Light Mode' : 'Dark Mode';
            });
        }

        // Hamburger navigation menu toggle
        const hamburgerBtn = document.getElementById('hamburger');
        const mainNavMenu = document.getElementById('mainNav');
        if (hamburgerBtn && mainNavMenu) {
            hamburgerBtn.addEventListener('click', () => {
                mainNavMenu.classList.toggle('show');
            });
        }
    </script>
</body>

</html>
