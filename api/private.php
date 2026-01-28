<?php
/**
 * Private Game Rooms API Endpoint
 */
require_once __DIR__ . '/../includes/init.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Require authentication for all actions
requireAuthApi();

$userId = getCurrentUserId();

switch ($action) {
    case 'create':
        handleCreate($userId);
        break;
    case 'join':
        handleJoin($userId);
        break;
    case 'cancel':
        handleCancel($userId);
        break;
    case 'status':
        handleStatus($userId);
        break;
    default:
        jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
}

function handleCreate($userId) {
    $data = getPostData();
    $maxRounds = (int)($data['max_rounds'] ?? DEFAULT_MAX_ROUNDS);

    // Validate max_rounds is odd and between 1-9
    if ($maxRounds < 1 || $maxRounds > 9 || $maxRounds % 2 === 0) {
        $maxRounds = DEFAULT_MAX_ROUNDS;
    }

    $result = createPrivateRoom($userId, $maxRounds);
    jsonResponse($result, $result['success'] ? 200 : 400);
}

function handleJoin($userId) {
    $data = getPostData();
    $code = strtoupper(trim($data['code'] ?? ''));

    if (empty($code)) {
        jsonResponse(['success' => false, 'error' => 'Room code required'], 400);
    }

    $result = joinPrivateRoom($userId, $code);
    jsonResponse($result, $result['success'] ? 200 : 400);
}

function handleCancel($userId) {
    $result = cancelPrivateRoom($userId);
    jsonResponse($result);
}

function handleStatus($userId) {
    $result = checkPrivateRoomStatus($userId);
    jsonResponse($result);
}
