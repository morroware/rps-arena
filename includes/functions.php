<?php
/**
 * Utility Functions
 */

if (!defined('RPS_GAME')) {
    die('Direct access not permitted');
}

/**
 * Send JSON response
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Get POST data as JSON or form data
 */
function getPostData() {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($contentType, 'application/json') !== false) {
        $json = file_get_contents('php://input');
        return json_decode($json, true) ?? [];
    }
    
    return $_POST;
}

/**
 * Sanitize string for output
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Get online players
 */
function getOnlinePlayers($limit = 20) {
    $threshold = ONLINE_THRESHOLD_SECONDS;
    $stmt = db()->prepare("
        SELECT id, username, rating, wins, losses, draws, 
               (SELECT COUNT(*) FROM games WHERE (player1_id = users.id OR player2_id = users.id) AND status = 'active') as in_game
        FROM users 
        WHERE last_active > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ORDER BY rating DESC
        LIMIT ?
    ");
    $stmt->execute([$threshold, $limit]);
    return $stmt->fetchAll();
}

/**
 * Get leaderboard
 */
function getLeaderboard($type = 'rating', $limit = 50) {
    switch ($type) {
        case 'wins':
            $orderBy = 'wins DESC, rating DESC';
            break;
        case 'winrate':
            $orderBy = 'CASE WHEN games_played > 0 THEN wins / games_played ELSE 0 END DESC, games_played DESC';
            break;
        case 'rating':
        default:
            $orderBy = 'rating DESC, wins DESC';
    }
    
    $stmt = db()->prepare("
        SELECT id, username, rating, wins, losses, draws, games_played,
               CASE WHEN games_played > 0 THEN ROUND(wins * 100.0 / games_played, 1) ELSE 0 END as win_rate
        FROM users 
        WHERE games_played > 0
        ORDER BY $orderBy
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

/**
 * Get user stats
 */
function getUserStats($userId) {
    $stmt = db()->prepare("
        SELECT u.*, 
               CASE WHEN u.games_played > 0 THEN ROUND(u.wins * 100.0 / u.games_played, 1) ELSE 0 END as win_rate,
               (SELECT COUNT(*) + 1 FROM users WHERE rating > u.rating) as rank
        FROM users u
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

/**
 * Get user's recent matches
 */
function getUserMatches($userId, $limit = 10) {
    $stmt = db()->prepare("
        SELECT g.*, 
               u1.username as player1_name,
               u2.username as player2_name
        FROM games g
        JOIN users u1 ON g.player1_id = u1.id
        JOIN users u2 ON g.player2_id = u2.id
        WHERE (g.player1_id = ? OR g.player2_id = ?) AND g.status = 'finished'
        ORDER BY g.finished_at DESC
        LIMIT ?
    ");
    $stmt->execute([$userId, $userId, $limit]);
    $matches = $stmt->fetchAll();
    
    // Format matches from user's perspective
    return array_map(function($match) use ($userId) {
        $isPlayer1 = ($match['player1_id'] == $userId);
        return [
            'game_id' => $match['id'],
            'opponent' => $isPlayer1 ? $match['player2_name'] : $match['player1_name'],
            'your_score' => $isPlayer1 ? $match['player1_score'] : $match['player2_score'],
            'opponent_score' => $isPlayer1 ? $match['player2_score'] : $match['player1_score'],
            'result' => $match['winner_id'] == $userId ? 'win' : ($match['winner_id'] === null ? 'draw' : 'loss'),
            'date' => $match['finished_at']
        ];
    }, $matches);
}

/**
 * Get user's current/active game
 */
function getUserActiveGame($userId) {
    $stmt = db()->prepare("
        SELECT id FROM games 
        WHERE (player1_id = ? OR player2_id = ?) AND status IN ('waiting', 'active')
        LIMIT 1
    ");
    $stmt->execute([$userId, $userId]);
    return $stmt->fetch();
}

/**
 * Clean up stale data
 */
function cleanupStaleData() {
    // Remove old queue entries
    $stmt = db()->prepare("DELETE FROM matchmaking_queue WHERE joined_at < DATE_SUB(NOW(), INTERVAL ? SECOND)");
    $stmt->execute([QUEUE_TIMEOUT_SECONDS]);
    
    // Mark abandoned games (both players inactive for 10 minutes)
    $stmt = db()->prepare("
        UPDATE games g
        SET status = 'abandoned'
        WHERE status = 'active'
        AND NOT EXISTS (
            SELECT 1 FROM users u 
            WHERE (u.id = g.player1_id OR u.id = g.player2_id) 
            AND u.last_active > DATE_SUB(NOW(), INTERVAL 600 SECOND)
        )
    ");
    $stmt->execute();
    
    // Clean old sessions
    $stmt = db()->prepare("DELETE FROM user_sessions WHERE expires_at < NOW()");
    $stmt->execute();
}

/**
 * Time ago helper
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } else {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }
}

/**
 * Format number with suffix (1st, 2nd, 3rd, etc.)
 */
function ordinal($number) {
    $suffixes = ['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'];
    if (($number % 100) >= 11 && ($number % 100) <= 13) {
        return $number . 'th';
    }
    return $number . $suffixes[$number % 10];
}

/**
 * Get site URL
 */
function siteUrl($path = '') {
    return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
}

/**
 * Redirect helper
 */
function redirect($path) {
    header('Location: ' . siteUrl($path));
    exit;
}

/**
 * Clean stale data periodically (1% chance per request)
 */
function maybeCleanup() {
    if (rand(1, 100) === 1) {
        cleanupStaleData();
    }
}
