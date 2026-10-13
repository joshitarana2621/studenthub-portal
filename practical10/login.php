<?php

declare(strict_types=1);

/**
 * StudentHub Login API
 * Practical 10: Secure Login/Logout with Sessions, Role-Based Access, Timeout, and Remember-Me Token.
 */

require_once __DIR__ . '/auth.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Enter valid login details.']);
    exit;
}

$identifier = trim((string) ($input['enrollment'] ?? $input['identifier'] ?? ''));
$password = (string) ($input['password'] ?? '');
$rememberMe = !empty($input['remember_me']);

// Basic server-side input validation
if ($identifier === '' || $password === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Enrollment number or email and password are required.']);
    exit;
}

try {
    require_once __DIR__ . '/db.php';

    // Prepared statement: look up user by enrollment OR email (never trust role from client)
    $statement = $pdo->prepare(
        'SELECT id, fullname, enrollment, email, role, password_hash FROM students 
         WHERE enrollment = :enrollment OR email = :email LIMIT 1'
    );
    $statement->execute([
        'enrollment' => $identifier,
        'email' => $identifier,
    ]);
    $user = $statement->fetch();

    // Verify password securely using password_verify()
    if ($user && password_verify($password, (string) $user['password_hash'])) {
        // Regenerate session ID to prevent session fixation attacks
        session_regenerate_id(true);

        $userRole = (string) ($user['role'] ?? 'student');

        // Store minimal necessary trusted identity and authorization state
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['enrollment'] = (string) $user['enrollment'];
        $_SESSION['fullname'] = (string) $user['fullname'];
        $_SESSION['role'] = $userRole;
        $_SESSION['last_activity'] = time();

        // Remember-me token processing
        if ($rememberMe) {
            studenthub_create_remember_token($pdo, (int) $user['id']);
        } else {
            studenthub_clear_remember_cookie();
            $pdo->prepare('DELETE FROM remember_tokens WHERE user_id = :user_id')
                ->execute(['user_id' => (int) $user['id']]);
        }

        // Role-Based Redirection: Admins go to admin dashboard, students go to student dashboard
        $redirectTo = ($userRole === 'admin') ? 'admin_dashboard.php' : 'dashboard.php';

        echo json_encode([
            'success' => true,
            'message' => 'Login successful.',
            'role' => $userRole,
            'redirect_to' => $redirectTo,
        ]);
        exit;
    }
} catch (PDOException $exception) {
    error_log('StudentHub login database error: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Login is temporarily unavailable.']);
    exit;
} catch (Exception $exception) {
    error_log('StudentHub login security error: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Login could not be completed.']);
    exit;
}

// Generic error response to prevent user enumeration attacks
http_response_code(401);
echo json_encode(['success' => false, 'message' => 'Invalid enrollment number or password.']);
