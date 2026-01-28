/**
 * RPS Arena - Game Page JavaScript
 */

(function() {
    'use strict';
    
    // State
    let gameState = null;
    let pollInterval = null;
    let hasSubmittedMove = false;
    let lastRoundNumber = 0;
    
    // Config
    const POLL_GAME = parseInt(document.getElementById('poll-game')?.value || 1500);
    const GAME_ID = document.getElementById('game-id')?.value;
    
    // Move icons
    const MOVE_ICONS = {
        rock: '🪨',
        paper: '📄',
        scissors: '✂️'
    };
    
    // Elements
    const moveButtons = document.querySelectorAll('.move-btn');
    const moveSelection = document.getElementById('move-selection');
    const waitingIndicator = document.getElementById('waiting-indicator');
    const yourChosenMove = document.getElementById('your-chosen-move');
    const opponentChosenMove = document.getElementById('opponent-chosen-move');
    const yourMoveLabel = document.getElementById('your-move-label');
    const opponentMoveLabel = document.getElementById('opponent-move-label');
    const battleStatus = document.getElementById('battle-status');
    const roundResult = document.getElementById('round-result');
    const yourScoreEl = document.getElementById('your-score');
    const opponentScoreEl = document.getElementById('opponent-score');
    const currentRoundEl = document.getElementById('current-round');
    const historyRoundsEl = document.getElementById('history-rounds');
    const gameOverModal = document.getElementById('game-over-modal');
    const forfeitBtn = document.getElementById('forfeit-btn');
    const forfeitModal = document.getElementById('forfeit-modal');
    const confirmForfeitBtn = document.getElementById('confirm-forfeit-btn');
    const cancelForfeitBtn = document.getElementById('cancel-forfeit-btn');
    const playAgainBtn = document.getElementById('play-again-btn');
    
    // Initialize
    function init() {
        if (!GAME_ID) {
            window.location.href = 'lobby.php';
            return;
        }
        
        // Bind move buttons
        moveButtons.forEach(btn => {
            btn.addEventListener('click', () => handleMoveSelection(btn.dataset.move));
        });
        
        // Forfeit handlers
        forfeitBtn?.addEventListener('click', showForfeitModal);
        confirmForfeitBtn?.addEventListener('click', handleForfeit);
        cancelForfeitBtn?.addEventListener('click', hideForfeitModal);
        
        // Play again
        playAgainBtn?.addEventListener('click', handlePlayAgain);
        
        // Load initial state
        loadGameState();
        
        // Start polling
        startPolling();
    }
    
    // ============ Game State ============
    
    async function loadGameState() {
        try {
            const result = await API.getGameState(GAME_ID);
            if (result.success) {
                updateGameState(result.game);
            }
        } catch (error) {
            console.error('Failed to load game state:', error);
        }
    }
    
    function updateGameState(state) {
        const previousState = gameState;
        gameState = state;
        
        // Update scores
        yourScoreEl.textContent = state.your_score;
        opponentScoreEl.textContent = state.opponent_score;
        currentRoundEl.textContent = state.current_round;
        
        // Check if game is over
        if (state.status === 'finished' || state.status === 'abandoned') {
            handleGameOver();
            return;
        }
        
        // Check if new round started
        if (previousState && state.current_round > previousState.current_round) {
            handleNewRound(previousState);
        }
        
        // Update move display based on state
        if (state.your_move) {
            showYourMove(state.your_move);
            hasSubmittedMove = true;
            
            if (!state.opponent_move) {
                showWaitingForOpponent();
            }
        } else {
            showMoveSelection();
            hasSubmittedMove = false;
        }
        
        // Check for round completion
        if (state.round_complete && state.your_move && state.opponent_move) {
            showRoundResult(state);
        }
        
        // Update opponent status
        if (state.opponent_has_moved && !state.round_complete) {
            opponentMoveLabel.textContent = 'Ready!';
            opponentChosenMove.querySelector('.move-icon').textContent = '✅';
        }
        
        // Update round history
        updateRoundHistory(state.completed_rounds);
    }
    
    // ============ Move Handling ============
    
    async function handleMoveSelection(move) {
        if (hasSubmittedMove || !gameState || gameState.status !== 'active') return;
        
        // Disable buttons
        moveButtons.forEach(btn => btn.disabled = true);
        
        try {
            const result = await API.submitMove(GAME_ID, move);
            
            if (result.success) {
                hasSubmittedMove = true;
                showYourMove(move);
                
                if (!result.game.round_complete) {
                    showWaitingForOpponent();
                } else {
                    updateGameState(result.game);
                }
            }
        } catch (error) {
            alert('Failed to submit move: ' + error.message);
            moveButtons.forEach(btn => btn.disabled = false);
        }
    }
    
    function showYourMove(move) {
        moveSelection.classList.add('hidden');
        yourChosenMove.querySelector('.move-icon').textContent = MOVE_ICONS[move];
        yourChosenMove.classList.add('revealed');
        yourMoveLabel.textContent = capitalize(move);
    }
    
    function showWaitingForOpponent() {
        waitingIndicator.classList.remove('hidden');
        battleStatus.querySelector('.status-text').textContent = 'Waiting for opponent...';
    }
    
    function showMoveSelection() {
        moveSelection.classList.remove('hidden');
        waitingIndicator.classList.add('hidden');
        moveButtons.forEach(btn => btn.disabled = false);
        
        // Reset displays
        yourChosenMove.querySelector('.move-icon').textContent = '❓';
        yourChosenMove.classList.remove('revealed');
        yourMoveLabel.textContent = 'Choose your move';
        
        opponentChosenMove.querySelector('.move-icon').textContent = '❓';
        opponentChosenMove.classList.remove('revealed');
        opponentMoveLabel.textContent = 'Waiting...';
        
        battleStatus.querySelector('.status-text').textContent = 'Make your move!';
        roundResult.classList.add('hidden');
    }
    
    function showRoundResult(state) {
        waitingIndicator.classList.add('hidden');
        
        // Show opponent's move
        opponentChosenMove.querySelector('.move-icon').textContent = MOVE_ICONS[state.opponent_move];
        opponentChosenMove.classList.add('revealed');
        opponentMoveLabel.textContent = capitalize(state.opponent_move);
        
        // Determine result
        let resultText, resultClass;
        if (state.your_move === state.opponent_move) {
            resultText = "It's a Draw!";
            resultClass = 'draw';
        } else if (
            (state.your_move === 'rock' && state.opponent_move === 'scissors') ||
            (state.your_move === 'paper' && state.opponent_move === 'rock') ||
            (state.your_move === 'scissors' && state.opponent_move === 'paper')
        ) {
            resultText = 'You Win This Round!';
            resultClass = 'win';
        } else {
            resultText = 'You Lose This Round!';
            resultClass = 'lose';
        }
        
        // Show result
        roundResult.querySelector('.result-text').textContent = resultText;
        roundResult.className = 'round-result ' + resultClass;
        roundResult.classList.remove('hidden');
        
        battleStatus.classList.add('hidden');
    }
    
    function handleNewRound(previousState) {
        // Brief delay before showing new round UI
        setTimeout(() => {
            battleStatus.classList.remove('hidden');
            showMoveSelection();
        }, 2000);
    }
    
    // ============ Round History ============
    
    function updateRoundHistory(rounds) {
        if (!historyRoundsEl || !rounds) return;
        
        if (rounds.length === 0) {
            historyRoundsEl.innerHTML = '<p class="no-history">No rounds completed yet</p>';
            return;
        }
        
        historyRoundsEl.innerHTML = rounds.map(round => {
            let resultClass = 'draw';
            if (round.your_move !== round.opponent_move) {
                const wins = { rock: 'scissors', paper: 'rock', scissors: 'paper' };
                resultClass = wins[round.your_move] === round.opponent_move ? 'win' : 'lose';
            }
            
            return `
                <div class="history-round ${resultClass}">
                    <span class="round-num">R${round.round}</span>
                    <span class="moves">
                        <span class="your-move">${MOVE_ICONS[round.your_move]}</span>
                        <span class="vs">vs</span>
                        <span class="opponent-move">${MOVE_ICONS[round.opponent_move]}</span>
                    </span>
                </div>
            `;
        }).join('');
    }
    
    // ============ Game Over ============
    
    function handleGameOver() {
        stopPolling();
        
        // Determine result
        let icon, title, message;
        
        if (gameState.is_draw) {
            icon = '🤝';
            title = "It's a Draw!";
            message = 'Neither player could claim victory.';
        } else if (gameState.is_winner) {
            icon = '🏆';
            title = 'Victory!';
            message = 'Congratulations, you won the match!';
        } else {
            icon = '😔';
            title = 'Defeat';
            message = 'Better luck next time!';
        }
        
        // Update modal
        document.getElementById('result-icon').textContent = icon;
        document.getElementById('result-title').textContent = title;
        document.getElementById('result-message').textContent = message;
        document.getElementById('final-your-score').textContent = gameState.your_score;
        document.getElementById('final-opponent-score').textContent = gameState.opponent_score;
        document.getElementById('final-opponent-name').textContent = gameState.opponent_name;
        
        // Show modal
        gameOverModal.classList.remove('hidden');
    }
    
    // ============ Forfeit ============
    
    function showForfeitModal() {
        forfeitModal.classList.remove('hidden');
    }
    
    function hideForfeitModal() {
        forfeitModal.classList.add('hidden');
    }
    
    async function handleForfeit() {
        try {
            await API.forfeitGame(GAME_ID);
            window.location.href = 'lobby.php';
        } catch (error) {
            alert('Failed to forfeit: ' + error.message);
        }
    }
    
    // ============ Play Again ============
    
    async function handlePlayAgain() {
        try {
            playAgainBtn.disabled = true;
            playAgainBtn.textContent = 'Finding match...';
            
            const result = await API.joinQueue();
            
            if (result.matched) {
                window.location.href = 'game.php?id=' + result.game_id;
            } else {
                window.location.href = 'lobby.php';
            }
        } catch (error) {
            window.location.href = 'lobby.php';
        }
    }
    
    // ============ Polling ============
    
    function startPolling() {
        pollInterval = setInterval(loadGameState, POLL_GAME);
    }
    
    function stopPolling() {
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
    }
    
    // ============ Utility ============
    
    function capitalize(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
