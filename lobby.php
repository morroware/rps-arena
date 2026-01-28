<?php
/**
 * RPS Arena - Lobby
 */
require_once __DIR__ . '/includes/init.php';
requireAuth();

$user = getCurrentUser();

// Check if user is in an active game
$activeGame = getUserActiveGame($user['id']);
if ($activeGame) {
    redirect('game.php?id=' . $activeGame['id']);
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lobby - <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="lobby-page">
    <?php $winStreak = getUserWinStreak($user['id']); ?>
    <header class="main-header">
        <div class="header-left">
            <a href="lobby.php" class="logo">
                <span class="logo-icons">🪨📄✂️</span>
                <span class="logo-text"><?= e(SITE_NAME) ?></span>
            </a>
        </div>
        <nav class="header-nav">
            <a href="lobby.php" class="nav-link active">Lobby</a>
            <a href="leaderboard.php" class="nav-link">Leaderboard</a>
            <a href="profile.php" class="nav-link">Profile</a>
        </nav>
        <div class="header-right">
            <div class="user-info">
                <span class="username"><?= e($user['username']) ?></span>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <?= renderRankBadge($user['rating']) ?>
                    <span class="rating">⭐ <?= number_format($user['rating']) ?></span>
                </div>
                <?php if ($winStreak >= 2): ?>
                <div class="streak-display <?= getStreakClass($winStreak) ?>">
                    🔥 <?= $winStreak ?> Win Streak
                </div>
                <?php endif; ?>
            </div>
            <a href="api/auth.php?action=logout" class="btn btn-small btn-outline">Logout</a>
        </div>
    </header>
    
    <main class="lobby-main">
        <div class="lobby-content">
            <!-- Matchmaking Section -->
            <section class="matchmaking-section">
                <div class="matchmaking-card">
                    <h2>⚔️ Battle Arena ⚔️</h2>
                    <p>Challenge players worldwide in epic Rock Paper Scissors battles!</p>

                    <div id="queue-status" class="queue-status hidden">
                        <div class="searching-animation queue-pulse">
                            <div class="pulse-ring"></div>
                            <div class="pulse-ring" style="animation-delay: 0.5s;"></div>
                            <span class="search-icon">🔍</span>
                        </div>
                        <p class="status-text">⚡ Searching for a worthy opponent...</p>
                        <p class="wait-time">Time elapsed: <span id="wait-time">0</span>s</p>
                        <p class="players-waiting">🎮 <span id="players-waiting">0</span> warriors in queue</p>
                    </div>

                    <div id="queue-actions">
                        <button id="join-queue-btn" class="btn btn-primary btn-large btn-glow">
                            <span class="btn-icon">⚔️</span>
                            Enter the Arena
                        </button>
                        <button id="leave-queue-btn" class="btn btn-secondary btn-large hidden">
                            🚪 Leave Queue
                        </button>
                    </div>

                    <?php if ($winStreak >= 3): ?>
                    <div style="margin-top: 1rem; padding: 0.75rem; background: rgba(255,107,53,0.1); border-radius: 8px; border: 1px solid rgba(255,107,53,0.3);">
                        <span style="font-weight: 600; color: #ff6b35;">🔥 You're on fire! <?= $winStreak ?> consecutive victories!</span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="quick-stats">
                    <div class="stat">
                        <span class="stat-value"><?= number_format($user['wins']) ?></span>
                        <span class="stat-label">Wins</span>
                    </div>
                    <div class="stat">
                        <span class="stat-value"><?= number_format($user['losses']) ?></span>
                        <span class="stat-label">Losses</span>
                    </div>
                    <div class="stat">
                        <span class="stat-value"><?= $user['games_played'] > 0 ? round($user['wins'] / $user['games_played'] * 100) : 0 ?>%</span>
                        <span class="stat-label">Win Rate</span>
                    </div>
                </div>
            </section>
            
            <!-- Online Players Section -->
            <section class="online-section">
                <div class="section-header">
                    <h2>🟢 Online Players</h2>
                    <span class="online-count" id="online-count">0</span>
                </div>
                <div class="online-players" id="online-players">
                    <div class="loading-placeholder">
                        <div class="spinner"></div>
                        <p>Loading players...</p>
                    </div>
                </div>
            </section>
        </div>
        
        <!-- Sidebar -->
        <aside class="lobby-sidebar">
            <section class="top-players">
                <h3>🏆 Top Players</h3>
                <div id="top-players">
                    <div class="loading-placeholder small">
                        <div class="spinner"></div>
                    </div>
                </div>
                <a href="leaderboard.php" class="view-all-link">View Full Leaderboard →</a>
            </section>
            
            <section class="recent-matches">
                <h3>📜 Your Recent Matches</h3>
                <div id="recent-matches">
                    <div class="loading-placeholder small">
                        <div class="spinner"></div>
                    </div>
                </div>
                <a href="profile.php" class="view-all-link">View All Matches →</a>
            </section>
        </aside>
    </main>
    
    <!-- Match Found Modal -->
    <div id="match-found-modal" class="modal hidden">
        <div class="modal-content match-found">
            <div class="match-animation">
                <span class="vs-icon">⚔️</span>
            </div>
            <h2>Match Found!</h2>
            <p>Opponent: <span id="opponent-name">Loading...</span></p>
            <p>Starting game...</p>
        </div>
    </div>
    
    <input type="hidden" id="csrf-token" value="<?= e($csrfToken) ?>">
    <input type="hidden" id="user-id" value="<?= $user['id'] ?>">
    <input type="hidden" id="poll-queue" value="<?= POLL_QUEUE ?>">
    <input type="hidden" id="poll-lobby" value="<?= POLL_LOBBY ?>">
    
    <script src="assets/js/api.js"></script>
    <script src="assets/js/lobby.js"></script>
</body>
</html>
