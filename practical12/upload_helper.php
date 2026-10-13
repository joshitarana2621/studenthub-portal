<?php

declare(strict_types=1);

/**
 * StudentHub - Practical 12: Event Poster Upload & Validation Helper
 * Provides server-side MIME detection, size checking, random filename generation,
 * safe file removal, and event audit logging.
 */

const MAX_POSTER_FILE_SIZE = 2 * 1024 * 1024; // 2 MB in bytes

const ALLOWED_POSTER_MIME_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

/**
 * Validates and stores an uploaded event poster image.
 *
 * @param array<string, mixed> $file Array from $_FILES['poster']
 * @param bool $isTest Set to true in automated CLI test environments
 * @return array{success: bool, error?: string, filename?: string, relative_path?: string, full_path?: string}
 */
function validate_and_upload_poster(array $file, bool $isTest = false): array
{
    // 1. Verify upload error code
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return ['success' => false, 'error' => 'No file was uploaded.'];
    }

    if ($errorCode === UPLOAD_ERR_INI_SIZE || $errorCode === UPLOAD_ERR_FORM_SIZE) {
        return ['success' => false, 'error' => 'The uploaded file exceeds the maximum allowed file size of 2 MB.'];
    }

    if ($errorCode !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload failed with server error code: ' . $errorCode];
    }

    $tmpPath = (string) ($file['tmp_name'] ?? '');
    if ($tmpPath === '' || (!is_uploaded_file($tmpPath) && !$isTest)) {
        return ['success' => false, 'error' => 'Uploaded temporary file is invalid or missing.'];
    }

    // 2. Check file size (Max 2 MB)
    $fileSize = (int) ($file['size'] ?? 0);
    if ($fileSize <= 0) {
        return ['success' => false, 'error' => 'The uploaded file is empty.'];
    }

    if ($fileSize > MAX_POSTER_FILE_SIZE) {
        $sizeInMb = round($fileSize / (1024 * 1024), 2);
        return [
            'success' => false,
            'error' => "File size ({$sizeInMb} MB) exceeds the maximum allowed limit of 2 MB."
        ];
    }

    // 3. Inspect genuine MIME type using finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        return ['success' => false, 'error' => 'Could not initialize MIME type detector.'];
    }
    $detectedMime = (string) finfo_file($finfo, $tmpPath);
    finfo_close($finfo);

    // 4. Verify against allowed MIME types whitelist (Strictly JPEG, PNG, WebP)
    if (!array_key_exists($detectedMime, ALLOWED_POSTER_MIME_TYPES)) {
        return [
            'success' => false,
            'error' => "Unsupported file type ('{$detectedMime}'). Only JPEG, PNG, and WebP images are permitted."
        ];
    }

    // 5. Inspect image structure via getimagesize() to reject malicious disguised files (e.g. php/html renamed to .jpg)
    $imgInfo = @getimagesize($tmpPath);
    if ($imgInfo === false) {
        return ['success' => false, 'error' => 'The uploaded file is not a valid or readable image.'];
    }

    // Cross-check image MIME from getimagesize with finfo MIME
    $imageMime = (string) ($imgInfo['mime'] ?? '');
    if ($imageMime !== $detectedMime) {
        return ['success' => false, 'error' => 'Security check failed: Image header does not match file MIME type.'];
    }

    // 6. Generate secure random filename
    $extension = ALLOWED_POSTER_MIME_TYPES[$detectedMime];
    $randomName = 'poster_' . bin2hex(random_bytes(16)) . '.' . $extension;

    $uploadDir = realpath(__DIR__ . '/../uploads/event-posters');
    if ($uploadDir === false) {
        $uploadDir = __DIR__ . '/../uploads/event-posters';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $uploadDir = realpath($uploadDir);
    }

    $destination = $uploadDir . DIRECTORY_SEPARATOR . $randomName;
    $relativePath = 'uploads/event-posters/' . $randomName;

    // 7. Move file securely
    $saved = $isTest ? @copy($tmpPath, $destination) : @move_uploaded_file($tmpPath, $destination);
    if (!$saved) {
        return ['success' => false, 'error' => 'Failed to save uploaded file to destination directory.'];
    }

    return [
        'success' => true,
        'filename' => $randomName,
        'relative_path' => $relativePath,
        'full_path' => $destination,
    ];
}

/**
 * Safely deletes a poster file from disk without directory traversal risk.
 */
function delete_event_poster(?string $relativePath): bool
{
    if (empty($relativePath)) {
        return false;
    }

    // Sanitize and ensure path starts with uploads/event-posters/
    $normalized = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relativePath);
    $baseUploadDir = realpath(__DIR__ . '/../uploads/event-posters');
    $targetFile = realpath(__DIR__ . '/../' . $normalized);

    if ($baseUploadDir === false || $targetFile === false) {
        return false;
    }

    // Ensure the target file is inside the upload directory
    if (!str_starts_with($targetFile, $baseUploadDir . DIRECTORY_SEPARATOR)) {
        return false;
    }

    if (is_file($targetFile)) {
        return @unlink($targetFile);
    }

    return false;
}

/**
 * Records an entry in the event audit log.
 */
function log_event_audit(
    mysqli $conn,
    ?int $eventId,
    string $eventTitle,
    ?int $adminId,
    string $adminName,
    string $actionType,
    string $details
): void {
    $stmt = $conn->prepare(
        "INSERT INTO event_audit_log (event_id, event_title, admin_id, admin_name, action_type, details, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())"
    );
    if ($stmt !== false) {
        $stmt->bind_param('isisss', $eventId, $eventTitle, $adminId, $adminName, $actionType, $details);
        $stmt->execute();
        $stmt->close();
    }
}
