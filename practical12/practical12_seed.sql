-- ==============================================================================
-- StudentHub Practical 12: Seed Data Script
-- Title: Event Management CRUD with Poster Upload and File Validation
-- Provides 10 realistic sample events across multiple categories and statuses.
-- ==============================================================================

USE studenthub;

INSERT INTO events (id, title, description, category, location, starts_at, ends_at, capacity, status, created_at)
VALUES
    (10, 'Campus Hackathon 2026', 'A 24-hour coding sprint solving real-world challenges with innovative software solutions.', 'Technical', 'Computer Center Lab 1 & 2', '2026-11-12 09:00:00', '2026-11-13 09:00:00', 100, 'Open', '2026-10-01 10:00:00'),
    (11, 'Annual Cultural Fest - Spandan', 'Celebrate music, dance, theater, and arts across various inter-department competitions.', 'Cultural', 'Main Campus Ground', '2026-12-18 16:00:00', '2026-12-19 22:00:00', 500, 'Open', '2026-10-02 11:30:00'),
    (12, 'AI & Machine Learning Symposium', 'Distinguished industry experts talk on deep learning, generative AI, and emerging tech careers.', 'Seminar', 'Dr. APJ Abdul Kalam Auditorium', '2026-11-20 10:00:00', '2026-11-20 14:00:00', 200, 'Open', '2026-10-03 14:15:00'),
    (13, 'Inter-College Cricket Tournament', 'Annual knockout cricket tournament between departments and visiting colleges.', 'Sports', 'University Sports Pavilion', '2026-10-05 08:30:00', '2026-10-08 17:00:00', 300, 'Closed', '2026-09-20 09:00:00'),
    (14, 'Cloud Computing with AWS & Docker', 'Hands-on practical lab configuring Linux containers, Dockerfiles, and AWS cloud instances.', 'Workshop', 'Software Lab 3', '2026-11-26 13:00:00', '2026-11-26 17:00:00', 45, 'Open', '2026-10-05 16:00:00'),
    (15, 'Career Paths & Placement Readiness', 'HR leaders and alumni discuss interview tactics, resume engineering, and aptitude rounds.', 'Seminar', 'Seminar Hall B', '2026-11-16 11:00:00', '2026-11-16 13:30:00', 150, 'Open', '2026-10-06 12:00:00'),
    (16, 'Digital Photography & Art Showcase', 'Exhibition of campus photography, digital illustrations, and UI/UX student projects.', 'Cultural', 'Architecture Gallery', '2026-10-08 10:00:00', '2026-10-09 18:00:00', 120, 'Closed', '2026-09-25 10:30:00'),
    (17, 'Cyber Security & Ethical Hacking Lab', 'Live demonstration of network penetration testing, web app vulnerabilities, and defenses.', 'Workshop', 'Cyber Security Research Lab', '2026-12-04 10:30:00', '2026-12-04 15:30:00', 50, 'Open', '2026-10-08 15:00:00'),
    (18, 'Alumni Tech Leadership Talk', 'Interactive Q&A session with alumni founders on tech entrepreneurship and startup growth.', 'Guest Lecture', 'Conference Room 1', '2026-12-10 14:00:00', '2026-12-10 16:00:00', 80, 'Open', '2026-10-09 11:00:00'),
    (19, 'Inter-Department Badminton League', 'Singles and doubles indoor badminton championship for students and faculty.', 'Sports', 'Indoor Sports Complex', '2026-10-10 16:00:00', '2026-10-12 20:00:00', 100, 'Closed', '2026-09-28 08:00:00')
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    description = VALUES(description),
    category = VALUES(category),
    location = VALUES(location),
    starts_at = VALUES(starts_at),
    ends_at = VALUES(ends_at),
    capacity = VALUES(capacity),
    status = VALUES(status);
