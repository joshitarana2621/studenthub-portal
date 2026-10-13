-- ==============================================================================
-- StudentHub Practical 11: Seed Data Script
-- Title: Student Management CRUD with Search and Filter
-- Provides at least 20 realistic fictional student records across multiple courses.
-- Uses INSERT IGNORE to safely prevent duplicates and preserve existing records.
-- ==============================================================================

USE studenthub;

INSERT IGNORE INTO students (fullname, enrollment, email, course, role, status, password_hash, created_at)
VALUES
    ('Aarav Sharma', '25dce010', 'aarav.sharma@studenthub.edu', 'Computer Engineering', 'student', 'Active', '$2y$10$Dkk/72cC3OANBTGYcNnQW.4hNiqZb68u8zSrkH8ZRJfNcpC2bEgBu', '2026-08-01 10:15:00'),
    ('Diya Mehta', '25dce011', 'diya.mehta@studenthub.edu', 'Computer Engineering', 'student', 'Active', '$2y$10$Dkk/72cC3OANBTGYcNnQW.4hNiqZb68u8zSrkH8ZRJfNcpC2bEgBu', '2026-08-01 10:20:00'),
    ('Rohan Verma', '25dce012', 'rohan.verma@studenthub.edu', 'Computer Engineering', 'student', 'Inactive', '$2y$10$Dkk/72cC3OANBTGYcNnQW.4hNiqZb68u8zSrkH8ZRJfNcpC2bEgBu', '2026-08-01 10:25:00'),
    ('Priya Patel', '25dce013', 'priya.patel@studenthub.edu', 'Computer Engineering', 'student', 'Active', '$2y$10$Dkk/72cC3OANBTGYcNnQW.4hNiqZb68u8zSrkH8ZRJfNcpC2bEgBu', '2026-08-01 10:30:00'),
    ('Siddharth Nair', '25dce014', 'siddharth.nair@studenthub.edu', 'Computer Engineering', 'student', 'Active', '$2y$10$Dkk/72cC3OANBTGYcNnQW.4hNiqZb68u8zSrkH8ZRJfNcpC2bEgBu', '2026-08-01 10:35:00'),
    
    ('Sneha Kulkarni', '25it001', 'sneha.kulkarni@studenthub.edu', 'Information Technology', 'student', 'Active', '$2y$10$Dkk/72cC3OANBTGYcNnQW.4hNiqZb68u8zSrkH8ZRJfNcpC2bEgBu', '2026-08-02 09:10:00'),
    ('Aditya Iyer', '25it002', 'aditya.iyer@studenthub.edu', 'Information Technology', 'student', 'Active', '$2y$10$Dkk/72cC3OANBTGYcNnQW.4hNiqZb68u8zSrkH8ZRJfNcpC2bEgBu', '2026-08-02 09:15:00'),
    ('Ishita Deshmukh', '25it003', 'ishita.deshmukh@studenthub.edu', 'Information Technology', 'student', 'Inactive', '$2y$10$Dkk/72cC3OANBTGYcNnQW.4hNiqZb68u8zSrkH8ZRJfNcpC2bEgBu', '2026-08-02 09:20:00'),
    ('Varun Rao', '25it004', 'varun.rao@studenthub.edu', 'Information Technology', 'student', 'Active', '$2y$10$Dkk/72cC3OANBTGYcNnQW.4hNiqZb68u8zSrkH8ZRJfNcpC2bEgBu', '2026-08-02 09:25:00'),
    ('Riya Bhatt', '25it005', 'riya.bhatt@studenthub.edu', 'Information Technology', 'student', 'Active', '$2y$10$Dkk/72cC3OANBTGYcNnQW.4hNiqZb68u8zSrkH8ZRJfNcpC2bEgBu', '2026-08-02 09:30:00'),

    ('Kunal Joshi', '25aiml001', 'kunal.joshi@studenthub.edu', 'Artificial Intelligence & Machine Learning', 'student', 'Active', '$2y$10$Dkk/72cC3OANBTGYcNnQW.4hNiqZb68u8zSrkH8ZRJfNcpC2bEgBu', '2026-08-03 11:00:00'),
    ('Ananya Roy', '25aiml002', 'ananya.roy@studenthub.edu', 'Artificial Intelligence & Machine Learning', 'student', 'Active', '$2y$10$Dkk/72cC3OANBTGYcNnQW.4hNiqZb68u8zSrkH8ZRJfNcpC2bEgBu', '2026-08-03 11:05:00'),
    ('Harshil Shah', '25aiml003', 'harshil.shah@studenthub.edu', 'Artificial Intelligence & Machine Learning', 'student', 'Active', '$2y$10$Dkk/72cC3OANBTGYcNnQW.4hNiqZb68u8zSrkH8ZRJfNcpC2bEgBu', '2026-08-03 11:10:00'),
    ('Pooja Chauhan', '25aiml004', 'pooja.chauhan@studenthub.edu', 'Artificial Intelligence & Machine Learning', 'student', 'Inactive', '$2y$10$Dkk/72cC3OANBTGYcNnQW.4hNiqZb68u8zSrkH8ZRJfNcpC2bEgBu', '2026-08-03 11:15:00'),

    ('Karan Gupta', '25me001', 'karan.gupta@studenthub.edu', 'Mechanical Engineering', 'student', 'Active', '$2y$10$Dkk/72cC3OANBTGYcNnQW.4hNiqZb68u8zSrkH8ZRJfNcpC2bEgBu', '2026-08-04 14:00:00'),
    ('Neha Solanki', '25me002', 'neha.solanki@studenthub.edu', 'Mechanical Engineering', 'student', 'Active', '$2y$10$Dkk/72cC3OANBTGYcNnQW.4hNiqZb68u8zSrkH8ZRJfNcpC2bEgBu', '2026-08-04 14:05:00'),
    ('Manan Dave', '25me003', 'manan.dave@studenthub.edu', 'Mechanical Engineering', 'student', 'Inactive', '$2y$10$Dkk/72cC3OANBTGYcNnQW.4hNiqZb68u8zSrkH8ZRJfNcpC2bEgBu', '2026-08-04 14:10:00'),
    ('Tanvi Parekh', '25me004', 'tanvi.parekh@studenthub.edu', 'Mechanical Engineering', 'student', 'Active', '$2y$10$Dkk/72cC3OANBTGYcNnQW.4hNiqZb68u8zSrkH8ZRJfNcpC2bEgBu', '2026-08-04 14:15:00'),

    ('Yash Rajput', '25ce001', 'yash.rajput@studenthub.edu', 'Civil Engineering', 'student', 'Active', '$2y$10$Dkk/72cC3OANBTGYcNnQW.4hNiqZb68u8zSrkH8ZRJfNcpC2bEgBu', '2026-08-05 10:00:00'),
    ('Riddhi Trivedi', '25ce002', 'riddhi.trivedi@studenthub.edu', 'Civil Engineering', 'student', 'Active', '$2y$10$Dkk/72cC3OANBTGYcNnQW.4hNiqZb68u8zSrkH8ZRJfNcpC2bEgBu', '2026-08-05 10:05:00'),
    ('Vivek Pandey', '25ce003', 'vivek.pandey@studenthub.edu', 'Civil Engineering', 'student', 'Active', '$2y$10$Dkk/72cC3OANBTGYcNnQW.4hNiqZb68u8zSrkH8ZRJfNcpC2bEgBu', '2026-08-05 10:10:00'),

    ('Shreya Singhania', '25ee001', 'shreya.singhania@studenthub.edu', 'Electrical Engineering', 'student', 'Active', '$2y$10$Dkk/72cC3OANBTGYcNnQW.4hNiqZb68u8zSrkH8ZRJfNcpC2bEgBu', '2026-08-06 15:00:00'),
    ('Parth Vaghela', '25ee002', 'parth.vaghela@studenthub.edu', 'Electrical Engineering', 'student', 'Active', '$2y$10$Dkk/72cC3OANBTGYcNnQW.4hNiqZb68u8zSrkH8ZRJfNcpC2bEgBu', '2026-08-06 15:05:00'),
    ('Drishti Soni', '25ee003', 'drishti.soni@studenthub.edu', 'Electrical Engineering', 'student', 'Inactive', '$2y$10$Dkk/72cC3OANBTGYcNnQW.4hNiqZb68u8zSrkH8ZRJfNcpC2bEgBu', '2026-08-06 15:10:00'),
    ('Devendra Rathod', '25ee004', 'devendra.rathod@studenthub.edu', 'Electrical Engineering', 'student', 'Active', '$2y$10$Dkk/72cC3OANBTGYcNnQW.4hNiqZb68u8zSrkH8ZRJfNcpC2bEgBu', '2026-08-06 15:15:00');
