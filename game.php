<?php
/**
 * RPS Arena - Game Room
 */
require_once __DIR__ . '/includes/init.php';
requireAuth();

$user = getCurrentUser();
$gameId = $_GET['id'] ?? 0;

if (!$gameId) {
    redirect('lobby.php');
}

// Verify user is in this game
$gameState = getGameState($gameId, $user['id']);
if (!$gameState) {
    redirect('lobby.php');
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game #<?= e($gameId) ?> - <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="game-page">
    <header class="game-header">
        <a href="lobby.php" class="back-link">← Back to Lobby</a>
        <div class="game-info">
            <span class="game-id">Game #<?= e($gameId) ?></span>
            <span class="round-info">Round <span id="current-round"><?= $gameState['current_round'] ?></span> of <?= $gameState['max_rounds'] ?></span>
        </div>
        <button id="forfeit-btn" class="btn btn-small btn-danger">Forfeit</button>
    </header>
    
    <main class="game-main">
        <!-- Score Board -->
        <div class="scoreboard">
            <div class="player you">
                <span class="player-label">You</span>
                <span class="player-name"><?= e($user['username']) ?></span>
                <span class="player-score" id="your-score"><?= $gameState['your_score'] ?></span>
            </div>
            <div class="vs">VS</div>
            <div class="player opponent">
                <span class="player-label">Opponent</span>
                <span class="player-name" id="opponent-name"><?= e($gameState['opponent_name']) ?></span>
                <span class="player-score" id="opponent-score"><?= $gameState['opponent_score'] ?></span>
            </div>
        </div>
        
        <!-- Battle Arena -->
        <div class="battle-arena">
            <!-- Your Side -->
            <div class="battle-side your-side">
                <div class="chosen-move" id="your-chosen-move">
                    <span class="move-icon">❓</span>
                </div>
                <p class="move-label" id="your-move-label">Choose your move</p>
            </div>
            
            <!-- Center Status -->
            <div class="battle-center">
                <div id="battle-status" class="battle-status">
                    <span class="status-text">Make your move!</span>
                </div>
                <div id="round-result" class="round-result hidden">
                    <span class="result-text"></span>
                </div>
            </div>
            
            <!-- Opponent Side -->
            <div class="battle-side opponent-side">
                <div class="chosen-move" id="opponent-chosen-move">
                    <span class="move-icon">❓</span>
                </div>
                <p class="move-label" id="opponent-move-label">Waiting...</p>
            </div>
        </div>
        
        <!-- Move Selection -->
        <div class="move-selection" id="move-selection">
            <h3>Choose Your Weapon</h3>
            <div class="moves">
                <button class="move-btn" data-move="rock">
                    <span class="move-icon">🪨</span>
                    <span class="move-name">Rock</span>
                </button>
                <button class="move-btn" data-move="paper">
                    <span class="move-icon">📄</span>
                    <span class="move-name">Paper</span>
                </button>
                <button class="move-btn" data-move="scissors">
                    <span class="move-icon">✂️</span>
                    <span class="move-name">Scissors</span>
                </button>
            </div>
        </div>
        
        <!-- Waiting Indicator -->
        <div class="waiting-indicator hidden" id="waiting-indicator">
            <div class="spinner"></div>
            <p>Waiting for opponent's move...</p>
        </div>
        
        <!-- Round History -->
        <div class="round-history" id="round-history">
            <h3>Round History</h3>
            <div class="history-rounds" id="history-rounds">
                <!-- Rounds will be added here -->
            </div>
        </div>
    </main>
    
    <!-- Game Over Modal -->
    <div id="game-over-modal" class="modal hidden">
        <div class="modal-content game-over">
            <div class="result-icon" id="result-icon">🏆</div>
            <h2 id="result-title">Victory!</h2>
            <p id="result-message">You won the match!</p>

            <div class="final-score">
                <span class="score"><?= e($user['username']) ?>: <span id="final-your-score">0</span></span>
                <span class="score-divider">-</span>
                <span class="score"><span id="final-opponent-name">Opponent</span>: <span id="final-opponent-score">0</span></span>
            </div>

            <div id="rating-change-display" class="rating-change-display">
                <p>Rating Change</p>
                <div class="rating-change-row">
                    <span id="old-rating" class="text-muted">1000</span>
                    <span class="rating-arrow">→</span>
                    <span id="new-rating" class="new-rating-value">1000</span>
                    <span id="rating-diff" class="rating-change positive">+0</span>
                </div>
            </div>

            <div class="modal-actions">
                <a href="lobby.php" class="btn btn-primary">Back to Lobby</a>
                <button id="play-again-btn" class="btn btn-secondary btn-glow">⚔️ Play Again</button>
            </div>
        </div>
    </div>
    
    <!-- Forfeit Confirmation Modal -->
    <div id="forfeit-modal" class="modal hidden">
        <div class="modal-content">
            <h2>Forfeit Match?</h2>
            <p>Are you sure you want to forfeit? This will count as a loss.</p>
            <div class="modal-actions">
                <button id="confirm-forfeit-btn" class="btn btn-danger">Yes, Forfeit</button>
                <button id="cancel-forfeit-btn" class="btn btn-secondary">Cancel</button>
            </div>
        </div>
    </div>
    
    <input type="hidden" id="csrf-token" value="<?= e($csrfToken) ?>">
    <input type="hidden" id="game-id" value="<?= $gameId ?>">
    <input type="hidden" id="user-id" value="<?= $user['id'] ?>">
    <input type="hidden" id="poll-game" value="<?= POLL_GAME ?>">
    
    <script src="assets/js/api.js"></script>
    <script src="assets/js/game.js"></script>
</body>
</html>
