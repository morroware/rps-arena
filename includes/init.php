<?php
/**
 * Main Bootstrap File
 * Include this at the top of every page
 */

// Define app constant to prevent direct file access
define('RPS_GAME', true);

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Load configuration
$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    // Redirect to installer
    $installPath = dirname($_SERVER['SCRIPT_NAME']) . '/install.php';
    header('Location: ' . $installPath);
    exit;
}

require_once $configFile;

// Set error display based on debug mode
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    ini_set('display_errors', 1);
}

// Set timezone
date_default_timezone_set('UTC');

// Load core files
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/game_logic.php';

// Initialize session
initSession();

// Maybe run cleanup
maybeCleanup();
