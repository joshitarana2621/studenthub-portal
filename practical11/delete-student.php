<?php

declare(strict_types=1);

/**
 * StudentHub - Practical 11: Student Management CRUD with Search and Filter
 * Module: Delete Student Handler (POST Only with CSRF Protection)
 */

require_once __DIR__ . '/../api/auth.php';
require_once __DIR__ . '/../api/db.php';

// Server-side authentication and role guard: only administrators allowed
studenthub_require_role('admin', $pdo);

// Helper to preserve active search, filter, and pagination parameters on redirect
function getReturnUrl(): string
{
    $params = [];
    if (!empty($_POST['search'])) {
        $params['search'] = (string) $_POST['search'];
    }
    if (!empty($_POST['course'])) {
        $params['course'] = (string) $_POST['course'];
    }
    if (!empty($_POST['status'])) {
        $params['status'] = (string) $_POST['status'];
    }
    if (!empty($_POST['page'])) {
        $params['page'] = (int) $_POST['page'];
    }
    return 'students.php' . (!empty($params) ? '?' . http_build_query($params) : '');
}

$returnUrl = getReturnUrl();

// Requirement D: Deletion must ONLY be executed via POST, never via GET
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $_SESSION['flash_error'] = 'Invalid request method. Deletion must be submitted via POST.';
    header('Location: students.php');
    exit;
}

// Requirement D: CSRF Token Verification
$submittedCsrf = (string) ($_POST['csrf_token'] ?? '');
if (!studenthub_verify_csrf_token($submittedCsrf)) {
    http_response_code(403);
    $_SESSION['flash_error'] = 'Security validation failed: Invalid CSRF token. Deletion aborted.';
    header('Location: ' . $returnUrl);
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash_error'] = 'Invalid student ID.';
    header('Location: ' . $returnUrl);
    exit;
}

// Verify that the record exists and has the role 'student' (prevent deleting administrators)
$checkStmt = $conn->prepare("SELECT id, fullname, enrollment, role FROM students WHERE id = ? LIMIT 1");
if ($checkStmt === false) {
    $_SESSION['flash_error'] = 'Database error preparing verification query: ' . $conn->error;
    header('Location: ' . $returnUrl);
    exit;
}

$checkStmt->bind_param('i', $id);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();
$student = $checkResult->fetch_assoc();
$checkStmt->close();

if (!$student) {
    $_SESSION['flash_error'] = 'Student record not found or already deleted.';
    header('Location: ' . $returnUrl);
    exit;
}

if ($student['role'] !== 'student') {
    $_SESSION['flash_error'] = 'Access restricted: Administrator records cannot be deleted through this module.';
    header('Location: ' . $returnUrl);
    exit;
}

// Execute MySQLi prepared DELETE statement
$deleteStmt = $conn->prepare("DELETE FROM students WHERE id = ? AND role = 'student'");
if ($deleteStmt === false) {
    $_SESSION['flash_error'] = 'Database error preparing delete query: ' . $conn->error;
    header('Location: ' . $returnUrl);
    exit;
}

$deleteStmt->bind_param('i', $id);
if ($deleteStmt->execute()) {
    $affected = $deleteStmt->affected_rows;
    $deleteStmt->close();

    if ($affected > 0) {
        $name = (string) $student['fullname'];
        $enrollment = (string) $student['enrollment'];
        $_SESSION['flash_success'] = "Student '{$name}' ({$enrollment}) was deleted successfully!";
    } else {
        $_SESSION['flash_error'] = 'No matching student record was deleted.';
    }
} else {
    $_SESSION['flash_error'] = 'Failed to delete student record: ' . $deleteStmt->error;
    $deleteStmt->close();
}

header('Location: ' . $returnUrl);
exit;
