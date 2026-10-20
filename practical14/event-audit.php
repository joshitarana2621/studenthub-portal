<?php

declare(strict_types=1);

/**
 * StudentHub - Practical 12: Event Management CRUD with Poster Upload and File Validation
 * Module: Event Audit Log View (Administrative History)
 */

require_once __DIR__ . '/../api/auth.php';
require_once __DIR__ . '/../api/db.php';

// Server-side authentication and role guard: only administrators allowed
studenthub_require_role('admin', $pdo);

$adminFullname = (string) ($_SESSION['fullname'] ?? 'Administrator');

// Fetch recent audit log records
$auditSql = "SELECT id, event_id, event_title, admin_id, admin_name, action_type, details, created_at 
             FROM event_audit_log 
             ORDER BY id DESC 
             LIMIT 50";
$auditResult = $conn->query($auditSql);
$logs = [];
if ($auditResult) {
    while ($row = $auditResult->fetch_assoc()) {
        $logs[] = $row;
    }
    $auditResult->free();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Audit Log - StudentHub Portal</title>
    <link rel="stylesheet" href="../pages/css/style.css">
    <style>
        .badge-create { background: #dcfce7; color: #166534; padding: 3px 8px; border-radius: 4px; font-size: 0.82em; font-weight: bold; }
        .badge-update { background: #e0f2fe; color: #0369a1; padding: 3px 8px; border-radius: 4px; font-size: 0.82em; font-weight: bold; }
        .badge-delete { background: #fee2e2; color: #991b1b; padding: 3px 8px; border-radius: 4px; font-size: 0.82em; font-weight: bold; }
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
            <li><a href="students.php">Manage Students</a></li>
            <li><a href="events.php">Manage Events</a></li>
            <li><a href="add-event.php">+ Add Event</a></li>
            <li><a href="event-audit.php" class="active">Audit Log</a></li>
            <li><a href="../pages/contact.html">Contact</a></li>
        </ul>
    </nav>

    <main>
        <section>
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h2>Event Administration Audit Log</h2>
                    <p style="margin: 0; color: var(--muted);">
                        Historical log tracking event creation, updates, poster replacements, and deletions.
                    </p>
                </div>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <a href="events.php" class="btn btn-primary">&larr; Return to Events</a>
                    <a href="../pages/admin_dashboard.php" class="btn btn-secondary">Admin Dashboard</a>
                </div>
            </div>
        </section>

        <section>
            <h3>Recent Administrative Actions (Latest 50)</h3>

            <?php if (empty($logs)): ?>
                <div class="empty-state">
                    <h4>No Audit Log Records Yet</h4>
                    <p>Actions performed in the Event Management module will automatically be logged here.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="student-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">Log ID</th>
                                <th>Timestamp</th>
                                <th>Administrator</th>
                                <th>Action</th>
                                <th>Event Title</th>
                                <th>Action Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><strong>#<?= (int) $log['id'] ?></strong></td>
                                    <td><?= htmlspecialchars(date('d-M-Y h:i:s A', strtotime((string) $log['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) $log['admin_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?php if ($log['action_type'] === 'CREATE'): ?>
                                            <span class="badge-create">CREATE</span>
                                        <?php elseif ($log['action_type'] === 'UPDATE'): ?>
                                            <span class="badge-update">UPDATE</span>
                                        <?php else: ?>
                                            <span class="badge-delete">DELETE</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?= htmlspecialchars((string) $log['event_title'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                    <td><?= htmlspecialchars((string) $log['details'], ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        <p>&copy; 2026 StudentHub Portal &middot; Practical 12: Event Management CRUD with Poster Upload and File Validation.</p>
    </footer>

    <script>
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
