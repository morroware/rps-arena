<?php
/**
 * User API Endpoint
 */
require_once __DIR__ . '/../includes/init.php';

$action = $_GET['action'] ?? '';

// Require authentication for all actions
requireAuthApi();

$userId = getCurrentUserId();

switch ($action) {
    case 'online':
        handleOnlinePlayers();
        break;
    case 'stats':
        handleUserStats();
        break;
    case 'matches':
        handleUserMatches();
        break;
    case 'heartbeat':
        handleHeartbeat($userId);
        break;
    default:
        jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
}

function handleOnlinePlayers() {
    $limit = min((int)($_GET['limit'] ?? 20), 50);
    $players = getOnlinePlayers($limit);
    
    jsonResponse([
        'success' => true,
        'players' => $players,
        'count' => count($players)
    ]);
}

function handleUserStats() {
    $targetId = $_GET['id'] ?? getCurrentUserId();
    $stats = getUserStats($targetId);
    
    if (!$stats) {
        jsonResponse(['success' => false, 'error' => 'User not found'], 404);
    }
    
    jsonResponse(['success' => true, 'stats' => $stats]);
}

function handleUserMatches() {
    $targetId = $_GET['id'] ?? getCurrentUserId();
    $limit = min((int)($_GET['limit'] ?? 10), 50);
    
    $matches = getUserMatches($targetId, $limit);
    
    jsonResponse(['success' => true, 'matches' => $matches]);
}

function handleHeartbeat($userId) {
    updateLastActive($userId);
    
    // Also check if user is in an active game
    $activeGame = getUserActiveGame($userId);
    
    jsonResponse([
        'success' => true,
        'active_game' => $activeGame ? $activeGame['id'] : null
    ]);
}
