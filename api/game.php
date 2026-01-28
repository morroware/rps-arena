<?php
/**
 * Game API Endpoint
 */
require_once __DIR__ . '/../includes/init.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Require authentication for all actions
requireAuthApi();

$userId = getCurrentUserId();

switch ($action) {
    case 'state':
        handleGetState($userId);
        break;
    case 'move':
        handleSubmitMove($userId);
        break;
    case 'forfeit':
        handleForfeit($userId);
        break;
    default:
        jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
}

function handleGetState($userId) {
    $gameId = $_GET['id'] ?? 0;
    
    if (!$gameId) {
        jsonResponse(['success' => false, 'error' => 'Game ID required'], 400);
    }
    
    $state = getGameState($gameId, $userId);
    
    if (!$state) {
        jsonResponse(['success' => false, 'error' => 'Game not found'], 404);
    }
    
    jsonResponse(['success' => true, 'game' => $state]);
}

function handleSubmitMove($userId) {
    $data = getPostData();
    $gameId = $data['game_id'] ?? 0;
    $move = $data['move'] ?? '';
    
    if (!$gameId || !$move) {
        jsonResponse(['success' => false, 'error' => 'Game ID and move required'], 400);
    }
    
    $result = submitMove($gameId, $userId, $move);
    
    if ($result['success']) {
        // Return updated game state
        $state = getGameState($gameId, $userId);
        jsonResponse(['success' => true, 'game' => $state]);
    } else {
        jsonResponse($result, 400);
    }
}

function handleForfeit($userId) {
    $data = getPostData();
    $gameId = $data['game_id'] ?? $_GET['id'] ?? 0;
    
    if (!$gameId) {
        jsonResponse(['success' => false, 'error' => 'Game ID required'], 400);
    }
    
    $result = forfeitGame($gameId, $userId);
    jsonResponse($result);
}
