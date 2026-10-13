<?php

declare(strict_types=1);

/**
 * StudentHub Session Status API
 * Practical 10: Secure Login/Logout with Sessions, Role-Based Access, Timeout, and Remember-Me Token.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

studenthub_start_session();

// If not logged in in session, check if a valid remember-me cookie exists
if (empty($_SESSION['user_id'])) {
    studenthub_resume_remembered_session($pdo);
}

// Enforce session timeout check (does NOT reset timer before expiry check)
if (!empty($_SESSION['user_id'])) {
    if (studenthub_check_timeout()) {
        echo json_encode([
            'authenticated' => false,
            'fullname' => null,
            'role' => null,
            'timeout' => true,
        ]);
        exit;
    }

    echo json_encode([
        'authenticated' => true,
        'fullname' => $_SESSION['fullname'] ?? null,
        'enrollment' => $_SESSION['enrollment'] ?? null,
        'role' => $_SESSION['role'] ?? 'student',
        'timeout' => false,
        'csrf_token' => studenthub_get_csrf_token(),
    ]);
    exit;
}

echo json_encode([
    'authenticated' => false,
    'fullname' => null,
    'role' => null,
    'timeout' => false,
]);
