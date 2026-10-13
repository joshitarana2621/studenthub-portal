-- ==============================================================================
-- StudentHub Practical 11: Database Migration
-- Title: Student Management CRUD with Search and Filter
-- Safe migration: preserves all existing Practical 1-10 records and schema.
-- ==============================================================================

USE studenthub;

-- 1. Add 'course' column if it does not already exist
ALTER TABLE students
    ADD COLUMN IF NOT EXISTS course VARCHAR(100) NOT NULL DEFAULT 'Computer Engineering' AFTER email;

-- 2. Add 'status' column if it does not already exist
ALTER TABLE students
    ADD COLUMN IF NOT EXISTS status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active' AFTER role;

-- 3. Allow password_hash to be NULL for admin-added records
ALTER TABLE students
    MODIFY COLUMN password_hash VARCHAR(255) NULL DEFAULT NULL;

-- 4. Populate realistic courses for existing student records
UPDATE students SET course = 'Computer Engineering' WHERE enrollment LIKE '%dce%' OR enrollment = 'STUDENT001';
UPDATE students SET course = 'Artificial Intelligence & Machine Learning' WHERE enrollment LIKE '%aimil%' OR enrollment LIKE '%aiml%';
UPDATE students SET course = 'Administration' WHERE role = 'admin';
