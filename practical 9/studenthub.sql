CREATE DATABASE IF NOT EXISTS studenthub
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE studenthub;

CREATE TABLE IF NOT EXISTS students (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    fullname VARCHAR(120) NOT NULL,
    enrollment VARCHAR(32) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_students_enrollment (enrollment),
    UNIQUE KEY uq_students_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(180) NOT NULL,
    starts_at DATETIME NOT NULL,
    capacity INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_events_starts_at (starts_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS registrations (
    student_id INT UNSIGNED NOT NULL,
    event_id INT UNSIGNED NOT NULL,
    registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (student_id, event_id),
    CONSTRAINT fk_registrations_student
        FOREIGN KEY (student_id) REFERENCES students (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_registrations_event
        FOREIGN KEY (event_id) REFERENCES events (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    KEY idx_registrations_event (event_id)
) ENGINE=InnoDB;

INSERT INTO students (id, fullname, enrollment, email, password_hash, created_at)
VALUES
    (1, 'Tarana Joshi', '25dce034', 'joshitarana26@gmail.com', '$2y$10$Tks4zlOmIjOnU3riJWuPJeXR/zg8oFU8KCpbcPmzlb/OU4iX/kVX6', '2026-09-23 14:49:48'),
    (2, 'Tulsi', '25dce155', 'joshitarana20@gmail.com', '$2y$10$TPBfMJR1PARVVHS59831zeLaS9hNz8Sl6jGkL5RgkiosY6UeGMPA6', '2026-09-23 14:53:07')
ON DUPLICATE KEY UPDATE
    fullname = VALUES(fullname),
    email = VALUES(email),
    password_hash = VALUES(password_hash);

INSERT INTO events (id, title, description, location, starts_at, capacity)
VALUES
    (1, 'Student Welcome Meetup', 'Meet fellow students and learn about campus services.', 'Main Auditorium', '2026-10-15 10:00:00', 120),
    (2, 'Web Development Workshop', 'A hands-on introduction to accessible web development.', 'Computer Lab 2', '2026-10-22 13:30:00', 40),
    (3, 'Campus Tech Talk', 'An invited speaker discusses current software engineering practices.', 'Seminar Hall', '2026-11-05 11:00:00', 80)
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    description = VALUES(description),
    location = VALUES(location),
    starts_at = VALUES(starts_at),
    capacity = VALUES(capacity);

INSERT IGNORE INTO registrations (student_id, event_id)
VALUES (1, 1), (1, 2), (2, 1), (2, 3);
