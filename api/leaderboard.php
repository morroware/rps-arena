<?php
/**
 * Leaderboard API Endpoint
 */
require_once __DIR__ . '/../includes/init.php';

// Require authentication
requireAuthApi();

$sortBy = $_GET['sort'] ?? 'rating';
$limit = min((int)($_GET['limit'] ?? 10), 100);

$validSorts = ['rating', 'wins', 'winrate'];
if (!in_array($sortBy, $validSorts)) {
    $sortBy = 'rating';
}

$leaderboard = getLeaderboard($sortBy, $limit);

jsonResponse([
    'success' => true,
    'leaderboard' => $leaderboard,
    'sort' => $sortBy,
    'count' => count($leaderboard)
]);
