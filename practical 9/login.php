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
try {
    require_once __DIR__ . '/db.php';
    $statement = $pdo->prepare(
        'SELECT id, fullname, enrollment, password_hash FROM students WHERE enrollment = :enrollment LIMIT 1'
    );
    $statement->execute(['enrollment' => $enrollment]);
    $user = $statement->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['enrollment'] = $user['enrollment'];
        $_SESSION['fullname'] = $user['fullname'];

        echo json_encode(['success' => true, 'message' => 'Login successful.']);
        exit;
    }
} catch (PDOException $exception) {
    error_log('StudentHub login database error: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Login is temporarily unavailable.']);
    exit;
}

http_response_code(401);
echo json_encode(['success' => false, 'message' => 'Invalid enrollment number or password.']);
