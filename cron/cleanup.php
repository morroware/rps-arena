#!/usr/bin/env php
<?php
/**
 * RPS Arena - Cleanup Cron Job
 *
 * This script should be run every 5 minutes via cPanel cron jobs.
 *
 * cPanel Cron Setup:
 * 1. Log into cPanel
 * 2. Go to "Cron Jobs" under "Advanced"
 * 3. Add a new cron job with:
 *    - Common Settings: Every 5 minutes (cron: 0,5,10,15,20,25,30,35,40,45,50,55 every hour)
 *    - Command: /usr/bin/php /home/yourusername/public_html/rps-arena/cron/cleanup.php
 *
 * This script performs:
 * - Removes stale queue entries (older than 5 minutes)
 * - Marks abandoned games (both players inactive for 10 minutes)
 * - Cleans expired session tokens
 * - Optionally notifies players of abandoned games
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from the command line.');
}

// Change to script directory
chdir(__DIR__ . '/..');

// Load application
define('RPS_GAME', true);

// Load config (check if it exists)
$configFile = __DIR__ . '/../includes/config.php';
if (!file_exists($configFile)) {
    echo "Error: Configuration file not found. Please run the installer first.\n";
    exit(1);
}

require_once $configFile;
require_once __DIR__ . '/../includes/db.php';

// Run cleanup
echo "RPS Arena Cleanup - " . date('Y-m-d H:i:s') . "\n";
echo str_repeat('-', 50) . "\n";

try {
    $stats = runCleanup();

    echo "Cleanup completed successfully!\n";
    echo "- Stale queue entries removed: {$stats['queue']}\n";
    echo "- Abandoned games marked: {$stats['games']}\n";
    echo "- Expired sessions cleaned: {$stats['sessions']}\n";
    echo "- Orphaned rounds cleaned: {$stats['rounds']}\n";

} catch (Exception $e) {
    echo "Error during cleanup: " . $e->getMessage() . "\n";
    exit(1);
}

function runCleanup() {
    $stats = [
        'queue' => 0,
        'games' => 0,
        'sessions' => 0,
        'rounds' => 0
    ];

    $pdo = db();

    // 1. Remove stale queue entries (older than 5 minutes)
    $stmt = $pdo->prepare("
        DELETE FROM matchmaking_queue
        WHERE joined_at < DATE_SUB(NOW(), INTERVAL 300 SECOND)
    ");
    $stmt->execute();
    $stats['queue'] = $stmt->rowCount();

    // 2. Find and mark abandoned games
    // A game is abandoned if both players have been inactive for 10+ minutes
    $stmt = $pdo->prepare("
        SELECT g.id, g.player1_id, g.player2_id, g.player1_score, g.player2_score
        FROM games g
        JOIN users u1 ON g.player1_id = u1.id
        JOIN users u2 ON g.player2_id = u2.id
        WHERE g.status = 'active'
        AND u1.last_active < DATE_SUB(NOW(), INTERVAL 600 SECOND)
        AND u2.last_active < DATE_SUB(NOW(), INTERVAL 600 SECOND)
    ");
    $stmt->execute();
    $abandonedGames = $stmt->fetchAll();

    foreach ($abandonedGames as $game) {
        // Determine winner based on current score, or null for draw/abandon
        $winnerId = null;
        if ($game['player1_score'] > $game['player2_score']) {
            $winnerId = $game['player1_id'];
        } elseif ($game['player2_score'] > $game['player1_score']) {
            $winnerId = $game['player2_id'];
        }

        $stmt = $pdo->prepare("
            UPDATE games
            SET status = 'abandoned', winner_id = ?, finished_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$winnerId, $game['id']]);
        $stats['games']++;
    }

    // 3. Clean expired session tokens
    $stmt = $pdo->prepare("
        DELETE FROM user_sessions
        WHERE expires_at < NOW()
    ");
    $stmt->execute();
    $stats['sessions'] = $stmt->rowCount();

    // 4. Clean orphaned game rounds (from games that were deleted)
    $stmt = $pdo->prepare("
        DELETE gr FROM game_rounds gr
        LEFT JOIN games g ON gr.game_id = g.id
        WHERE g.id IS NULL
    ");
    $stmt->execute();
    $stats['rounds'] = $stmt->rowCount();

    // 5. Update last_active for users who haven't been seen in a while
    // This helps with accurate online counts
    // (optional: remove users who haven't logged in for 90 days from online calculations)

    return $stats;
}
