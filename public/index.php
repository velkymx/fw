<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

// Only start session when needed (has session cookie or state-changing request)
if (session_status() === PHP_SESSION_NONE) {
    $hasSessionCookie = isset($_COOKIE[session_name()]);
    $isStateChanging = in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'PUT', 'PATCH', 'DELETE'], true);

    if ($hasSessionCookie || $isStateChanging) {
        session_start();
    }
}

require BASE_PATH . '/vendor/autoload.php';

use Fw\Core\Application;

$app = Application::getInstance();
$app->run();
