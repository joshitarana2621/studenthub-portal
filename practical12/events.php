<?php

declare(strict_types=1);

/**
 * StudentHub - Practical 12: Event Management Redirector
 * Forwards requests from pages/events.php to admin/events.php
 */

$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: ../admin/events.php' . $query);
exit;
