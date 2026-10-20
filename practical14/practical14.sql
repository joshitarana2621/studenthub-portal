-- ==============================================================================
-- StudentHub Practical 14: Database Schema & Migration Notes
-- Practical Title: Admin Dashboard with Role-Based Access Control, Dynamic Menu, and Audit Log
-- Course: Web Development Frameworks (ITUE203) - Semester 3, CHARUSAT
-- ==============================================================================

USE studenthub;

-- 1. Ensure students table has 'status' and 'role' columns for RBAC and status toggles
ALTER TABLE students 
    ADD COLUMN IF NOT EXISTS status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active' AFTER role;

-- 2. Ensure registrations table exists with relationships to students and events
CREATE TABLE IF NOT EXISTS registrations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    event_id INT UNSIGNED NOT NULL,
    registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Confirmed', 'Cancelled') NOT NULL DEFAULT 'Confirmed',
    UNIQUE KEY uq_student_event (student_id, event_id),
    KEY idx_reg_student (student_id),
    KEY idx_reg_event (event_id),
    CONSTRAINT fk_reg_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
    CONSTRAINT fk_reg_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Ensure event_audit_log table exists for tracing admin actions
CREATE TABLE IF NOT EXISTS event_audit_log (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NULL,
    event_title VARCHAR(150) NOT NULL,
    admin_id INT UNSIGNED NULL,
    admin_name VARCHAR(120) NOT NULL,
    action_type ENUM('CREATE', 'UPDATE', 'DELETE') NOT NULL,
    details TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_event (event_id),
    KEY idx_audit_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Sample audit trail entries for testing
INSERT IGNORE INTO event_audit_log (id, event_id, event_title, admin_id, admin_name, action_type, details, created_at)
VALUES
    (1, 1, 'Orientation 2026', 1, 'System Administrator', 'CREATE', 'Created initial orientation event record.', '2026-08-10 10:00:00'),
    (2, 2, 'Web Development Bootcamp', 1, 'System Administrator', 'CREATE', 'Created web development workshop.', '2026-08-12 11:00:00'),
    (3, 4, 'Annual Hackathon: CodeSprint 2026', 1, 'System Administrator', 'CREATE', 'Created flagship annual hackathon event.', '2026-08-20 09:15:00');
