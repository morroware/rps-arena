<?php
/**
 * RPS Arena - User Profile
 */
require_once __DIR__ . '/includes/init.php';
requireAuth();

$currentUser = getCurrentUser();
$profileId = $_GET['id'] ?? $currentUser['id'];

$profileUser = getUserStats($profileId);
if (!$profileUser) {
    redirect('lobby.php');
}

$isOwnProfile = ($profileId == $currentUser['id']);
$matches = getUserMatches($profileId, 20);
$winStreak = getUserWinStreak($profileId);
$rank = getPlayerRank($profileUser['rating']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($profileUser['username']) ?>'s Profile - <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="profile-page">
    <header class="main-header">
        <div class="header-left">
            <a href="lobby.php" class="logo">
                <span class="logo-icons">🪨📄✂️</span>
                <span class="logo-text"><?= e(SITE_NAME) ?></span>
            </a>
        </div>
        <nav class="header-nav">
            <a href="lobby.php" class="nav-link">Lobby</a>
            <a href="leaderboard.php" class="nav-link">Leaderboard</a>
            <a href="profile.php" class="nav-link <?= $isOwnProfile ? 'active' : '' ?>">Profile</a>
        </nav>
        <div class="header-right">
            <div class="user-info">
                <span class="username"><?= e($currentUser['username']) ?></span>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <?= renderRankBadge($currentUser['rating']) ?>
                    <span class="rating">⭐ <?= number_format($currentUser['rating']) ?></span>
                </div>
            </div>
            <a href="api/auth.php?action=logout" class="btn btn-small btn-outline">Logout</a>
        </div>
    </header>
    
    <main class="profile-main">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-avatar" style="background: linear-gradient(135deg, var(--primary), var(--accent));">
                <span class="avatar-icon"><?= $rank['icon'] ?></span>
            </div>
            <div class="profile-info">
                <h1><?= e($profileUser['username']) ?></h1>
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                    <?= renderRankBadge($profileUser['rating']) ?>
                    <?php if ($isOwnProfile): ?>
                        <span class="profile-badge">Your Profile</span>
                    <?php endif; ?>
                </div>
                <?php if ($winStreak >= 2): ?>
                <div class="streak-display <?= getStreakClass($winStreak) ?>" style="margin-bottom: 8px;">
                    🔥 <?= $winStreak ?> Win Streak
                </div>
                <?php endif; ?>
                <p class="member-since">Member since <?= date('F Y', strtotime($profileUser['created_at'])) ?></p>
            </div>
            <div class="profile-rank">
                <span class="rank-label">Global Rank</span>
                <span class="rank-value">#<?= number_format($profileUser['rank']) ?></span>
            </div>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card rating">
                <span class="stat-icon">⭐</span>
                <span class="stat-value"><?= number_format($profileUser['rating']) ?></span>
                <span class="stat-label">Rating</span>
            </div>
            <div class="stat-card wins">
                <span class="stat-icon">🏆</span>
                <span class="stat-value"><?= number_format($profileUser['wins']) ?></span>
                <span class="stat-label">Wins</span>
            </div>
            <div class="stat-card losses">
                <span class="stat-icon">💔</span>
                <span class="stat-value"><?= number_format($profileUser['losses']) ?></span>
                <span class="stat-label">Losses</span>
            </div>
            <div class="stat-card draws">
                <span class="stat-icon">🤝</span>
                <span class="stat-value"><?= number_format($profileUser['draws']) ?></span>
                <span class="stat-label">Draws</span>
            </div>
            <div class="stat-card games">
                <span class="stat-icon">🎮</span>
                <span class="stat-value"><?= number_format($profileUser['games_played']) ?></span>
                <span class="stat-label">Total Games</span>
            </div>
            <div class="stat-card winrate">
                <span class="stat-icon">📊</span>
                <span class="stat-value"><?= $profileUser['win_rate'] ?>%</span>
                <span class="stat-label">Win Rate</span>
            </div>
        </div>
        
        <!-- Win Rate Visual -->
        <div class="winrate-visual">
            <h3>Performance Overview</h3>
            <div class="winrate-bar-large">
                <?php 
                $total = $profileUser['games_played'] ?: 1;
                $winPct = ($profileUser['wins'] / $total) * 100;
                $lossPct = ($profileUser['losses'] / $total) * 100;
                $drawPct = ($profileUser['draws'] / $total) * 100;
                ?>
                <div class="bar-segment wins" style="width: <?= $winPct ?>%" title="Wins: <?= $profileUser['wins'] ?>"></div>
                <div class="bar-segment draws" style="width: <?= $drawPct ?>%" title="Draws: <?= $profileUser['draws'] ?>"></div>
                <div class="bar-segment losses" style="width: <?= $lossPct ?>%" title="Losses: <?= $profileUser['losses'] ?>"></div>
            </div>
            <div class="bar-legend">
                <span class="legend-item wins"><span class="dot"></span> Wins (<?= round($winPct) ?>%)</span>
                <span class="legend-item draws"><span class="dot"></span> Draws (<?= round($drawPct) ?>%)</span>
                <span class="legend-item losses"><span class="dot"></span> Losses (<?= round($lossPct) ?>%)</span>
            </div>
        </div>
        
        <!-- Match History -->
        <div class="match-history">
            <h3>Recent Matches</h3>
            <?php if (empty($matches)): ?>
                <div class="empty-state">
                    <p>No matches played yet.</p>
                    <?php if ($isOwnProfile): ?>
                        <a href="lobby.php" class="btn btn-primary">Find a Match</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="matches-list">
                    <?php foreach ($matches as $match): ?>
                        <div class="match-item <?= $match['result'] ?>">
                            <div class="match-result">
                                <?php if ($match['result'] === 'win'): ?>
                                    <span class="result-badge win">WIN</span>
                                <?php elseif ($match['result'] === 'loss'): ?>
                                    <span class="result-badge loss">LOSS</span>
                                <?php else: ?>
                                    <span class="result-badge draw">DRAW</span>
                                <?php endif; ?>
                            </div>
                            <div class="match-details">
                                <span class="opponent">vs <?= e($match['opponent']) ?></span>
                                <span class="score"><?= $match['your_score'] ?> - <?= $match['opponent_score'] ?></span>
                            </div>
                            <div class="match-date">
                                <?= timeAgo($match['date']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <script src="assets/js/app.js"></script>
</body>
</html>
