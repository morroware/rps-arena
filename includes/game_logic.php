<?php
/**
 * Rock Paper Scissors Game Logic
 */

if (!defined('RPS_GAME')) {
    die('Direct access not permitted');
}

/**
 * Determine winner of a round
 * @return int|null Winner ID, null for draw
 */
function determineRoundWinner($move1, $move2, $player1Id, $player2Id) {
    if ($move1 === $move2) {
        return null; // Draw
    }
    
    $wins = [
        'rock' => 'scissors',
        'paper' => 'rock',
        'scissors' => 'paper'
    ];
    
    if ($wins[$move1] === $move2) {
        return $player1Id;
    }
    
    return $player2Id;
}

/**
 * Get the move that beats another
 */
function getWinningMove($move) {
    $counters = [
        'rock' => 'paper',
        'paper' => 'scissors',
        'scissors' => 'rock'
    ];
    return $counters[$move] ?? null;
}

/**
 * Calculate new ELO ratings
 */
function calculateNewRatings($winnerRating, $loserRating) {
    $expectedWinner = 1 / (1 + pow(10, ($loserRating - $winnerRating) / 400));
    $expectedLoser = 1 - $expectedWinner;
    
    $newWinnerRating = round($winnerRating + RATING_K_FACTOR * (1 - $expectedWinner));
    $newLoserRating = round($loserRating + RATING_K_FACTOR * (0 - $expectedLoser));
    
    // Minimum rating is 100
    $newLoserRating = max(100, $newLoserRating);
    
    return [
        'winner' => $newWinnerRating,
        'loser' => $newLoserRating
    ];
}

/**
 * Calculate draw rating changes (small adjustment toward average)
 */
function calculateDrawRatings($rating1, $rating2) {
    $avgRating = ($rating1 + $rating2) / 2;
    
    // Move 10% toward average
    $new1 = round($rating1 + ($avgRating - $rating1) * 0.1);
    $new2 = round($rating2 + ($avgRating - $rating2) * 0.1);
    
    return ['player1' => $new1, 'player2' => $new2];
}

/**
 * Join the matchmaking queue
 */
function joinQueue($userId) {
    // Check if user is already in queue
    $stmt = db()->prepare("SELECT id FROM matchmaking_queue WHERE user_id = ?");
    $stmt->execute([$userId]);
    if ($stmt->fetch()) {
        return ['success' => true, 'message' => 'Already in queue'];
    }
    
    // Check if user is in an active game
    $stmt = db()->prepare("SELECT id FROM games WHERE (player1_id = ? OR player2_id = ?) AND status IN ('waiting', 'active')");
    $stmt->execute([$userId, $userId]);
    if ($game = $stmt->fetch()) {
        return ['success' => false, 'error' => 'Already in a game', 'game_id' => $game['id']];
    }
    
    // Get user's rating
    $stmt = db()->prepare("SELECT rating FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    // Add to queue
    $stmt = db()->prepare("INSERT INTO matchmaking_queue (user_id, rating) VALUES (?, ?)");
    $stmt->execute([$userId, $user['rating']]);
    
    return ['success' => true, 'message' => 'Joined queue'];
}

/**
 * Leave the matchmaking queue
 */
function leaveQueue($userId) {
    $stmt = db()->prepare("DELETE FROM matchmaking_queue WHERE user_id = ?");
    $stmt->execute([$userId]);
    return ['success' => true];
}

/**
 * Check queue status and try to find a match
 */
function checkQueueStatus($userId) {
    // First check if already matched
    $stmt = db()->prepare("SELECT id FROM games WHERE (player1_id = ? OR player2_id = ?) AND status IN ('waiting', 'active')");
    $stmt->execute([$userId, $userId]);
    if ($game = $stmt->fetch()) {
        // Remove from queue just in case
        leaveQueue($userId);
        return ['success' => true, 'matched' => true, 'game_id' => $game['id']];
    }
    
    // Check if still in queue
    $stmt = db()->prepare("SELECT id, joined_at FROM matchmaking_queue WHERE user_id = ?");
    $stmt->execute([$userId]);
    $queueEntry = $stmt->fetch();
    
    if (!$queueEntry) {
        return ['success' => true, 'in_queue' => false];
    }
    
    // Check for timeout
    $joinedAt = strtotime($queueEntry['joined_at']);
    if (time() - $joinedAt > QUEUE_TIMEOUT_SECONDS) {
        leaveQueue($userId);
        return ['success' => true, 'in_queue' => false, 'timeout' => true];
    }
    
    // Try to find a match
    $match = tryFindMatch($userId);
    if ($match) {
        return ['success' => true, 'matched' => true, 'game_id' => $match];
    }
    
    // Still waiting
    $waitTime = time() - $joinedAt;
    $playersInQueue = getQueueCount();
    
    return [
        'success' => true, 
        'in_queue' => true, 
        'wait_time' => $waitTime,
        'players_waiting' => $playersInQueue
    ];
}

/**
 * Get number of players in queue
 */
function getQueueCount() {
    $stmt = db()->query("SELECT COUNT(*) as count FROM matchmaking_queue");
    return $stmt->fetch()['count'];
}

/**
 * Try to find a match for the user
 */
function tryFindMatch($userId) {
    // Get user's queue entry
    $stmt = db()->prepare("SELECT rating FROM matchmaking_queue WHERE user_id = ?");
    $stmt->execute([$userId]);
    $userQueue = $stmt->fetch();
    
    if (!$userQueue) {
        return null;
    }
    
    // Find oldest other player in queue (FIFO matching)
    // For rating-based matching, you could add: ORDER BY ABS(rating - ?) ASC
    $stmt = db()->prepare("
        SELECT user_id FROM matchmaking_queue 
        WHERE user_id != ? 
        ORDER BY joined_at ASC 
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $opponent = $stmt->fetch();
    
    if (!$opponent) {
        return null;
    }
    
    // Create the game
    $gameId = createGame($userId, $opponent['user_id']);
    
    if ($gameId) {
        // Remove both players from queue
        leaveQueue($userId);
        leaveQueue($opponent['user_id']);
        return $gameId;
    }
    
    return null;
}

/**
 * Create a new game
 */
function createGame($player1Id, $player2Id) {
    try {
        db()->beginTransaction();
        
        // Create game
        $stmt = db()->prepare("
            INSERT INTO games (player1_id, player2_id, max_rounds, status) 
            VALUES (?, ?, ?, 'active')
        ");
        $stmt->execute([$player1Id, $player2Id, DEFAULT_MAX_ROUNDS]);
        $gameId = db()->lastInsertId();
        
        // Create first round
        $stmt = db()->prepare("INSERT INTO game_rounds (game_id, round_number) VALUES (?, 1)");
        $stmt->execute([$gameId]);
        
        db()->commit();
        return $gameId;
    } catch (Exception $e) {
        db()->rollBack();
        return null;
    }
}

/**
 * Get game state
 */
function getGameState($gameId, $userId) {
    // Get game details
    $stmt = db()->prepare("
        SELECT g.*, 
               u1.username as player1_name, u1.rating as player1_rating,
               u2.username as player2_name, u2.rating as player2_rating
        FROM games g
        JOIN users u1 ON g.player1_id = u1.id
        JOIN users u2 ON g.player2_id = u2.id
        WHERE g.id = ?
    ");
    $stmt->execute([$gameId]);
    $game = $stmt->fetch();
    
    if (!$game) {
        return null;
    }
    
    // Check if user is part of this game
    if ($game['player1_id'] != $userId && $game['player2_id'] != $userId) {
        return null;
    }
    
    // Determine which player the user is
    $isPlayer1 = ($game['player1_id'] == $userId);
    $opponentId = $isPlayer1 ? $game['player2_id'] : $game['player1_id'];
    
    // Get current round
    $stmt = db()->prepare("
        SELECT * FROM game_rounds 
        WHERE game_id = ? AND round_number = ?
    ");
    $stmt->execute([$gameId, $game['current_round']]);
    $round = $stmt->fetch();
    
    // Get all completed rounds for history
    $stmt = db()->prepare("
        SELECT * FROM game_rounds 
        WHERE game_id = ? AND player1_move IS NOT NULL AND player2_move IS NOT NULL
        ORDER BY round_number ASC
    ");
    $stmt->execute([$gameId]);
    $completedRounds = $stmt->fetchAll();
    
    // Determine user's move and opponent's move for current round
    $userMove = $isPlayer1 ? $round['player1_move'] : $round['player2_move'];
    $opponentMove = $isPlayer1 ? $round['player2_move'] : $round['player1_move'];
    
    // Only reveal opponent's move if both have played
    $showOpponentMove = ($round['player1_move'] && $round['player2_move']);
    
    return [
        'game_id' => $game['id'],
        'status' => $game['status'],
        'current_round' => $game['current_round'],
        'max_rounds' => $game['max_rounds'],
        'your_score' => $isPlayer1 ? $game['player1_score'] : $game['player2_score'],
        'opponent_score' => $isPlayer1 ? $game['player2_score'] : $game['player1_score'],
        'opponent_name' => $isPlayer1 ? $game['player2_name'] : $game['player1_name'],
        'opponent_rating' => $isPlayer1 ? $game['player2_rating'] : $game['player1_rating'],
        'your_move' => $userMove,
        'opponent_move' => $showOpponentMove ? $opponentMove : null,
        'opponent_has_moved' => $opponentMove !== null,
        'round_complete' => $showOpponentMove,
        'winner_id' => $game['winner_id'],
        'is_winner' => $game['winner_id'] == $userId,
        'is_draw' => $game['status'] === 'finished' && $game['winner_id'] === null,
        'completed_rounds' => array_map(function($r) use ($isPlayer1) {
            return [
                'round' => $r['round_number'],
                'your_move' => $isPlayer1 ? $r['player1_move'] : $r['player2_move'],
                'opponent_move' => $isPlayer1 ? $r['player2_move'] : $r['player1_move'],
                'winner' => $r['is_draw'] ? 'draw' : ($r['winner_id'] == ($isPlayer1 ? $r['player1_move'] : $r['player2_move']) ? 'you' : 'opponent')
            ];
        }, $completedRounds)
    ];
}

/**
 * Submit a move
 */
function submitMove($gameId, $userId, $move) {
    $validMoves = ['rock', 'paper', 'scissors'];
    if (!in_array($move, $validMoves)) {
        return ['success' => false, 'error' => 'Invalid move'];
    }
    
    // Get game
    $stmt = db()->prepare("SELECT * FROM games WHERE id = ? AND status = 'active'");
    $stmt->execute([$gameId]);
    $game = $stmt->fetch();
    
    if (!$game) {
        return ['success' => false, 'error' => 'Game not found or not active'];
    }
    
    // Determine which player
    $isPlayer1 = ($game['player1_id'] == $userId);
    $isPlayer2 = ($game['player2_id'] == $userId);
    
    if (!$isPlayer1 && !$isPlayer2) {
        return ['success' => false, 'error' => 'You are not in this game'];
    }
    
    $moveColumn = $isPlayer1 ? 'player1_move' : 'player2_move';
    
    // Check if already moved this round
    $stmt = db()->prepare("SELECT * FROM game_rounds WHERE game_id = ? AND round_number = ?");
    $stmt->execute([$gameId, $game['current_round']]);
    $round = $stmt->fetch();
    
    if ($round[$moveColumn] !== null) {
        return ['success' => false, 'error' => 'Already submitted move for this round'];
    }
    
    // Submit the move
    $stmt = db()->prepare("UPDATE game_rounds SET $moveColumn = ? WHERE game_id = ? AND round_number = ?");
    $stmt->execute([$move, $gameId, $game['current_round']]);
    
    // Check if both players have moved
    $stmt = db()->prepare("SELECT * FROM game_rounds WHERE game_id = ? AND round_number = ?");
    $stmt->execute([$gameId, $game['current_round']]);
    $round = $stmt->fetch();
    
    if ($round['player1_move'] && $round['player2_move']) {
        // Both moved - resolve the round
        resolveRound($game, $round);
    }
    
    return ['success' => true];
}

/**
 * Resolve a completed round
 */
function resolveRound($game, $round) {
    $winnerId = determineRoundWinner(
        $round['player1_move'], 
        $round['player2_move'],
        $game['player1_id'],
        $game['player2_id']
    );
    
    $isDraw = ($winnerId === null);
    
    // Update round
    $stmt = db()->prepare("UPDATE game_rounds SET winner_id = ?, is_draw = ?, completed_at = NOW() WHERE id = ?");
    $stmt->execute([$winnerId, $isDraw ? 1 : 0, $round['id']]);
    
    // Update game scores
    if (!$isDraw) {
        $scoreColumn = ($winnerId == $game['player1_id']) ? 'player1_score' : 'player2_score';
        $stmt = db()->prepare("UPDATE games SET $scoreColumn = $scoreColumn + 1 WHERE id = ?");
        $stmt->execute([$game['id']]);
    }
    
    // Check if game is over
    checkGameEnd($game['id']);
}

/**
 * Check if game should end
 */
function checkGameEnd($gameId) {
    $stmt = db()->prepare("SELECT * FROM games WHERE id = ?");
    $stmt->execute([$gameId]);
    $game = $stmt->fetch();
    
    $winsNeeded = ceil($game['max_rounds'] / 2);
    
    // Check for winner
    if ($game['player1_score'] >= $winsNeeded) {
        endGame($gameId, $game['player1_id']);
    } elseif ($game['player2_score'] >= $winsNeeded) {
        endGame($gameId, $game['player2_id']);
    } elseif ($game['current_round'] >= $game['max_rounds']) {
        // All rounds played - check final scores
        if ($game['player1_score'] > $game['player2_score']) {
            endGame($gameId, $game['player1_id']);
        } elseif ($game['player2_score'] > $game['player1_score']) {
            endGame($gameId, $game['player2_id']);
        } else {
            // True draw
            endGame($gameId, null);
        }
    } else {
        // Start next round
        $nextRound = $game['current_round'] + 1;
        $stmt = db()->prepare("UPDATE games SET current_round = ? WHERE id = ?");
        $stmt->execute([$nextRound, $gameId]);
        
        $stmt = db()->prepare("INSERT INTO game_rounds (game_id, round_number) VALUES (?, ?)");
        $stmt->execute([$gameId, $nextRound]);
    }
}

/**
 * End a game
 */
function endGame($gameId, $winnerId) {
    db()->beginTransaction();
    
    try {
        // Get game details
        $stmt = db()->prepare("SELECT * FROM games WHERE id = ?");
        $stmt->execute([$gameId]);
        $game = $stmt->fetch();
        
        // Get player ratings
        $stmt = db()->prepare("SELECT id, rating FROM users WHERE id IN (?, ?)");
        $stmt->execute([$game['player1_id'], $game['player2_id']]);
        $players = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Update game status
        $stmt = db()->prepare("UPDATE games SET status = 'finished', winner_id = ?, finished_at = NOW() WHERE id = ?");
        $stmt->execute([$winnerId, $gameId]);
        
        if ($winnerId !== null) {
            // There's a winner
            $loserId = ($winnerId == $game['player1_id']) ? $game['player2_id'] : $game['player1_id'];
            $newRatings = calculateNewRatings($players[$winnerId], $players[$loserId]);
            
            // Update winner
            $stmt = db()->prepare("UPDATE users SET wins = wins + 1, games_played = games_played + 1, rating = ? WHERE id = ?");
            $stmt->execute([$newRatings['winner'], $winnerId]);
            
            // Update loser
            $stmt = db()->prepare("UPDATE users SET losses = losses + 1, games_played = games_played + 1, rating = ? WHERE id = ?");
            $stmt->execute([$newRatings['loser'], $loserId]);
        } else {
            // Draw
            $newRatings = calculateDrawRatings($players[$game['player1_id']], $players[$game['player2_id']]);
            
            $stmt = db()->prepare("UPDATE users SET draws = draws + 1, games_played = games_played + 1, rating = ? WHERE id = ?");
            $stmt->execute([$newRatings['player1'], $game['player1_id']]);
            
            $stmt = db()->prepare("UPDATE users SET draws = draws + 1, games_played = games_played + 1, rating = ? WHERE id = ?");
            $stmt->execute([$newRatings['player2'], $game['player2_id']]);
        }
        
        db()->commit();
    } catch (Exception $e) {
        db()->rollBack();
        throw $e;
    }
}

/**
 * Forfeit a game
 */
function forfeitGame($gameId, $userId) {
    $stmt = db()->prepare("SELECT * FROM games WHERE id = ? AND status = 'active'");
    $stmt->execute([$gameId]);
    $game = $stmt->fetch();
    
    if (!$game) {
        return ['success' => false, 'error' => 'Game not found'];
    }
    
    if ($game['player1_id'] != $userId && $game['player2_id'] != $userId) {
        return ['success' => false, 'error' => 'You are not in this game'];
    }
    
    // Winner is the other player
    $winnerId = ($game['player1_id'] == $userId) ? $game['player2_id'] : $game['player1_id'];
    
    $stmt = db()->prepare("UPDATE games SET status = 'abandoned', winner_id = ?, finished_at = NOW() WHERE id = ?");
    $stmt->execute([$winnerId, $gameId]);
    
    // Update stats (forfeit counts as a loss)
    $stmt = db()->prepare("UPDATE users SET losses = losses + 1, games_played = games_played + 1 WHERE id = ?");
    $stmt->execute([$userId]);
    
    $stmt = db()->prepare("UPDATE users SET wins = wins + 1, games_played = games_played + 1 WHERE id = ?");
    $stmt->execute([$winnerId]);
    
    return ['success' => true];
}
