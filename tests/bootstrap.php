<?php

declare(strict_types=1);

/**
 * PHPUnit Bootstrap File
 *
 * Sets up the testing environment.
 */

// Define base path for tests
define('BASE_PATH', dirname(__DIR__));

// Load Composer autoloader
require BASE_PATH . '/vendor/autoload.php';

// Start session for tests that need it
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set up error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');
