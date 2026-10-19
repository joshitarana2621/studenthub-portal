<?php

declare(strict_types=1);

/**
 * StudentHub - Practical 13: AJAX & Fetch API Event Management Interface
 *
 * Full Single-Page Application (SPA) style CRUD interface operating without full page reloads:
 * - Communicates with /api/events.php via vanilla JavaScript Fetch API
 * - Asynchronous Create, Read, Update, Delete with inline DOM updates
 * - Live search with input debouncing and category/status filtering
 * - Server-side authentication guard for administrators
 */

require_once __DIR__ . '/../api/auth.php';
require_once __DIR__ . '/../api/db.php';

// Server-side authentication and role guard: only administrators allowed
studenthub_require_role('admin', $pdo);

$adminFullname = (string) ($_SESSION['fullname'] ?? 'Administrator');
$adminEnrollment = (string) ($_SESSION['enrollment'] ?? 'ADMIN');
$csrfToken = studenthub_get_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AJAX Event Management - StudentHub Portal</title>
    <link rel="stylesheet" href="../pages/css/style.css">
    <style>
        /* Modern styles for Practical 13 AJAX Module */
        .ajax-header-meta {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .practical-badge {
            background: #4f46e5;
            color: #ffffff;
            font-size: 0.82em;
            padding: 4px 10px;
            border-radius: 999px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .no-reload-pill {
            background: #10b981;
            color: #ffffff;
            font-size: 0.78em;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 600;
            margin-left: 8px;
        }

        /* Tab Navigation Bar */
        .module-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 8px;
            flex-wrap: wrap;
        }
        .module-tab-btn {
            padding: 10px 18px;
            font-size: 0.95em;
            font-weight: 600;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.2s ease;
            color: #475569;
            background: #f1f5f9;
        }
        .module-tab-btn:hover {
            background: #e2e8f0;
            color: #0f172a;
        }
        .module-tab-btn.active {
            background: #003366;
            color: #ffffff;
            box-shadow: 0 2px 4px rgba(0,51,102,0.2);
        }

        /* Metric Cards */
        .metric-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .metric-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .metric-card h4 {
            margin: 0 0 6px 0;
            color: #64748b;
            font-size: 0.85em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .metric-card .metric-val {
            font-size: 1.8em;
            font-weight: bold;
            color: #0f172a;
            margin: 0;
        }

        /* Notification Banners */
        .alert-container {
            margin-bottom: 20px;
        }
        .alert-banner {
            padding: 14px 18px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 0.95em;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
            transition: opacity 0.3s ease;
        }
        .alert-success {
            background-color: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .alert-error {
            background-color: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .alert-close {
            background: transparent;
            border: none;
            font-size: 1.3em;
            cursor: pointer;
            color: inherit;
            margin-left: 12px;
            line-height: 1;
        }

        /* Search & Filter Toolbar */
        .toolbar-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .toolbar-flex {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        .search-box {
            flex: 2;
            min-width: 240px;
            position: relative;
        }
        .search-box input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 0.95em;
            box-sizing: border-box;
        }
        .filter-select {
            padding: 10px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 0.95em;
            background: #fff;
            min-width: 140px;
        }
        .btn-reset-filters {
            padding: 10px 16px;
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.2s;
        }
        .btn-reset-filters:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        /* AJAX Form Card */
        .form-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 28px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border-top: 4px solid #003366;
            transition: border-color 0.3s ease;
        }
        .form-card.edit-active {
            border-top-color: #d97706;
        }
        .form-card h3 {
            margin-top: 0;
            margin-bottom: 16px;
            color: #003366;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 16px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        .form-group label {
            font-weight: 600;
            font-size: 0.9em;
            color: #334155;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 0.95em;
            box-sizing: border-box;
            background: #fff;
            font-family: inherit;
        }
        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }
        .form-actions {
            margin-top: 20px;
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .btn-primary-ajax {
            padding: 12px 24px;
            background: #003366;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.95em;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
        }
        .btn-primary-ajax:hover {
            background: #002244;
        }
        .btn-primary-ajax:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .btn-update-mode {
            background: #d97706 !important;
        }
        .btn-update-mode:hover {
            background: #b45309 !important;
        }
        .btn-cancel-edit {
            padding: 12px 20px;
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            display: none;
        }
        .btn-cancel-edit:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        /* Events Table Styling */
        .table-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            position: relative;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .events-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.95em;
        }
        .events-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            padding: 12px 14px;
            border-bottom: 2px solid #e2e8f0;
        }
        .events-table td {
            padding: 14px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        .event-row:hover {
            background: #f8fafc;
        }
        .event-row.row-editing {
            background: #fffbeb !important;
            border-left: 4px solid #d97706;
        }
        .event-title-text {
            color: #0f172a;
            font-size: 1.05em;
            display: block;
            margin-bottom: 4px;
        }
        .event-desc-text {
            color: #64748b;
            font-size: 0.88em;
            margin: 0;
            line-height: 1.4;
            max-width: 320px;
        }
        .category-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 0.82em;
            font-weight: 600;
            background: #e0e7ff;
            color: #3730a3;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.82em;
            font-weight: 700;
        }
        .status-open {
            background: #ecfdf5;
            color: #065f46;
        }
        .status-closed {
            background: #fef2f2;
            color: #991b1b;
        }
        .col-actions {
            white-space: nowrap;
        }
        .btn-action {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: 600;
            cursor: pointer;
            margin-right: 6px;
            border: none;
            transition: background 0.15s;
        }
        .btn-edit {
            background: #e0f2fe;
            color: #0369a1;
        }
        .btn-edit:hover {
            background: #bae6fd;
        }
        .btn-delete {
            background: #fee2e2;
            color: #b91c1c;
        }
        .btn-delete:hover {
            background: #fecaca;
        }

        /* Loading Spinner Overlay */
        .loading-overlay {
            display: none;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 16px;
            background: rgba(248, 250, 252, 0.9);
            border-radius: 6px;
            margin-bottom: 16px;
            font-weight: 600;
            color: #003366;
        }
        .spinner {
            width: 22px;
            height: 22px;
            border: 3px solid #cbd5e1;
            border-top: 3px solid #003366;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Empty State */
        .empty-state {
            display: none;
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
        }
        .empty-state h4 {
            margin: 0 0 8px 0;
            color: #334155;
            font-size: 1.2em;
        }

        /* Educational Footer Note */
        .edu-note-box {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-left: 4px solid #4f46e5;
            border-radius: 6px;
            padding: 16px 20px;
            margin-top: 28px;
            font-size: 0.9em;
            color: #334155;
            line-height: 1.6;
        }
        .edu-note-box code {
            background: #e2e8f0;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: Consolas, monospace;
            font-size: 0.95em;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <header>
        <div>
            <img src="../images/logo.png" alt="StudentHub Portal Logo" width="100">
            <h1>StudentHub Portal</h1>
        </div>
        <h2>Campus Event Management</h2>
    </header>

    <!-- Navigation -->
    <nav class="nav-menu" aria-label="Main navigation">
        <ul>
            <li><a href="../pages/index.html">Home</a></li>
            <li><a href="../pages/admin_dashboard.php">Dashboard</a></li>
            <li><a href="students.php">Manage Students</a></li>
            <li><a href="events.php">Manage Events</a></li>
            <li><a href="events-ajax.php" class="active" style="color: #afdcf6; font-weight: bold;">AJAX Events</a></li>
            <li><a href="../pages/contact.html">Contact</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main style="max-width: 1200px; margin: 30px auto; padding: 0 20px;">

        <!-- Top Meta Section -->
        <div class="ajax-header-meta">
            <div>
                <span class="no-reload-pill">⚡ No Page Reload</span>
                <h2 style="margin: 8px 0 0 0; color: #003366;">PHP JSON API &amp; Fetch API CRUD</h2>
                <small style="color: #64748b;">Logged in as: <strong><?= htmlspecialchars($adminFullname, ENT_QUOTES, 'UTF-8') ?></strong> (<?= htmlspecialchars($adminEnrollment, ENT_QUOTES, 'UTF-8') ?>)</small>
            </div>
            <div>
                <a href="../pages/admin_dashboard.php" style="color: #003366; font-weight: 600; text-decoration: none;">&larr; Back to Dashboard</a>
            </div>
        </div>

        <!-- Navigation Tabs between P12 and P13 -->
        <div class="module-tabs">
            <a href="events-ajax.php" class="module-tab-btn active">⚡ AJAX / Fetch CRUD (No Page Reload)</a>
            <a href="events.php" class="module-tab-btn">📁 Traditional CRUD &amp; Poster Upload</a>
            <a href="event-audit.php" class="module-tab-btn">📜 Event Audit Log</a>
        </div>

        <!-- Alert Notification Banners Container -->
        <div id="alertContainer" class="alert-container" aria-live="polite"></div>

        <!-- Metric Summary Cards -->
        <div class="metric-cards-grid">
            <div class="metric-card">
                <h4>Total Events</h4>
                <p id="statTotal" class="metric-val">0</p>
            </div>
            <div class="metric-card">
                <h4>Open Events</h4>
                <p id="statOpen" class="metric-val" style="color: #059669;">0</p>
            </div>
            <div class="metric-card">
                <h4>Closed Events</h4>
                <p id="statClosed" class="metric-val" style="color: #dc2626;">0</p>
            </div>
        </div>

        <!-- Section 1: Add / Edit Event Form (Submits via AJAX POST or PUT) -->
        <div class="form-card" id="formCard">
            <h3 id="formTitle">Schedule New Campus Event</h3>
            <form id="eventForm" novalidate>
                <!-- Hidden Event ID (Populated only during Edit mode) -->
                <input type="hidden" id="eventId" name="id" value="">

                <div class="form-grid">
                    <!-- Title -->
                    <div class="form-group full-width">
                        <label for="eventTitle">Event Title <span style="color: #dc2626;">*</span></label>
                        <input type="text" id="eventTitle" name="title" placeholder="e.g. Campus Hackathon 2026" required maxlength="150">
                    </div>

                    <!-- Category -->
                    <div class="form-group">
                        <label for="eventCategory">Category <span style="color: #dc2626;">*</span></label>
                        <select id="eventCategory" name="category" required>
                            <option value="Workshop">Workshop</option>
                            <option value="Seminar">Seminar</option>
                            <option value="Technical">Technical</option>
                            <option value="Cultural">Cultural</option>
                            <option value="Sports">Sports</option>
                            <option value="Orientation">Orientation</option>
                            <option value="Guest Lecture">Guest Lecture</option>
                        </select>
                    </div>

                    <!-- Venue / Location -->
                    <div class="form-group">
                        <label for="eventLocation">Venue / Location <span style="color: #dc2626;">*</span></label>
                        <input type="text" id="eventLocation" name="location" placeholder="e.g. Main Auditorium, Lab 2" required maxlength="180">
                    </div>

                    <!-- Event Date & Start Time -->
                    <div class="form-group">
                        <label for="eventDate">Event Date &amp; Start Time <span style="color: #dc2626;">*</span></label>
                        <input type="datetime-local" id="eventDate" name="event_date" required>
                    </div>

                    <!-- Optional End Date & Time -->
                    <div class="form-group">
                        <label for="eventEndsAt">End Date &amp; Time (Optional)</label>
                        <input type="datetime-local" id="eventEndsAt" name="ends_at">
                    </div>

                    <!-- Capacity -->
                    <div class="form-group">
                        <label for="eventCapacity">Attendee Capacity</label>
                        <input type="number" id="eventCapacity" name="capacity" min="1" max="10000" placeholder="e.g. 100">
                    </div>

                    <!-- Status -->
                    <div class="form-group">
                        <label for="eventStatus">Event Status <span style="color: #dc2626;">*</span></label>
                        <select id="eventStatus" name="status" required>
                            <option value="Open">Open (Accepting Registrations)</option>
                            <option value="Closed">Closed (Registration Closed)</option>
                        </select>
                    </div>

                    <!-- Description -->
                    <div class="form-group full-width">
                        <label for="eventDescription">Description <span style="color: #dc2626;">*</span></label>
                        <textarea id="eventDescription" name="description" placeholder="Provide full event overview, agenda, eligibility, or guidelines..." required></textarea>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="form-actions">
                    <button type="submit" id="submitBtn" class="btn-primary-ajax">Add Event</button>
                    <button type="button" id="cancelBtn" class="btn-cancel-edit">Cancel Edit</button>
                </div>
            </form>
        </div>

        <!-- Section 2: Search & Filter Toolbar -->
        <div class="toolbar-card">
            <div class="toolbar-flex">
                <div class="search-box">
                    <input type="text" id="eventSearchInput" placeholder="🔍 Search events by title, venue, or category... (debounced)">
                </div>
                <div>
                    <select id="categoryFilter" class="filter-select">
                        <option value="">All Categories</option>
                        <option value="Workshop">Workshop</option>
                        <option value="Seminar">Seminar</option>
                        <option value="Technical">Technical</option>
                        <option value="Cultural">Cultural</option>
                        <option value="Sports">Sports</option>
                        <option value="Orientation">Orientation</option>
                        <option value="Guest Lecture">Guest Lecture</option>
                    </select>
                </div>
                <div>
                    <select id="statusFilter" class="filter-select">
                        <option value="">All Statuses</option>
                        <option value="Open">Open</option>
                        <option value="Closed">Closed</option>
                    </select>
                </div>
                <div>
                    <button type="button" id="clearFiltersBtn" class="btn-reset-filters">Reset Filters</button>
                </div>
            </div>
        </div>

        <!-- Section 3: Live Events Table (Updated dynamically via Fetch API) -->
        <div class="table-card">
            <h3 style="margin-top: 0; color: #003366;">Campus Events Directory</h3>

            <!-- Loading Spinner Indicator -->
            <div id="loadingIndicator" class="loading-overlay">
                <div class="spinner"></div>
                <span>Communicating with JSON API...</span>
            </div>

            <!-- Table -->
            <div class="table-responsive">
                <table class="events-table">
                    <thead>
                        <tr>
                            <th style="width: 60px;">ID</th>
                            <th>Event Title &amp; Description</th>
                            <th style="width: 120px;">Category</th>
                            <th style="width: 150px;">Venue</th>
                            <th style="width: 170px;">Date &amp; Time</th>
                            <th style="width: 90px;">Status</th>
                            <th style="width: 130px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="eventsTableBody">
                        <!-- Rows rendered dynamically by js/events.js without page reload -->
                    </tbody>
                </table>
            </div>

            <!-- Empty State -->
            <div id="emptyState" class="empty-state">
                <h4>No Events Found</h4>
                <p>No matching campus events were found in the database. Use the form above to schedule a new event.</p>
            </div>
        </div>

        <!-- Educational Note for Practical File / Evaluator -->
        <div class="edu-note-box">
            <strong>🎓 Practical 13 Architecture Summary:</strong>
            <p style="margin: 6px 0 0 0;">
                All CRUD operations on this page communicate with the backend RESTful endpoint at <code>/api/events.php</code> using the native JavaScript <strong>Fetch API</strong>.
                Form submissions (<code>POST</code>), in-place updates (<code>PUT</code>), and removals (<code>DELETE</code>) happen asynchronously <strong>without a full page reload</strong>.
                Press <code>F12</code> &rarr; <strong>Network</strong> tab to observe the asynchronous HTTP requests and JSON response payloads in real-time.
            </p>
        </div>

    </main>

    <!-- Footer -->
    <footer>
        <p>&copy; 2026 StudentHub Portal. Web Development Practical 13 (CHARUSAT).</p>
    </footer>

    <!-- JavaScript: Practical 13 Fetch API Module -->
    <script src="../js/events.js"></script>

</body>
</html>
