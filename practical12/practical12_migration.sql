-- ==============================================================================
-- StudentHub Practical 12: Database Migration
-- Title: Event Management CRUD with Poster Upload and File Validation
-- Safe migration: preserves all existing events, registrations, and schema.
-- ==============================================================================

USE studenthub;

-- 1. Add 'category' column if it does not already exist
ALTER TABLE events
    ADD COLUMN IF NOT EXISTS category VARCHAR(60) NOT NULL DEFAULT 'Workshop' AFTER description;

-- 2. Add 'ends_at' column if it does not already exist
ALTER TABLE events
    ADD COLUMN IF NOT EXISTS ends_at DATETIME NULL DEFAULT NULL AFTER starts_at;

-- 3. Add 'status' column if it does not already exist
ALTER TABLE events
    ADD COLUMN IF NOT EXISTS status ENUM('Open', 'Closed') NOT NULL DEFAULT 'Open' AFTER capacity;

-- 4. Add 'poster_image' column if it does not already exist
ALTER TABLE events
    ADD COLUMN IF NOT EXISTS poster_image VARCHAR(255) NULL DEFAULT NULL AFTER status;

-- 5. Add 'updated_at' column if it does not already exist
ALTER TABLE events
    ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- 6. Create event_audit_log table for tracking administrative changes
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
) ENGINE=InnoDB;

-- 7. Populate realistic categories and end times for existing events
UPDATE events
SET category = 'Orientation',
    ends_at = DATE_ADD(starts_at, INTERVAL 2 HOUR),
    status = 'Open'
WHERE id = 1;

UPDATE events
SET category = 'Workshop',
    ends_at = DATE_ADD(starts_at, INTERVAL 3 HOUR),
    status = 'Open'
WHERE id = 2;

UPDATE events
SET category = 'Seminar',
    ends_at = DATE_ADD(starts_at, INTERVAL 2 HOUR),
    status = 'Open'
WHERE id = 3;
