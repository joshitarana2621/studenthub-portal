-- ==============================================================================
-- StudentHub Practical 13: Database Schema & Migration Notes
-- Practical Title: PHP JSON API and AJAX/Fetch-Based CRUD Without Page Reload
-- Subject: Web Development Frameworks (ITUE203) - Semester 3, CHARUSAT
-- ==============================================================================

USE studenthub;

-- 1. Ensure `events` table exists with complete columns supporting both
--    traditional CRUD (Practical 12) and asynchronous JSON API (Practical 13)
CREATE TABLE IF NOT EXISTS events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(60) NOT NULL DEFAULT 'Workshop',
    location VARCHAR(180) NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NULL DEFAULT NULL,
    capacity INT UNSIGNED NULL DEFAULT NULL,
    status ENUM('Open', 'Closed') NOT NULL DEFAULT 'Open',
    poster_image VARCHAR(255) NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_events_starts_at (starts_at),
    KEY idx_events_category (category),
    KEY idx_events_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Optional sample event records for testing Practical 13 Fetch API CRUD
--    (INSERT IGNORE ensures existing records and Practical 1-12 data remain untouched)
INSERT IGNORE INTO events (id, title, description, category, location, starts_at, ends_at, capacity, status)
VALUES
    (101, 'Python & Data Analysis Bootcamp', 'Hands-on training covering NumPy, Pandas, and Matplotlib data visualization techniques.', 'Workshop', 'Computer Lab 4', '2026-11-18 10:00:00', '2026-11-18 14:00:00', 60, 'Open'),
    (102, 'Full-Stack JavaScript & APIs', 'Learn modern asynchronous JS, Fetch API, and REST integration patterns.', 'Technical', 'DEPSTAR Seminar Hall', '2026-11-24 13:30:00', '2026-11-24 16:30:00', 80, 'Open'),
    (103, 'Annual Robotics Exhibition', 'Showcase of autonomous bot navigation, microcontrollers, and IoT sensor prototypes.', 'Exhibition', 'Main Campus Foyer', '2026-12-05 09:30:00', '2026-12-05 17:00:00', 250, 'Open');
