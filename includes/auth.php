<?php
/**
 * Authentication Helper Functions
 */

if (!defined('RPS_GAME')) {
    die('Direct access not permitted');
}

/**
 * Start secure session
 */
function initSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    initSession();
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    initSession();
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user data
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $stmt = db()->prepare("SELECT id, username, email, wins, losses, draws, rating, games_played, created_at FROM users WHERE id = ?");
    $stmt->execute([getCurrentUserId()]);
    return $stmt->fetch();
}

/**
 * Register a new user
 */
function registerUser($username, $email, $password) {
    // Validate input
    $username = trim($username);
    $email = trim(strtolower($email));
    
    if (strlen($username) < 3 || strlen($username) > 20) {
        return ['success' => false, 'error' => 'Username must be 3-20 characters'];
    }
    
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        return ['success' => false, 'error' => 'Username can only contain letters, numbers, and underscores'];
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Invalid email address'];
    }
    
    if (strlen($password) < 6) {
        return ['success' => false, 'error' => 'Password must be at least 6 characters'];
    }
    
    // Check if username or email exists
    $stmt = db()->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'Username or email already exists'];
    }
    
    // Create user
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = db()->prepare("INSERT INTO users (username, email, password_hash, rating) VALUES (?, ?, ?, ?)");
    
    try {
        $stmt->execute([$username, $email, $passwordHash, RATING_START]);
        $userId = db()->lastInsertId();
        
        // Auto-login after registration
        loginUserById($userId);
        
        return ['success' => true, 'user_id' => $userId];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Registration failed. Please try again.'];
    }
}

/**
 * Login user by credentials
 */
function loginUser($username, $password, $remember = false) {
    $stmt = db()->prepare("SELECT id, password_hash FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'error' => 'Invalid username or password'];
    }
    
    loginUserById($user['id']);
    
    // Handle "remember me"
    if ($remember) {
        createRememberToken($user['id']);
    }
    
    return ['success' => true, 'user_id' => $user['id']];
}

/**
 * Login user by ID (internal use)
 */
function loginUserById($userId) {
    initSession();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['login_time'] = time();
    
    // Update last active
    updateLastActive($userId);
}

/**
 * Logout user
 */
function logoutUser() {
    initSession();
    
    // Clear remember token if exists
    if (isset($_COOKIE['remember_token'])) {
        $tokenHash = hash('sha256', $_COOKIE['remember_token']);
        $stmt = db()->prepare("DELETE FROM user_sessions WHERE token_hash = ?");
        $stmt->execute([$tokenHash]);
        
        setcookie('remember_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
    }
    
    $_SESSION = [];
    session_destroy();
}

/**
 * Create remember me token
 */
function createRememberToken($userId) {
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', strtotime('+' . REMEMBER_ME_DAYS . ' days'));
    
    $stmt = db()->prepare("INSERT INTO user_sessions (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $tokenHash, $expires]);
    
    setcookie('remember_token', $token, [
        'expires' => strtotime($expires),
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

/**
 * Check remember me token
 */
function checkRememberToken() {
    if (isLoggedIn()) {
        return true;
    }
    
    if (!isset($_COOKIE['remember_token'])) {
        return false;
    }
    
    $tokenHash = hash('sha256', $_COOKIE['remember_token']);
    $stmt = db()->prepare("SELECT user_id FROM user_sessions WHERE token_hash = ? AND expires_at > NOW()");
    $stmt->execute([$tokenHash]);
    $session = $stmt->fetch();
    
    if ($session) {
        loginUserById($session['user_id']);
        return true;
    }
    
    // Invalid token, clear cookie
    setcookie('remember_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
    return false;
}

/**
 * Update user's last active timestamp
 */
function updateLastActive($userId = null) {
    $userId = $userId ?? getCurrentUserId();
    if (!$userId) return;
    
    $stmt = db()->prepare("UPDATE users SET last_active = NOW() WHERE id = ?");
    $stmt->execute([$userId]);
}

/**
 * Generate CSRF token
 */
function generateCsrfToken() {
    initSession();
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Verify CSRF token
 */
function verifyCsrfToken($token) {
    initSession();
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Require authentication (redirect if not logged in)
 */
function requireAuth() {
    checkRememberToken();
    if (!isLoggedIn()) {
        header('Location: index.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
    updateLastActive();
}

/**
 * Require authentication for API (return JSON error)
 */
function requireAuthApi() {
    checkRememberToken();
    if (!isLoggedIn()) {
        jsonResponse(['success' => false, 'error' => 'Not authenticated'], 401);
    }
    updateLastActive();
}
