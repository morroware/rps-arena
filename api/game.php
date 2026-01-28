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
    $gameId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);

    if (!$gameId || $gameId < 1) {
        jsonResponse(['success' => false, 'error' => 'Valid Game ID required'], 400);
    }
    
    $state = getGameState($gameId, $userId);
    
    if (!$state) {
        jsonResponse(['success' => false, 'error' => 'Game not found'], 404);
    }
    
    jsonResponse(['success' => true, 'game' => $state]);
}

function handleSubmitMove($userId) {
    $data = getPostData();
    $gameId = filter_var($data['game_id'] ?? 0, FILTER_VALIDATE_INT);
    $move = $data['move'] ?? '';

    if (!$gameId || $gameId < 1 || !$move) {
        jsonResponse(['success' => false, 'error' => 'Valid Game ID and move required'], 400);
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
    $gameId = filter_var($data['game_id'] ?? $_GET['id'] ?? 0, FILTER_VALIDATE_INT);

    if (!$gameId || $gameId < 1) {
        jsonResponse(['success' => false, 'error' => 'Valid Game ID required'], 400);
    }
    
    $result = forfeitGame($gameId, $userId);
    jsonResponse($result);
}
