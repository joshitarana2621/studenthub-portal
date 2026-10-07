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
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $dbHost = getenv('STUDENTHUB_DB_HOST') ?: '127.0.0.1';
    $dbPort = (int) (getenv('STUDENTHUB_DB_PORT') ?: '3306');
    $dbName = getenv('STUDENTHUB_DB_NAME') ?: 'studenthub';
    $dbUser = getenv('STUDENTHUB_DB_USER') ?: 'root';
    $dbPassword = getenv('STUDENTHUB_DB_PASSWORD') ?: '';

    $mysqli = new mysqli($dbHost, $dbUser, $dbPassword, $dbName, $dbPort);
    $mysqli->set_charset('utf8mb4');

    $duplicateCheck = $mysqli->prepare(
        'SELECT id FROM students WHERE enrollment = ? OR email = ? LIMIT 1'
    );
    $duplicateCheck->bind_param('ss', $enrollment, $email);
    $duplicateCheck->execute();
    $duplicateCheck->store_result();
    if ($duplicateCheck->num_rows > 0) {
        $duplicateCheck->close();
        respond(409, 'An account with this enrollment number or email already exists.');
    }
    $duplicateCheck->close();

    $statement = $mysqli->prepare(
        'INSERT INTO students (fullname, enrollment, email, password_hash)
         VALUES (?, ?, ?, ?)'
    );
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $statement->bind_param('ssss', $fullName, $enrollment, $email, $passwordHash);
    $statement->execute();
    $statement->close();
    $mysqli->close();
} catch (mysqli_sql_exception $exception) {
    if ($exception->getCode() === 1062) {
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
