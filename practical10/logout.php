<?php

declare(strict_types=1);

/**
 * StudentHub Logout API & Route
 * Practical 10: Secure Login/Logout with Sessions, Role-Based Access, Timeout, and Remember-Me Token.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

studenthub_start_session();

$isAjax = (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
    || (!empty($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check CSRF token if sent
    $csrfToken = $_POST['csrf_token'] ?? null;
    if ($csrfToken === null) {
        $headers = getallheaders();
        $csrfToken = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? null;
    }

    if ($csrfToken !== null && !studenthub_verify_csrf_token((string) $csrfToken)) {
        http_response_code(403);
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'CSRF token mismatch.']);
        } else {
            echo 'Invalid CSRF token.';
        }
        exit;
    }
}

// Remove remember-me token from database for current user
if (!empty($_SESSION['user_id'])) {
    try {
        $pdo->prepare('DELETE FROM remember_tokens WHERE user_id = :user_id')
            ->execute(['user_id' => (int) $_SESSION['user_id']]);
    } catch (PDOException $e) {
        error_log('Error deleting remember token on logout: ' . $e->getMessage());
    }
}

// Clear all session variables, destroy session, and expire cookie
studenthub_destroy_session();

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'message' => 'Logged out successfully.',
        'redirect_to' => 'login.html?logout=success',
    ]);
    exit;
}

// If form post or browser redirection
header('Location: ../pages/login.html?logout=success');
exit;
