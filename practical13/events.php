<?php

declare(strict_types=1);

/**
 * StudentHub - Practical 13: PHP JSON API Endpoint for Events
 *
 * RESTful JSON API providing full AJAX/Fetch CRUD operations without page reload:
 * - GET    /api/events.php         -> Retrieve all events (supports search, category, status filters)
 * - GET    /api/events.php?id=X    -> Retrieve a single event by ID
 * - POST   /api/events.php         -> Create a new event (JSON body) [Admin only]
 * - PUT    /api/events.php?id=X    -> Update an existing event (JSON body) [Admin only]
 * - DELETE /api/events.php?id=X    -> Delete an event [Admin only]
 */

// Prevent HTML error output from corrupting JSON responses
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Ensure output buffer is clean
if (ob_get_level() > 0) {
    ob_clean();
}

// Set standard JSON content type and security headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Helper function to send standard JSON responses and terminate
function sendResponse(int $statusCode, bool $success, string $message, mixed $data = null, array $extra = []): void
{
    http_response_code($statusCode);
    $payload = array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra);

    if ($data !== null) {
        $payload['data'] = $data;
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Handle preflight OPTIONS requests cleanly
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    http_response_code(204);
    exit;
}

try {
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/auth.php';
} catch (Throwable $e) {
    error_log('StudentHub API initialization failure: ' . $e->getMessage());
    sendResponse(500, false, 'Database connection failed. Please ensure MySQL is running.');
}

// Determine HTTP Method (supports method override via _method or X-HTTP-Method-Override)
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'POST') {
    $override = $_POST['_method'] ?? $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? null;
    if ($override && in_array(strtoupper((string) $override), ['PUT', 'PATCH', 'DELETE'], true)) {
        $method = strtoupper((string) $override);
    }
}

// Helper: parse incoming JSON request payload
function getRequestPayload(): array
{
    $rawInput = file_get_contents('php://input');
    if ($rawInput === false || trim($rawInput) === '') {
        return $_POST ?: [];
    }

    $decoded = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(400, false, 'Invalid JSON payload received: ' . json_last_error_msg());
    }

    return is_array($decoded) ? $decoded : [];
}

// Helper: enforce administrator role for write operations
function checkAdminAuthorization(PDO $pdo): array
{
    studenthub_start_session();

    if (studenthub_check_timeout()) {
        sendResponse(401, false, 'Your session has expired due to inactivity. Please log in again.');
    }

    if (empty($_SESSION['user_id'])) {
        if (!studenthub_resume_remembered_session($pdo)) {
            sendResponse(401, false, 'Authentication required. Please log in with an administrator account.');
        }
    }

    $role = (string) ($_SESSION['role'] ?? 'student');
    if ($role !== 'admin') {
        sendResponse(403, false, 'Forbidden. Administrator privileges are required to modify campus events.');
    }

    return [
        'id' => (int) $_SESSION['user_id'],
        'name' => (string) ($_SESSION['fullname'] ?? 'Administrator'),
    ];
}

// Helper: write to event_audit_log if table exists
function logEventAudit(PDO $pdo, ?int $eventId, string $title, array $admin, string $action, string $details): void
{
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO event_audit_log (event_id, event_title, admin_id, admin_name, action_type, details)
             VALUES (:event_id, :event_title, :admin_id, :admin_name, :action_type, :details)'
        );
        $stmt->execute([
            'event_id' => $eventId,
            'event_title' => $title,
            'admin_id' => $admin['id'],
            'admin_name' => $admin['name'],
            'action_type' => $action,
            'details' => $details,
        ]);
    } catch (Throwable $e) {
        // Audit logging failure should not abort the main operation
        error_log('Event audit logging failed: ' . $e->getMessage());
    }
}

// Format event record for consistent JSON presentation
function formatEventRecord(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'title' => (string) $row['title'],
        'description' => (string) $row['description'],
        'category' => (string) ($row['category'] ?? 'Workshop'),
        'location' => (string) ($row['location'] ?? ''),
        'venue' => (string) ($row['location'] ?? ''), // convenient alias
        'starts_at' => (string) $row['starts_at'],
        'event_date' => (string) $row['starts_at'], // primary alias for Practical 13 requirements
        'ends_at' => $row['ends_at'] !== null ? (string) $row['ends_at'] : null,
        'capacity' => $row['capacity'] !== null ? (int) $row['capacity'] : null,
        'status' => (string) ($row['status'] ?? 'Open'),
        'poster_image' => $row['poster_image'] !== null ? (string) $row['poster_image'] : null,
        'created_at' => (string) ($row['created_at'] ?? ''),
        'updated_at' => $row['updated_at'] !== null ? (string) $row['updated_at'] : null,
    ];
}

// Route request based on HTTP method
try {
    switch ($method) {
        case 'GET':
            // -------------------------------------------------------------
            // GET: Fetch single event or all events with optional filters
            // -------------------------------------------------------------
            $eventId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

            if ($eventId > 0) {
                // Fetch single event by ID
                $stmt = $pdo->prepare(
                    'SELECT id, title, description, category, location, starts_at, ends_at, capacity, status, poster_image, created_at, updated_at
                     FROM events WHERE id = :id LIMIT 1'
                );
                $stmt->execute(['id' => $eventId]);
                $event = $stmt->fetch();

                if (!$event) {
                    sendResponse(404, false, 'Event not found with ID ' . $eventId);
                }

                sendResponse(200, true, 'Event retrieved successfully.', formatEventRecord($event));
            }

            // Fetch list of events with optional search and filters
            $search = trim((string) ($_GET['search'] ?? ''));
            $category = trim((string) ($_GET['category'] ?? ''));
            $status = trim((string) ($_GET['status'] ?? ''));

            $sql = 'SELECT id, title, description, category, location, starts_at, ends_at, capacity, status, poster_image, created_at, updated_at
                    FROM events WHERE 1=1';
            $params = [];

            if ($search !== '') {
                $sql .= ' AND (title LIKE :search1 OR description LIKE :search2 OR location LIKE :search3 OR category LIKE :search4)';
                $like = '%' . $search . '%';
                $params['search1'] = $like;
                $params['search2'] = $like;
                $params['search3'] = $like;
                $params['search4'] = $like;
            }

            if ($category !== '') {
                $sql .= ' AND category = :category';
                $params['category'] = $category;
            }

            if ($status !== '' && in_array($status, ['Open', 'Closed'], true)) {
                $sql .= ' AND status = :status';
                $params['status'] = $status;
            }

            $sql .= ' ORDER BY starts_at ASC, id DESC';

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            $events = array_map('formatEventRecord', $rows);

            // Compute summary metrics for dynamic dashboard headers
            $totalCount = count($events);
            $openCount = 0;
            $closedCount = 0;
            foreach ($events as $item) {
                if ($item['status'] === 'Open') {
                    $openCount++;
                } else {
                    $closedCount++;
                }
            }

            sendResponse(200, true, 'Events fetched successfully.', $events, [
                'meta' => [
                    'total' => $totalCount,
                    'open' => $openCount,
                    'closed' => $closedCount,
                    'search' => $search,
                    'category' => $category,
                    'status' => $status,
                ],
            ]);
            break;

        case 'POST':
            // -------------------------------------------------------------
            // POST: Create a new event [Admin Only]
            // -------------------------------------------------------------
            $admin = checkAdminAuthorization($pdo);
            $payload = getRequestPayload();

            $title = trim((string) ($payload['title'] ?? ''));
            $description = trim((string) ($payload['description'] ?? ''));
            $category = trim((string) ($payload['category'] ?? 'Workshop'));
            $location = trim((string) ($payload['location'] ?? $payload['venue'] ?? 'Campus Main Hall'));
            $eventDate = trim((string) ($payload['event_date'] ?? $payload['starts_at'] ?? ''));
            $endsAtInput = trim((string) ($payload['ends_at'] ?? ''));
            $capacity = isset($payload['capacity']) && $payload['capacity'] !== '' ? (int) $payload['capacity'] : null;
            $status = trim((string) ($payload['status'] ?? 'Open'));

            // Server-side validation
            $errors = [];
            if ($title === '') {
                $errors[] = 'Event title is required.';
            } elseif (mb_strlen($title) > 150) {
                $errors[] = 'Event title cannot exceed 150 characters.';
            }

            if ($description === '') {
                $errors[] = 'Event description is required.';
            }

            if ($eventDate === '') {
                $errors[] = 'Event date and time is required.';
            }

            $parsedDate = strtotime($eventDate);
            if ($parsedDate === false) {
                $errors[] = 'Invalid event date format.';
            }

            if ($category === '') {
                $category = 'Workshop';
            } elseif (mb_strlen($category) > 60) {
                $errors[] = 'Category cannot exceed 60 characters.';
            }

            if ($location === '') {
                $location = 'Campus Main Hall';
            } elseif (mb_strlen($location) > 180) {
                $errors[] = 'Venue location cannot exceed 180 characters.';
            }

            if (!in_array($status, ['Open', 'Closed'], true)) {
                $status = 'Open';
            }

            // Optional end time validation
            $formattedEndsAt = null;
            if ($endsAtInput !== '') {
                $parsedEnd = strtotime($endsAtInput);
                if ($parsedEnd === false) {
                    $errors[] = 'Invalid end time format.';
                } elseif ($parsedDate !== false && $parsedEnd < $parsedDate) {
                    $errors[] = 'Event end time cannot be earlier than start date/time.';
                } else {
                    $formattedEndsAt = date('Y-m-d H:i:s', $parsedEnd);
                }
            }

            if (!empty($errors)) {
                sendResponse(400, false, implode(' ', $errors), null, ['errors' => $errors]);
            }

            $formattedStartsAt = date('Y-m-d H:i:s', $parsedDate);

            // Insert into events table using PDO prepared statement
            $insertStmt = $pdo->prepare(
                'INSERT INTO events (title, description, category, location, starts_at, ends_at, capacity, status)
                 VALUES (:title, :description, :category, :location, :starts_at, :ends_at, :capacity, :status)'
            );
            $insertStmt->execute([
                'title' => $title,
                'description' => $description,
                'category' => $category,
                'location' => $location,
                'starts_at' => $formattedStartsAt,
                'ends_at' => $formattedEndsAt,
                'capacity' => $capacity,
                'status' => $status,
            ]);

            $newId = (int) $pdo->lastInsertId();

            // Fetch newly created record
            $fetchStmt = $pdo->prepare('SELECT * FROM events WHERE id = :id LIMIT 1');
            $fetchStmt->execute(['id' => $newId]);
            $createdRow = $fetchStmt->fetch();

            logEventAudit(
                $pdo,
                $newId,
                $title,
                $admin,
                'CREATE',
                "Created event via AJAX API: Category: {$category}, Venue: {$location}, Date: {$formattedStartsAt}"
            );

            sendResponse(201, true, 'Event created successfully.', formatEventRecord($createdRow));
            break;

        case 'PUT':
        case 'PATCH':
            // -------------------------------------------------------------
            // PUT / PATCH: Update an existing event [Admin Only]
            // -------------------------------------------------------------
            $admin = checkAdminAuthorization($pdo);
            $payload = getRequestPayload();

            $eventId = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($payload['id'] ?? 0);
            if ($eventId <= 0) {
                sendResponse(400, false, 'A valid numeric event ID is required to update an event.');
            }

            // Verify event exists
            $checkStmt = $pdo->prepare('SELECT * FROM events WHERE id = :id LIMIT 1');
            $checkStmt->execute(['id' => $eventId]);
            $existingEvent = $checkStmt->fetch();

            if (!$existingEvent) {
                sendResponse(404, false, 'Event not found with ID ' . $eventId);
            }

            $title = trim((string) ($payload['title'] ?? $existingEvent['title']));
            $description = trim((string) ($payload['description'] ?? $existingEvent['description']));
            $category = trim((string) ($payload['category'] ?? $existingEvent['category']));
            $location = trim((string) ($payload['location'] ?? $payload['venue'] ?? $existingEvent['location']));
            $eventDate = trim((string) ($payload['event_date'] ?? $payload['starts_at'] ?? $existingEvent['starts_at']));
            $endsAtInput = isset($payload['ends_at']) ? trim((string) $payload['ends_at']) : ($existingEvent['ends_at'] ?? '');
            $capacity = isset($payload['capacity']) && $payload['capacity'] !== '' ? (int) $payload['capacity'] : ($existingEvent['capacity'] !== null ? (int) $existingEvent['capacity'] : null);
            $status = trim((string) ($payload['status'] ?? $existingEvent['status']));

            // Server-side validation
            $errors = [];
            if ($title === '') {
                $errors[] = 'Event title cannot be empty.';
            } elseif (mb_strlen($title) > 150) {
                $errors[] = 'Event title cannot exceed 150 characters.';
            }

            if ($description === '') {
                $errors[] = 'Event description cannot be empty.';
            }

            if ($eventDate === '') {
                $errors[] = 'Event date and time is required.';
            }

            $parsedDate = strtotime($eventDate);
            if ($parsedDate === false) {
                $errors[] = 'Invalid event date format.';
            }

            if ($category === '') {
                $category = 'Workshop';
            } elseif (mb_strlen($category) > 60) {
                $errors[] = 'Category cannot exceed 60 characters.';
            }

            if ($location === '') {
                $location = 'Campus Main Hall';
            } elseif (mb_strlen($location) > 180) {
                $errors[] = 'Venue location cannot exceed 180 characters.';
            }

            if (!in_array($status, ['Open', 'Closed'], true)) {
                $status = 'Open';
            }

            $formattedEndsAt = null;
            if (array_key_exists('ends_at', $payload)) {
                $rawEnd = trim((string) $payload['ends_at']);
                if ($rawEnd !== '') {
                    $parsedEnd = strtotime($rawEnd);
                    if ($parsedEnd === false) {
                        $errors[] = 'Invalid end time format.';
                    } elseif ($parsedDate !== false && $parsedEnd < $parsedDate) {
                        $errors[] = 'Event end time cannot be earlier than start date/time.';
                    } else {
                        $formattedEndsAt = date('Y-m-d H:i:s', $parsedEnd);
                    }
                }
            } else {
                // If ends_at was not provided in the payload, retain existing only if it does not conflict with new starts_at
                if (!empty($existingEvent['ends_at'])) {
                    $parsedEnd = strtotime((string) $existingEvent['ends_at']);
                    if ($parsedEnd !== false && $parsedDate !== false && $parsedEnd >= $parsedDate) {
                        $formattedEndsAt = date('Y-m-d H:i:s', $parsedEnd);
                    }
                }
            }

            if (!empty($errors)) {
                sendResponse(400, false, implode(' ', $errors), null, ['errors' => $errors]);
            }

            $formattedStartsAt = date('Y-m-d H:i:s', $parsedDate);

            // Update using prepared statement
            $updateStmt = $pdo->prepare(
                'UPDATE events
                 SET title = :title,
                     description = :description,
                     category = :category,
                     location = :location,
                     starts_at = :starts_at,
                     ends_at = :ends_at,
                     capacity = :capacity,
                     status = :status
                 WHERE id = :id'
            );
            $updateStmt->execute([
                'title' => $title,
                'description' => $description,
                'category' => $category,
                'location' => $location,
                'starts_at' => $formattedStartsAt,
                'ends_at' => $formattedEndsAt,
                'capacity' => $capacity,
                'status' => $status,
                'id' => $eventId,
            ]);

            // Retrieve updated record
            $fetchStmt = $pdo->prepare('SELECT * FROM events WHERE id = :id LIMIT 1');
            $fetchStmt->execute(['id' => $eventId]);
            $updatedRow = $fetchStmt->fetch();

            logEventAudit(
                $pdo,
                $eventId,
                $title,
                $admin,
                'UPDATE',
                "Updated event via AJAX API: Status: {$status}, Date: {$formattedStartsAt}, Venue: {$location}"
            );

            sendResponse(200, true, 'Event updated successfully.', formatEventRecord($updatedRow));
            break;

        case 'DELETE':
            // -------------------------------------------------------------
            // DELETE: Remove an event [Admin Only]
            // -------------------------------------------------------------
            $admin = checkAdminAuthorization($pdo);
            $payload = getRequestPayload();

            $eventId = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($payload['id'] ?? 0);
            if ($eventId <= 0) {
                sendResponse(400, false, 'A valid numeric event ID is required to delete an event.');
            }

            // Check if event exists
            $checkStmt = $pdo->prepare('SELECT id, title, poster_image FROM events WHERE id = :id LIMIT 1');
            $checkStmt->execute(['id' => $eventId]);
            $existingEvent = $checkStmt->fetch();

            if (!$existingEvent) {
                sendResponse(404, false, 'Event not found with ID ' . $eventId);
            }

            // Safe dependency check: verify if student registrations exist
            try {
                $regStmt = $pdo->prepare('SELECT COUNT(*) AS total FROM registrations WHERE event_id = :id');
                $regStmt->execute(['id' => $eventId]);
                $regCount = (int) ($regStmt->fetch()['total'] ?? 0);

                if ($regCount > 0) {
                    sendResponse(
                        409,
                        false,
                        "Cannot delete event '{$existingEvent['title']}' because {$regCount} student registration(s) exist. Close the event or unregister students first."
                    );
                }
            } catch (Throwable $e) {
                // If registrations table does not exist, continue
            }

            // Delete event safely using prepared statement
            $deleteStmt = $pdo->prepare('DELETE FROM events WHERE id = :id');
            $deleteStmt->execute(['id' => $eventId]);

            // Clean up poster file if exists
            if (!empty($existingEvent['poster_image'])) {
                $posterPath = __DIR__ . '/../' . ltrim((string) $existingEvent['poster_image'], '/\\');
                if (file_exists($posterPath) && is_file($posterPath)) {
                    @unlink($posterPath);
                }
            }

            logEventAudit(
                $pdo,
                $eventId,
                (string) $existingEvent['title'],
                $admin,
                'DELETE',
                "Deleted event ID {$eventId} via AJAX API"
            );

            sendResponse(200, true, "Event '{$existingEvent['title']}' deleted successfully.", [
                'deleted_id' => $eventId,
            ]);
            break;

        default:
            sendResponse(405, false, "HTTP Method '{$method}' is not supported by this API.", null, [
                'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            ]);
            break;
    }
} catch (PDOException $e) {
    error_log('StudentHub Events API PDOException: ' . $e->getMessage());
    sendResponse(500, false, 'Database operation failed: ' . $e->getMessage());
} catch (Throwable $e) {
    error_log('StudentHub Events API Exception: ' . $e->getMessage());
    sendResponse(500, false, 'An unexpected server error occurred.');
}
