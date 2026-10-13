<?php

declare(strict_types=1);

/**
 * StudentHub Authentication & Session Security Helper
 * Practical 10: Secure Login/Logout with Sessions, Role-Based Access, Timeout, and Remember-Me Token.
 */

// Inactivity timeout in seconds (Default: 15 minutes = 900 seconds)
const STUDENTHUB_SESSION_TIMEOUT = 900;

// Remember-me token lifetime in seconds (30 days)
const STUDENTHUB_REMEMBER_LIFETIME = 2592000;

/**
 * Configure secure cookie parameters and start session if not already active.
 */
function studenthub_start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        // Detect HTTPS dynamically so local HTTP on XAMPP does not break
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }
}

// Auto-start session with secure settings on include
studenthub_start_session();

/**
 * Retrieve or generate a CSRF token for the active session.
 */
function studenthub_get_csrf_token(): string
{
    studenthub_start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf_token'];
}

/**
 * Validate a submitted CSRF token using timing-safe comparison.
 */
function studenthub_verify_csrf_token(?string $token): bool
{
    studenthub_start_session();
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals((string) $_SESSION['csrf_token'], $token);
}

/**
 * Check if the active session has exceeded the inactivity timeout.
 * Returns true if expired and session was destroyed, false otherwise.
 * NOTE: Does NOT reset the timer until expiry check passes.
 */
function studenthub_check_timeout(): bool
{
    if (empty($_SESSION['user_id'])) {
        return false;
    }

    $now = time();
    if (isset($_SESSION['last_activity'])) {
        $inactiveSeconds = $now - (int) $_SESSION['last_activity'];
        if ($inactiveSeconds >= STUDENTHUB_SESSION_TIMEOUT) {
            studenthub_destroy_session();
            return true;
        }
    }

    // Refresh last activity only after confirming session has not expired
    $_SESSION['last_activity'] = $now;
    return false;
}

/**
 * Clear session data and expire the session cookie.
 */
function studenthub_destroy_session(): void
{
    studenthub_start_session();
    $_SESSION = [];

    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 3600,
                'path' => $params['path'] ?? '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool) ($params['secure'] ?? $isSecure),
                'httponly' => (bool) ($params['httponly'] ?? true),
                'samesite' => $params['samesite'] ?? 'Lax',
            ]
        );
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    studenthub_clear_remember_cookie();
}

/**
 * Expire and delete the remember-me cookie.
 */
function studenthub_clear_remember_cookie(): void
{
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

    setcookie(
        'studenthub_remember',
        '',
        [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]
    );
}

/**
 * Generate, store (hashed), and issue a secure split remember-me token.
 */
function studenthub_create_remember_token(PDO $pdo, int $userId): void
{
    $selector = bin2hex(random_bytes(6)); // 12-char lookup selector
    $token = bin2hex(random_bytes(32));    // 64-char validator token
    $tokenHash = hash('sha256', $token);   // Only hash stored in DB
    $expiresAt = date('Y-m-d H:i:s', time() + STUDENTHUB_REMEMBER_LIFETIME);

    // Remove any previous tokens for this user
    $pdo->prepare('DELETE FROM remember_tokens WHERE user_id = :user_id')->execute(['user_id' => $userId]);

    // Insert new hashed token
    $insert = $pdo->prepare(
        'INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at)
         VALUES (:user_id, :selector, :token_hash, :expires_at)'
    );
    $insert->execute([
        'user_id' => $userId,
        'selector' => $selector,
        'token_hash' => $tokenHash,
        'expires_at' => $expiresAt,
    ]);

    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

    // Issue persistent cookie
    setcookie(
        'studenthub_remember',
        $selector . ':' . $token,
        [
            'expires' => time() + STUDENTHUB_REMEMBER_LIFETIME,
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]
    );
}

/**
 * Validate remember-me cookie, rotate the token, and resume authenticated session.
 */
function studenthub_resume_remembered_session(PDO $pdo): bool
{
    if (empty($_COOKIE['studenthub_remember'])) {
        return false;
    }

    [$selector, $token] = array_pad(explode(':', (string) $_COOKIE['studenthub_remember'], 2), 2, '');
    if ($selector === '' || $token === '') {
        studenthub_clear_remember_cookie();
        return false;
    }

    $statement = $pdo->prepare(
        'SELECT id, user_id, token_hash, expires_at FROM remember_tokens WHERE selector = :selector LIMIT 1'
    );
    $statement->execute(['selector' => $selector]);
    $remembered = $statement->fetch();

    if ($remembered === false) {
        studenthub_clear_remember_cookie();
        return false;
    }

    $expiresAt = strtotime((string) $remembered['expires_at']);
    if ($expiresAt === false || $expiresAt < time()) {
        $pdo->prepare('DELETE FROM remember_tokens WHERE id = :id')->execute(['id' => $remembered['id']]);
        studenthub_clear_remember_cookie();
        return false;
    }

    $expectedHash = hash('sha256', $token);
    if (!hash_equals((string) $remembered['token_hash'], $expectedHash)) {
        // Potential tampering/attack: invalidate immediately
        $pdo->prepare('DELETE FROM remember_tokens WHERE id = :id')->execute(['id' => $remembered['id']]);
        studenthub_clear_remember_cookie();
        return false;
    }

    // Retrieve user and their verified role from database
    $userStatement = $pdo->prepare(
        'SELECT id, fullname, enrollment, role FROM students WHERE id = :user_id LIMIT 1'
    );
    $userStatement->execute(['user_id' => (int) $remembered['user_id']]);
    $user = $userStatement->fetch();

    if ($user === false) {
        $pdo->prepare('DELETE FROM remember_tokens WHERE id = :id')->execute(['id' => $remembered['id']]);
        studenthub_clear_remember_cookie();
        return false;
    }

    // Regenerate session ID to prevent session fixation upon auto-login
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['fullname'] = (string) $user['fullname'];
    $_SESSION['enrollment'] = (string) $user['enrollment'];
    $_SESSION['role'] = (string) ($user['role'] ?? 'student');
    $_SESSION['last_activity'] = time();

    // Rotate remember-me token on successful use (prevents replay attacks)
    studenthub_create_remember_token($pdo, (int) $user['id']);

    return true;
}

/**
 * Server-side authorization guard.
 * Ensures the user is logged in, has not timed out, and holds an allowed role.
 *
 * @param string|array<string> $allowedRoles
 */
function studenthub_require_role($allowedRoles, PDO $pdo): void
{
    studenthub_start_session();

    // Enforce session timeout first
    if (studenthub_check_timeout()) {
        header('Location: login.html?session=expired');
        exit;
    }

    // If unauthenticated, try restoring from remember-me
    if (empty($_SESSION['user_id'])) {
        if (!studenthub_resume_remembered_session($pdo)) {
            header('Location: login.html');
            exit;
        }
    }

    // Check role against allowed roles
    $roles = (array) $allowedRoles;
    $currentRole = (string) ($_SESSION['role'] ?? 'student');

    if (!in_array($currentRole, $roles, true)) {
        http_response_code(403);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>403 Forbidden - StudentHub Portal</title>
            <link rel="stylesheet" href="css/style.css">
            <style>
                .forbidden-card { max-width: 540px; margin: 80px auto; padding: 32px; background: #fff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); text-align: center; }
                .forbidden-card h2 { color: #b13c34; margin-bottom: 12px; }
                .forbidden-card p { margin-bottom: 20px; line-height: 1.6; }
                .btn-return { display: inline-block; padding: 10px 20px; background: #16805d; color: #fff; text-decoration: none; border-radius: 4px; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class="forbidden-card">
                <h2>403 Forbidden: Access Denied</h2>
                <p>Your account (Role: <strong><?= htmlspecialchars($currentRole, ENT_QUOTES, 'UTF-8') ?></strong>) does not have authorization to view this protected resource.</p>
                <?php if ($currentRole === 'student'): ?>
                    <a class="btn-return" href="dashboard.php">&larr; Return to Student Dashboard</a>
                <?php else: ?>
                    <a class="btn-return" href="admin_dashboard.php">&larr; Return to Admin Dashboard</a>
                <?php endif; ?>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}
