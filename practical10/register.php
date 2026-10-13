<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    respond(422, 'Enter valid registration details.');
}

foreach (['fullname', 'enrollment', 'email', 'password', 'confirm_password'] as $field) {
    if (!isset($input[$field]) || !is_string($input[$field])) {
        respond(422, 'Enter valid registration details.');
    }
}

$fullName = trim($input['fullname']);
$enrollment = trim($input['enrollment']);
$email = trim($input['email']);
$password = $input['password'];
$confirmPassword = $input['confirm_password'];

if ($fullName === '' || strlen($fullName) > 120 || !preg_match('/^[A-Za-z\s]+$/', $fullName)) {
    respond(422, 'Enter a valid full name.');
}

if (strlen($enrollment) < 4 || strlen($enrollment) > 32 || !preg_match('/^[A-Za-z0-9]+$/', $enrollment)) {
    respond(422, 'Enter a valid enrollment number.');
}

if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(422, 'Enter a valid email address.');
}

if (strlen($password) < 6 || strlen($password) > 72) {
    respond(422, 'Password must contain 6 to 72 UTF-8 bytes.');
}

if ($password !== $confirmPassword) {
    respond(422, 'Passwords do not match.');
}

try {
    require_once __DIR__ . '/db.php';

    $duplicateCheck = $pdo->prepare(
        'SELECT id FROM students WHERE enrollment = :enrollment OR email = :email LIMIT 1'
    );
    $duplicateCheck->execute([
        'enrollment' => $enrollment,
        'email' => $email,
    ]);
    if ($duplicateCheck->fetch()) {
        respond(409, 'An account with this enrollment number or email already exists.');
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $statement = $pdo->prepare(
        'INSERT INTO students (fullname, enrollment, email, role, password_hash)
         VALUES (:fullname, :enrollment, :email, :role, :password_hash)'
    );
    $statement->execute([
        'fullname' => $fullName,
        'enrollment' => $enrollment,
        'email' => $email,
        'role' => 'student',
        'password_hash' => $passwordHash,
    ]);
} catch (PDOException $exception) {
    if ((int) $exception->getCode() === 23000) {
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
