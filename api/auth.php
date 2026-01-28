<?php
/**
 * Auth API Endpoint
 */
require_once __DIR__ . '/../includes/init.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'login':
        handleLogin();
        break;
    case 'register':
        handleRegister();
        break;
    case 'logout':
        handleLogout();
        break;
    case 'check':
        handleCheck();
        break;
    default:
        jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
}

function handleLogin() {
    $data = getPostData();
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';
    $remember = $data['remember'] ?? false;
    
    if (empty($username) || empty($password)) {
        jsonResponse(['success' => false, 'error' => 'Username and password required'], 400);
    }
    
    $result = loginUser($username, $password, $remember);
    
    if ($result['success']) {
        jsonResponse([
            'success' => true,
            'user_id' => $result['user_id'],
            'redirect' => 'lobby.php'
        ]);
    } else {
        jsonResponse($result, 401);
    }
}

function handleRegister() {
    $data = getPostData();
    $username = $data['username'] ?? '';
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    
    if (empty($username) || empty($email) || empty($password)) {
        jsonResponse(['success' => false, 'error' => 'All fields are required'], 400);
    }
    
    $result = registerUser($username, $email, $password);
    
    if ($result['success']) {
        jsonResponse([
            'success' => true,
            'user_id' => $result['user_id'],
            'redirect' => 'lobby.php'
        ]);
    } else {
        jsonResponse($result, 400);
    }
}

function handleLogout() {
    logoutUser();
    
    // Check if AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        jsonResponse(['success' => true]);
    }
    
    // Regular request - redirect
    redirect('index.php');
}

function handleCheck() {
    jsonResponse([
        'success' => true,
        'logged_in' => isLoggedIn(),
        'user_id' => getCurrentUserId()
    ]);
}
