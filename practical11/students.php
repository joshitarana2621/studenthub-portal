<?php

declare(strict_types=1);

/**
 * StudentHub - Practical 11: Student Management CRUD with Search and Filter
 * Module: Student Listing, Search, Filter & Pagination
 */

require_once __DIR__ . '/../api/auth.php';
require_once __DIR__ . '/../api/db.php';

// Server-side authentication and role guard: only administrators allowed
studenthub_require_role('admin', $pdo);

$adminFullname = (string) ($_SESSION['fullname'] ?? 'Administrator');
$adminEnrollment = (string) ($_SESSION['enrollment'] ?? 'ADMIN');
$csrfToken = studenthub_get_csrf_token();

// Retrieve and clear session flash messages
$flashSuccess = (string) ($_SESSION['flash_success'] ?? '');
$flashError = (string) ($_SESSION['flash_error'] ?? '');
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Request parameters for Search, Filter, and Pagination
$search = trim((string) ($_GET['search'] ?? ''));
$courseFilter = trim((string) ($_GET['course'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$page = (int) ($_GET['page'] ?? 1);
if ($page < 1) {
    $page = 1;
}
$recordsPerPage = 10;

// Fetch dynamic courses for the filter dropdown using MySQLi
$courses = [];
$courseQuery = "SELECT DISTINCT course FROM students WHERE course IS NOT NULL AND course != '' AND role = 'student' ORDER BY course ASC";
$courseResult = $conn->query($courseQuery);
if ($courseResult) {
    while ($cRow = $courseResult->fetch_assoc()) {
        $courses[] = (string) $cRow['course'];
    }
    $courseResult->free();
}

// Build dynamic WHERE clause using parameterized MySQLi statements
$whereClauses = ["role = 'student'"];
$paramTypes = '';
$paramValues = [];

if ($search !== '') {
    $whereClauses[] = "(fullname LIKE ? OR enrollment LIKE ? OR email LIKE ? OR course LIKE ?)";
    $paramTypes .= 'ssss';
    $likeSearch = '%' . $search . '%';
    $paramValues[] = $likeSearch;
    $paramValues[] = $likeSearch;
    $paramValues[] = $likeSearch;
    $paramValues[] = $likeSearch;
}

if ($courseFilter !== '') {
    $whereClauses[] = "course = ?";
    $paramTypes .= 's';
    $paramValues[] = $courseFilter;
}

if ($statusFilter !== '') {
    $whereClauses[] = "status = ?";
    $paramTypes .= 's';
    $paramValues[] = $statusFilter;
}

$whereSql = 'WHERE ' . implode(' AND ', $whereClauses);

// 1. COUNT Query to determine pagination bounds
$countSql = "SELECT COUNT(*) AS total FROM students {$whereSql}";
$countStmt = $conn->prepare($countSql);
if ($countStmt === false) {
    die("Database error preparing count statement: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8'));
}

if ($paramTypes !== '') {
    $countStmt->bind_param($paramTypes, ...$paramValues);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalRecords = (int) ($countResult->fetch_assoc()['total'] ?? 0);
$countStmt->close();

$totalPages = max(1, (int) ceil($totalRecords / $recordsPerPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $recordsPerPage;

// 2. DATA Query for the current page
$dataSql = "SELECT id, fullname, enrollment, email, course, status, created_at FROM students {$whereSql} ORDER BY id DESC LIMIT ? OFFSET ?";
$dataStmt = $conn->prepare($dataSql);
if ($dataStmt === false) {
    die("Database error preparing data statement: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8'));
}

$dataParamTypes = $paramTypes . 'ii';
$dataParamValues = array_merge($paramValues, [$recordsPerPage, $offset]);
$dataStmt->bind_param($dataParamTypes, ...$dataParamValues);
$dataStmt->execute();
$dataResult = $dataStmt->get_result();

$students = [];
while ($row = $dataResult->fetch_assoc()) {
    $students[] = $row;
}
$dataStmt->close();

// Helper to preserve active query parameters in pagination links
function getPaginationLink(int $targetPage, string $search, string $course, string $status): string
{
    $params = ['page' => $targetPage];
    if ($search !== '') {
        $params['search'] = $search;
    }
    if ($course !== '') {
        $params['course'] = $course;
    }
    if ($status !== '') {
        $params['status'] = $status;
    }
    return 'students.php?' . http_build_query($params);
}

$hasActiveFilters = ($search !== '' || $courseFilter !== '' || $statusFilter !== '');
$rangeStart = $totalRecords === 0 ? 0 : $offset + 1;
$rangeEnd = min($offset + $recordsPerPage, $totalRecords);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management - StudentHub Portal (Practical 11)</title>
    <link rel="stylesheet" href="../pages/css/style.css">
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
            <li><a href="students.php" class="active">Manage Students (CRUD)</a></li>
            <li><a href="add-student.php">+ Add Student</a></li>
            <li><a href="../pages/contact.html">Contact</a></li>
        </ul>
    </nav>

    <main>
        <section>
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h2>Student Management Module</h2>
                    <p style="margin: 0; color: var(--muted);">
                        Logged in as: <strong><?= htmlspecialchars($adminFullname, ENT_QUOTES, 'UTF-8') ?></strong> (<?= htmlspecialchars($adminEnrollment, ENT_QUOTES, 'UTF-8') ?>) &middot;
                        <span class="badge-active">Administrator</span>
                    </p>
                </div>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <a href="add-student.php" class="btn btn-primary">+ Add New Student</a>
                    <a href="../pages/admin_dashboard.php" class="btn btn-secondary">&larr; Admin Dashboard</a>
                </div>
            </div>
        </section>

        <!-- Session Flash Messages -->
        <?php if ($flashSuccess !== ''): ?>
            <div class="alert-success" role="alert">
                <?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if ($flashError !== ''): ?>
            <div class="alert-error" role="alert">
                <?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <!-- Search and Filter Form -->
        <section class="filter-card">
            <h3>Search &amp; Filter Students</h3>
            <form method="GET" action="students.php" class="filter-form">
                <div class="filter-group" style="flex: 2 1 280px;">
                    <label for="search">Search Keywords</label>
                    <input type="text" id="search" name="search"
                           value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="Search by full name, enrollment, email, or course...">
                </div>

                <div class="filter-group">
                    <label for="course">Filter by Course</label>
                    <select id="course" name="course">
                        <option value="">-- All Courses --</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>"
                                <?= $courseFilter === $c ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="status">Filter by Status</label>
                    <select id="status" name="status">
                        <option value="">-- All Statuses --</option>
                        <option value="Active" <?= $statusFilter === 'Active' ? 'selected' : '' ?>>Active</option>
                        <option value="Inactive" <?= $statusFilter === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <?php if ($hasActiveFilters): ?>
                        <a href="students.php" class="btn btn-secondary">Reset Filters</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <!-- Student Records Table Section -->
        <section>
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <h3>Student Records List</h3>
                <span style="color: var(--muted); font-size: 0.95em;">
                    Showing <strong><?= $rangeStart ?></strong>&ndash;<strong><?= $rangeEnd ?></strong> of <strong><?= $totalRecords ?></strong> student<?= $totalRecords === 1 ? '' : 's' ?>
                </span>
            </div>

            <?php if (empty($students)): ?>
                <div class="empty-state">
                    <h4>No Student Records Found</h4>
                    <?php if ($hasActiveFilters): ?>
                        <p>No students match your active search and filter criteria.</p>
                        <a href="students.php" class="btn btn-secondary">Clear Search &amp; Filters</a>
                    <?php else: ?>
                        <p>No student records are currently registered in the database.</p>
                        <a href="add-student.php" class="btn btn-primary">+ Add the First Student</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="student-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">ID</th>
                                <th>Full Name</th>
                                <th>Enrollment No.</th>
                                <th>Email Address</th>
                                <th>Course / Department</th>
                                <th>Status</th>
                                <th>Registration Date</th>
                                <th style="width: 200px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $s): ?>
                                <tr>
                                    <td><strong><?= (int) $s['id'] ?></strong></td>
                                    <td><?= htmlspecialchars((string) $s['fullname'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><code><?= htmlspecialchars((string) $s['enrollment'], ENT_QUOTES, 'UTF-8') ?></code></td>
                                    <td><?= htmlspecialchars((string) $s['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) $s['course'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?php if ($s['status'] === 'Active'): ?>
                                            <span class="badge-active">Active</span>
                                        <?php else: ?>
                                            <span class="badge-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars(date('d-M-Y', strtotime((string) $s['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <div class="table-actions">
                                            <button type="button" class="btn btn-secondary btn-sm"
                                                    onclick="openViewModal(<?= htmlspecialchars(json_encode($s), ENT_QUOTES, 'UTF-8') ?>)">
                                                View
                                            </button>

                                            <a href="edit-student.php?id=<?= (int) $s['id'] ?>" class="btn btn-primary btn-sm">
                                                Edit
                                            </a>

                                            <form method="POST" action="delete-student.php" style="display: inline; margin: 0;"
                                                  onsubmit="return confirm('Are you sure you want to permanently delete student: \'<?= htmlspecialchars(addslashes((string)$s['fullname']), ENT_QUOTES, 'UTF-8') ?>\' (<?= htmlspecialchars((string)$s['enrollment'], ENT_QUOTES, 'UTF-8') ?>)? This cannot be undone.');">
                                                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="course" value="<?= htmlspecialchars($courseFilter, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="page" value="<?= $page ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Server-Side Pagination Bar -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination-container">
                        <div>
                            <span>Page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong></span>
                        </div>
                        <div class="pagination-links">
                            <!-- Previous Link -->
                            <?php if ($page > 1): ?>
                                <a href="<?= getPaginationLink($page - 1, $search, $courseFilter, $statusFilter) ?>"
                                   class="pagination-link">&laquo; Previous</a>
                            <?php else: ?>
                                <span class="pagination-link disabled">&laquo; Previous</span>
                            <?php endif; ?>

                            <!-- Numbered Page Links -->
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="<?= getPaginationLink($i, $search, $courseFilter, $statusFilter) ?>"
                                   class="pagination-link <?= $i === $page ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <!-- Next Link -->
                            <?php if ($page < $totalPages): ?>
                                <a href="<?= getPaginationLink($page + 1, $search, $courseFilter, $statusFilter) ?>"
                                   class="pagination-link">Next &raquo;</a>
                            <?php else: ?>
                                <span class="pagination-link disabled">Next &raquo;</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>

    <!-- Student Detail Modal -->
    <div id="viewModal" class="modal-overlay" onclick="closeViewModal(event)">
        <div class="modal-card" onclick="event.stopPropagation()">
            <h3>Student Record Details</h3>
            <div id="modalBody">
                <!-- Dynamically populated via JS -->
            </div>
            <div style="text-align: right; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; 2026 StudentHub Portal &middot; Practical 11: Student Management CRUD with Search and Filter.</p>
    </footer>

    <script>
        // Modal logic for viewing student details
        function openViewModal(student) {
            const modalBody = document.getElementById('modalBody');
            const createdDate = new Date(student.created_at).toLocaleDateString('en-IN', {
                day: 'numeric',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });

            const statusBadge = student.status === 'Active'
                ? '<span class="badge-active">Active</span>'
                : '<span class="badge-inactive">Inactive</span>';

            modalBody.innerHTML = `
                <div class="detail-row"><strong>Student ID:</strong> <span>${escapeHtml(String(student.id))}</span></div>
                <div class="detail-row"><strong>Full Name:</strong> <span>${escapeHtml(student.fullname)}</span></div>
                <div class="detail-row"><strong>Enrollment:</strong> <code>${escapeHtml(student.enrollment)}</code></div>
                <div class="detail-row"><strong>Email:</strong> <span>${escapeHtml(student.email)}</span></div>
                <div class="detail-row"><strong>Course:</strong> <span>${escapeHtml(student.course)}</span></div>
                <div class="detail-row"><strong>Status:</strong> <span>${statusBadge}</span></div>
                <div class="detail-row"><strong>Registered On:</strong> <span>${createdDate}</span></div>
            `;

            document.getElementById('viewModal').classList.add('active');
        }

        function closeViewModal(event) {
            if (!event || event.target.id === 'viewModal') {
                document.getElementById('viewModal').classList.remove('active');
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Theme toggle support matching script.js
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
