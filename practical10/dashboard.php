<?php

declare(strict_types=1);

require_once __DIR__ . '/../api/auth.php';
require_once __DIR__ . '/../api/db.php';

// Server-side authentication and role check: only students allowed
studenthub_require_role('student', $pdo);

$userStatement = $pdo->prepare('SELECT fullname, enrollment, email FROM students WHERE id = :id LIMIT 1');
$userStatement->execute(['id' => (int) $_SESSION['user_id']]);
$userData = $userStatement->fetch();

$fullName = $userData['fullname'] ?? ($_SESSION['fullname'] ?? 'Student');
$enrollment = $userData['enrollment'] ?? ($_SESSION['enrollment'] ?? '');
$csrfToken = studenthub_get_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - StudentHub Portal</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <header>
        <div>
            <img src="../images/logo.png" alt="StudentHub Portal Logo" width="100">
            <h1>studenthub-portal</h1>
        </div>
    </header>

    <button id="hamburger" class="hamburger" aria-label="Toggle navigation">☰</button>
    <nav id="mainNav" class="nav-menu">
        <ul>
            <li><a href="index.html">Home</a></li>
            <li><a href="login.html">Login</a></li>
            <li><a href="register.html">Register</a></li>
            <li><a href="dashboard.php" class="active">Dashboard</a></li>
            <li><a href="profile.html">Profile</a></li>
            <li><a href="course.html">Courses</a></li>
            <li><a href="attendance.html">Attendance</a></li>
            <li><a href="assignment.html">Assignments</a></li>
            <li><a href="result.html">Result</a></li>
            <li><a href="contact.html">Contact</a></li>
        </ul>
    </nav>

    <main>
        <section>
            <h2>Student Dashboard</h2>
            <p>Welcome, <strong id="sessionUser"><?= htmlspecialchars((string) $fullName, ENT_QUOTES, 'UTF-8') ?></strong> (<?= htmlspecialchars((string) $enrollment, ENT_QUOTES, 'UTF-8') ?>).</p>
            <p>
                <span style="background: #16805d; color: #fff; padding: 3px 8px; border-radius: 4px; font-size: 0.85em;">Role: Student</span>
            </p>
            <form method="POST" action="../api/logout.php" style="margin-top: 10px;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <button id="logoutButton" type="submit">Logout</button>
            </form>
        </section>

        <section>
            <h3>Dashboard Quick Access</h3>
            <ul>
                <li><a href="profile.html"><strong>Student Profile</strong></a></li>
                <li><a href="course.html"><strong>Course Details</strong></a></li>
                <li><a href="attendance.html"><strong>Attendance</strong></a></li>
                <li><a href="assignment.html"><strong>Assignments</strong></a></li>
                <li><a href="result.html"><strong>Result Search</strong></a></li>
            </ul>
        </section>

        <section>
            <h3>Attendance</h3>
            <p>All over attendance-[85%]</p>
        </section>

        <section>
            <h2>Today's Class Schedule</h2>

            <table border="1">
                <caption><strong>Lecture Timetable</strong></caption>

                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Subject</th>
                        <th>Faculty Name</th>
                        <th>Lecture Type</th>
                    </tr>
                </thead>

                <tbody>
                    <tr>
                        <td>09:00 AM - 10:00 AM</td>
                        <td>Object Oriented Programming</td>
                        <td>Dr. Priya Shah</td>
                        <td>Theory</td>
                    </tr>

                    <tr>
                        <td>10:00 AM - 11:00 AM</td>
                        <td>Data Structures</td>
                        <td>Prof. Amit Patel</td>
                        <td>Practical</td>
                    </tr>

                    <tr>
                        <td>11:15 AM - 12:15 PM</td>
                        <td>Digital Electronics</td>
                        <td>Dr. Neha Mehta</td>
                        <td>Theory</td>
                    </tr>

                    <tr>
                        <td>01:00 PM - 02:00 PM</td>
                        <td>Database Management System</td>
                        <td>Prof. Rajesh Kumar</td>
                        <td>Lab</td>
                    </tr>

                    <tr>
                        <td>02:15 PM - 03:15 PM</td>
                        <td>Web Development</td>
                        <td>Ms. Sneha Desai</td>
                        <td>Practical</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section>
            <h3>college gallery</h3>
            <figure>
                <img src="../images/campus5.png" alt="college campus image" width="180">
                <figcaption>college campus image</figcaption>
            </figure>
            <figure>
                <img src="../images/classroom.png" alt="college classroom image" width="180">
                <figcaption>college classroom image</figcaption>
            </figure>
            <figure>
                <img src="../images/event.png" alt="collenge event image" width="180">
                <figcaption>collenge event image</figcaption>
            </figure>
            <figure>
                <img src="../images/lab.png" alt="college lab image" width="180">
                <figcaption>college lab image</figcaption>
            </figure>
        </section>

        <section>
            <h2>little description about college</h2>
            <p>
            Our college is dedicated to providing quality education, modern learning facilities, and a supportive
            environment for students. With experienced faculty, well-equipped laboratories, a digital library, and
            various extracurricular activities, we help students develop the knowledge, skills, and confidence needed
            for a successful future.
            </p>
        </section>
    </main>

    <footer>
        <p>&copy; 2026 StudentHub Portal. All Rights Reserved.</p>
    </footer>

    <script src="../js/auth-guard.js"></script>
</body>

</html>
