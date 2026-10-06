<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$fullName = trim((string) ($input['fullname'] ?? ''));
$enrollment = trim((string) ($input['enrollment'] ?? ''));
$email = trim((string) ($input['email'] ?? ''));
$password = (string) ($input['password'] ?? '');
$confirmPassword = (string) ($input['confirm_password'] ?? '');

if ($fullName === '' || !preg_match('/^[A-Za-z\s]+$/', $fullName)) {
    respond(422, 'Enter a valid full name.');
}

if (!preg_match('/^[A-Za-z0-9]{4,}$/', $enrollment)) {
    respond(422, 'Enter a valid enrollment number.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(422, 'Enter a valid email address.');
}

if (strlen($password) < 6 || strlen($password) > 72) {
    respond(422, 'Password must contain 6 to 72 characters.');
}

if ($password !== $confirmPassword) {
    respond(422, 'Passwords do not match.');
}

try {
    require_once __DIR__ . '/db.php';
    $statement = $pdo->prepare(
        'INSERT INTO students (fullname, enrollment, email, password_hash)
         VALUES (:fullname, :enrollment, :email, :password_hash)'
    );
    $statement->execute([
        'fullname' => $fullName,
        'enrollment' => $enrollment,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT)
    ]);
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        respond(409, 'An account with this enrollment number or email already exists.');
    }

    error_log('StudentHub registration database error: ' . $exception->getMessage());
    respond(500, 'The account could not be saved.');
}

echo json_encode(['success' => true, 'message' => 'Registration successful.']);

function respond(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}
