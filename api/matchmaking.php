<?php
/**
 * Matchmaking API Endpoint
 */
require_once __DIR__ . '/../includes/init.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Require authentication for all actions
requireAuthApi();

$userId = getCurrentUserId();

switch ($action) {
    case 'join':
        handleJoinQueue($userId);
        break;
    case 'leave':
        handleLeaveQueue($userId);
        break;
    case 'status':
        handleQueueStatus($userId);
        break;
    default:
        jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
}

function handleJoinQueue($userId) {
    $result = joinQueue($userId);
    
    if ($result['success']) {
        // Immediately try to find a match
        $status = checkQueueStatus($userId);
        jsonResponse($status);
    } else {
        jsonResponse($result, 400);
    }
}

function handleLeaveQueue($userId) {
    $result = leaveQueue($userId);
    jsonResponse($result);
}

function handleQueueStatus($userId) {
    $result = checkQueueStatus($userId);
    jsonResponse($result);
}
