<?php
/**
 * RPS Arena - Leaderboard
 */
require_once __DIR__ . '/includes/init.php';
requireAuth();

$user = getCurrentUser();
$sortBy = $_GET['sort'] ?? 'rating';
$validSorts = ['rating', 'wins', 'winrate'];
if (!in_array($sortBy, $validSorts)) {
    $sortBy = 'rating';
}

$leaderboard = getLeaderboard($sortBy, 100);
$userStats = getUserStats($user['id']);
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="leaderboard-page">
    <header class="main-header">
        <div class="header-left">
            <a href="lobby.php" class="logo">
                <span class="logo-icons">🪨📄✂️</span>
                <span class="logo-text"><?= e(SITE_NAME) ?></span>
            </a>
        </div>
        <nav class="header-nav">
            <a href="lobby.php" class="nav-link">Lobby</a>
            <a href="leaderboard.php" class="nav-link active">Leaderboard</a>
            <a href="profile.php" class="nav-link">Profile</a>
        </nav>
        <div class="header-right">
            <div class="user-info">
                <span class="username"><?= e($user['username']) ?></span>
                <div class="user-info-row">
                    <?= renderRankBadge($user['rating']) ?>
                    <span class="rating">⭐ <?= number_format($user['rating']) ?></span>
                </div>
            </div>
            <form action="api/auth.php?action=logout" method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <button type="submit" class="btn btn-small btn-outline">Logout</button>
            </form>
        </div>
    </header>
    
    <main class="leaderboard-main">
        <div class="leaderboard-header">
            <h1>🏆 Leaderboard</h1>
            <div class="sort-tabs">
                <a href="?sort=rating" class="sort-tab <?= $sortBy === 'rating' ? 'active' : '' ?>">By Rating</a>
                <a href="?sort=wins" class="sort-tab <?= $sortBy === 'wins' ? 'active' : '' ?>">By Wins</a>
                <a href="?sort=winrate" class="sort-tab <?= $sortBy === 'winrate' ? 'active' : '' ?>">By Win Rate</a>
            </div>
        </div>
        
        <!-- Your Rank Card -->
        <div class="your-rank-card">
            <div class="rank-badge">
                <span class="rank-number">#<?= number_format($userStats['rank']) ?></span>
            </div>
            <div class="rank-info">
                <span class="rank-username"><?= e($user['username']) ?> <?= renderRankBadge($userStats['rating']) ?></span>
                <span class="rank-stats">
                    ⭐ <?= number_format($userStats['rating']) ?> •
                    <?= $userStats['wins'] ?>W / <?= $userStats['losses'] ?>L / <?= $userStats['draws'] ?>D •
                    <?= $userStats['win_rate'] ?>% Win Rate
                </span>
            </div>
        </div>
        
        <!-- Leaderboard Table -->
        <div class="leaderboard-table-container">
            <!-- Mobile Card View -->
            <div class="leaderboard-cards">
                <?php if (empty($leaderboard)): ?>
                    <div class="empty-state">
                        <p>No players yet. Be the first to play!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($leaderboard as $index => $player): ?>
                        <?php $rank = $index + 1; ?>
                        <div class="leaderboard-card <?= $player['id'] == $user['id'] ? 'highlight' : '' ?>">
                            <div class="card-rank">
                                <?php if ($rank === 1): ?>🥇
                                <?php elseif ($rank === 2): ?>🥈
                                <?php elseif ($rank === 3): ?>🥉
                                <?php else: ?><?= $rank ?>
                                <?php endif; ?>
                            </div>
                            <div class="card-player">
                                <a href="profile.php?id=<?= $player['id'] ?>"><?= e($player['username']) ?></a>
                                <?= renderRankBadge($player['rating'], false) ?>
                                <?php if ($player['id'] == $user['id']): ?>
                                    <span class="you-badge">You</span>
                                <?php endif; ?>
                            </div>
                            <div class="card-rating">⭐ <?= number_format($player['rating']) ?></div>
                            <div class="card-stats">
                                <span class="card-record">
                                    <span class="wins"><?= $player['wins'] ?>W</span> /
                                    <span class="losses"><?= $player['losses'] ?>L</span> /
                                    <span class="draws"><?= $player['draws'] ?>D</span>
                                </span>
                                <div class="card-winrate">
                                    <div class="card-winrate-fill" style="width: <?= $player['win_rate'] ?>%"></div>
                                    <span class="card-winrate-text"><?= $player['win_rate'] ?>%</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Desktop Table View -->
            <table class="leaderboard-table">
                <thead>
                    <tr>
                        <th class="col-rank">Rank</th>
                        <th class="col-player">Player</th>
                        <th class="col-rating">Rating</th>
                        <th class="col-record">Record</th>
                        <th class="col-winrate">Win Rate</th>
                        <th class="col-games">Games</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leaderboard as $index => $player): ?>
                        <?php $rank = $index + 1; ?>
                        <tr class="<?= $player['id'] == $user['id'] ? 'highlight' : '' ?>">
                            <td class="col-rank">
                                <?php if ($rank === 1): ?>
                                    <span class="rank-medal gold">🥇</span>
                                <?php elseif ($rank === 2): ?>
                                    <span class="rank-medal silver">🥈</span>
                                <?php elseif ($rank === 3): ?>
                                    <span class="rank-medal bronze">🥉</span>
                                <?php else: ?>
                                    <span class="rank-number"><?= $rank ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="col-player">
                                <a href="profile.php?id=<?= $player['id'] ?>"><?= e($player['username']) ?></a>
                                <?= renderRankBadge($player['rating'], false) ?>
                                <?php if ($player['id'] == $user['id']): ?>
                                    <span class="you-badge">You</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-rating">
                                <span class="rating-value">⭐ <?= number_format($player['rating']) ?></span>
                            </td>
                            <td class="col-record">
                                <span class="wins"><?= $player['wins'] ?>W</span> /
                                <span class="losses"><?= $player['losses'] ?>L</span> /
                                <span class="draws"><?= $player['draws'] ?>D</span>
                            </td>
                            <td class="col-winrate">
                                <div class="winrate-bar">
                                    <div class="winrate-fill" style="width: <?= $player['win_rate'] ?>%"></div>
                                    <span class="winrate-text"><?= $player['win_rate'] ?>%</span>
                                </div>
                            </td>
                            <td class="col-games"><?= number_format($player['games_played']) ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($leaderboard)): ?>
                        <tr>
                            <td colspan="6" class="empty-state">
                                <p>No players yet. Be the first to play!</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
    
    <script src="assets/js/app.js"></script>
</body>
</html>
