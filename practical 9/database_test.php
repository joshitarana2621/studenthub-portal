<?php

declare(strict_types=1);

$enrollment = trim((string) ($_GET['enrollment'] ?? ''));
$connected = false;
$studentFound = null;
$errorMessage = null;

try {
    require __DIR__ . '/db.php';
    $connected = true;

    if ($enrollment !== '') {
        $statement = $pdo->prepare(
            'SELECT id FROM students WHERE enrollment = :enrollment LIMIT 1'
        );
        $statement->execute(['enrollment' => $enrollment]);
        $studentFound = $statement->fetch() !== false;
    }
} catch (PDOException $exception) {
    error_log('StudentHub database test error: ' . $exception->getMessage());
    $errorMessage = 'Connection failed. Check that MySQL is running and db.php settings match your local setup.';
}

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub Database Test</title>
    <style>
        body { max-width: 640px; margin: 48px auto; padding: 0 20px; color: #202b32; font: 16px/1.5 system-ui, sans-serif; }
        h1 { margin-bottom: 8px; }
        form { display: flex; flex-wrap: wrap; gap: 10px; margin: 24px 0; }
        input, button { min-height: 42px; padding: 8px 12px; font: inherit; }
        input { flex: 1 1 240px; }
        .status { padding: 14px; border-left: 4px solid #16805d; background: #edf7f2; }
        .error { border-color: #b13c34; background: #fff1ef; }
    </style>
</head>
<body>
    <h1>StudentHub database test</h1>
    <p>PDO connection and prepared-statement check for the local XAMPP database.</p>

    <?php if ($connected): ?>
        <p class="status">PDO connection successful.</p>
    <?php else: ?>
        <p class="status error"><?= escapeHtml($errorMessage ?? 'Connection failed.') ?></p>
    <?php endif; ?>

    <form method="get">
        <label for="enrollment">Enrollment number</label>
        <input id="enrollment" name="enrollment" value="<?= escapeHtml($enrollment) ?>" autocomplete="off">
        <button type="submit">Run prepared query</button>
    </form>

    <?php if ($studentFound !== null): ?>
        <p><?= $studentFound ? 'Matching student found.' : 'No student matched that enrollment number.' ?></p>
    <?php endif; ?>
</body>
</html>
