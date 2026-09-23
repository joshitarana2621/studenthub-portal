<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$enrollment = trim((string) ($input['enrollment'] ?? ''));
$password = (string) ($input['password'] ?? '');
$usersFile = __DIR__ . '/../data/students.csv';
$users = readUsers($usersFile);

foreach ($users as $user) {
    if (strcasecmp($user['enrollment'] ?? '', $enrollment) === 0 && password_verify($password, $user['password'] ?? '')) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['enrollment'] = $user['enrollment'];
        $_SESSION['fullname'] = $user['fullname'];

        echo json_encode(['success' => true, 'message' => 'Login successful.']);
        exit;
    }
}

http_response_code(401);
echo json_encode(['success' => false, 'message' => 'Invalid enrollment number or password.']);

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
