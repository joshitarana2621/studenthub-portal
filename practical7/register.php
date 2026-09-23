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

$usersFile = __DIR__ . '/../data/students.csv';
$users = readUsers($usersFile);

foreach ($users as $user) {
    if (strcasecmp($user['enrollment'], $enrollment) === 0) {
        respond(409, 'An account with this enrollment number already exists.');
    }

    if (strcasecmp($user['email'], $email) === 0) {
        respond(409, 'An account with this email already exists.');
    }
}

$newUser = [
    'id' => bin2hex(random_bytes(8)),
    'fullname' => $fullName,
    'enrollment' => $enrollment,
    'email' => $email,
    'password' => password_hash($password, PASSWORD_DEFAULT),
    'created_at' => date(DATE_ATOM)
];
$users[] = $newUser;

$csv = fopen($usersFile, 'w');
if ($csv === false) {
    respond(500, 'The account could not be saved.');
}

if (!flock($csv, LOCK_EX)) {
    fclose($csv);
    respond(500, 'The account could not be saved.');
}

fputcsv($csv, ['id', 'fullname', 'enrollment', 'email', 'password', 'created_at']);
foreach ($users as $user) {
    fputcsv($csv, $user);
}
flock($csv, LOCK_UN);
fclose($csv);

echo json_encode(['success' => true, 'message' => 'Registration successful.']);

function readUsers(string $usersFile): array
{
    if (!file_exists($usersFile)) {
        return [];
    }

    $csv = fopen($usersFile, 'r');
    if ($csv === false) {
        return [];
    }

    $headers = fgetcsv($csv);
    $users = [];
    while (($row = fgetcsv($csv)) !== false) {
        if (count($row) === count($headers)) {
            $users[] = array_combine($headers, $row);
        }
    }
    fclose($csv);
    return $users;
}

function respond(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}
