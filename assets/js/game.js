/**
 * RPS Arena - Game Page JavaScript
 * Enhanced with dramatic animations and engaging effects
 */

(function() {
    'use strict';

    // State
    let gameState = null;
    let pollInterval = null;
    let hasSubmittedMove = false;
    let isSubmittingMove = false;  // Prevents race condition during move submission
    let lastRoundNumber = 0;
    let countdownInterval = null;
    let countdownSeconds = 30;
    let consecutiveWins = 0;

    // Config
    const POLL_GAME = parseInt(document.getElementById('poll-game')?.value || 1500);
    const GAME_ID = document.getElementById('game-id')?.value;
    const MOVE_TIMEOUT = 30; // seconds

    // Move icons
    const MOVE_ICONS = {
        rock: '🪨',
        paper: '📄',
        scissors: '✂️'
    };

    // Move names for display
    const MOVE_NAMES = {
        rock: 'Rock',
        paper: 'Paper',
        scissors: 'Scissors'
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

        // Add countdown timer to battle center
        addCountdownTimer();

        // Bind move buttons with enhanced effects
        moveButtons.forEach(btn => {
            btn.addEventListener('click', () => handleMoveSelection(btn.dataset.move));
            btn.addEventListener('mouseenter', () => playHoverEffect(btn));
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

    // ============ Countdown Timer ============

    function addCountdownTimer() {
        const battleCenter = document.querySelector('.battle-center');
        if (!battleCenter) return;

        const timerHTML = `
            <div class="countdown-timer" id="countdown-timer" style="display: none;">
                <div class="countdown-ring">
                    <svg width="80" height="80">
                        <circle class="bg" cx="40" cy="40" r="36"></circle>
                        <circle class="progress" cx="40" cy="40" r="36"
                                stroke-dasharray="226.2" stroke-dashoffset="0"></circle>
                    </svg>
                    <span class="countdown-number" id="countdown-number">30</span>
                </div>
                <span class="countdown-label">seconds to choose</span>
            </div>
        `;
        battleCenter.insertAdjacentHTML('afterbegin', timerHTML);
    }

    function startCountdown() {
        stopCountdown();
        countdownSeconds = MOVE_TIMEOUT;

        const timerEl = document.getElementById('countdown-timer');
        const numberEl = document.getElementById('countdown-number');
        const ringEl = document.querySelector('.countdown-ring');
        const progressEl = document.querySelector('.countdown-ring .progress');

        if (!timerEl) return;

        timerEl.style.display = 'flex';

        const circumference = 226.2; // 2 * PI * 36

        countdownInterval = setInterval(() => {
            countdownSeconds--;

            if (numberEl) numberEl.textContent = countdownSeconds;

            // Update progress ring
            const offset = circumference * (1 - countdownSeconds / MOVE_TIMEOUT);
            if (progressEl) progressEl.style.strokeDashoffset = offset;

            // Add urgency classes
            if (ringEl) {
                ringEl.classList.remove('warning', 'danger');
                if (countdownSeconds <= 5) {
                    ringEl.classList.add('danger');
                    shakeScreen();
                } else if (countdownSeconds <= 10) {
                    ringEl.classList.add('warning');
                }
            }

            if (countdownSeconds <= 0) {
                stopCountdown();
                // Auto-select random move
                autoSelectMove();
            }
        }, 1000);
    }

    function stopCountdown() {
        if (countdownInterval) {
            clearInterval(countdownInterval);
            countdownInterval = null;
        }
        const timerEl = document.getElementById('countdown-timer');
        if (timerEl) timerEl.style.display = 'none';
    }

    function autoSelectMove() {
        const moves = ['rock', 'paper', 'scissors'];
        const randomMove = moves[Math.floor(Math.random() * moves.length)];
        handleMoveSelection(randomMove);
    }

    // ============ Visual Effects ============

    function playHoverEffect(btn) {
        if (hasSubmittedMove) return;
        btn.style.transform = 'translateY(-5px) scale(1.05)';
        setTimeout(() => {
            if (!btn.classList.contains('selected')) {
                btn.style.transform = '';
            }
        }, 200);
    }

    function shakeScreen() {
        document.body.classList.add('shake');
        setTimeout(() => document.body.classList.remove('shake'), 500);
    }

    function showClashEffect() {
        const clash = document.createElement('div');
        clash.className = 'battle-clash';
        clash.innerHTML = '<span class="clash-effect">⚔️</span>';
        document.body.appendChild(clash);

        setTimeout(() => clash.remove(), 800);
    }

    function showVictoryCelebration() {
        const celebration = document.createElement('div');
        celebration.className = 'victory-celebration';
        document.body.appendChild(celebration);

        // Create confetti
        const colors = ['#ff6b6b', '#ffd93d', '#6bcb77', '#4d96ff', '#9b59b6'];
        for (let i = 0; i < 50; i++) {
            const confetti = document.createElement('div');
            confetti.className = 'confetti';
            confetti.style.left = Math.random() * 100 + '%';
            confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
            confetti.style.animationDelay = Math.random() * 2 + 's';
            confetti.style.animationDuration = (2 + Math.random() * 2) + 's';
            celebration.appendChild(confetti);
        }

        setTimeout(() => celebration.remove(), 5000);
    }

    function showComboDisplay(combo) {
        if (combo < 2) return;

        const comboEl = document.createElement('div');
        comboEl.className = 'combo-display';
        comboEl.textContent = combo >= 3 ? `🔥 ${combo}x COMBO! 🔥` : `${combo}x Combo!`;
        document.body.appendChild(comboEl);

        setTimeout(() => comboEl.remove(), 1500);
    }

    function createParticles(x, y, color) {
        const container = document.createElement('div');
        container.className = 'particles-container';
        document.body.appendChild(container);

        for (let i = 0; i < 10; i++) {
            const particle = document.createElement('div');
            particle.className = 'particle';
            particle.style.left = x + (Math.random() - 0.5) * 100 + 'px';
            particle.style.top = y + 'px';
            particle.style.width = Math.random() * 10 + 5 + 'px';
            particle.style.height = particle.style.width;
            particle.style.backgroundColor = color;
            particle.style.animationDelay = Math.random() * 0.5 + 's';
            container.appendChild(particle);
        }

        setTimeout(() => container.remove(), 3000);
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

        // Animate score changes
        animateScoreChange(yourScoreEl, previousState?.your_score, state.your_score);
        animateScoreChange(opponentScoreEl, previousState?.opponent_score, state.opponent_score);
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
            stopCountdown();

            if (!state.opponent_move) {
                showWaitingForOpponent();
            }
        } else {
            showMoveSelection();
            hasSubmittedMove = false;
            if (!countdownInterval) {
                startCountdown();
            }
        }

        // Check for round completion
        if (state.round_complete && state.your_move && state.opponent_move) {
            showRoundResult(state, previousState);
        }

        // Update opponent status
        if (state.opponent_has_moved && !state.round_complete) {
            opponentMoveLabel.textContent = 'Ready! ✅';
            opponentChosenMove.querySelector('.move-icon').textContent = '✅';
        }

        // Update round history
        updateRoundHistory(state.completed_rounds);
    }

    function animateScoreChange(el, oldScore, newScore) {
        if (oldScore === newScore || oldScore === undefined) {
            el.textContent = newScore;
            return;
        }

        el.textContent = newScore;
        el.style.transform = 'scale(1.5)';
        el.style.color = '#10b981';

        setTimeout(() => {
            el.style.transform = '';
            el.style.color = '';
        }, 500);
    }

    // ============ Move Handling ============

    async function handleMoveSelection(move) {
        // Prevent race condition: check both flags and set submitting flag atomically
        if (hasSubmittedMove || isSubmittingMove || !gameState || gameState.status !== 'active') return;
        isSubmittingMove = true;  // Lock to prevent concurrent submissions

        stopCountdown();

        // Visual feedback
        moveButtons.forEach(btn => {
            btn.disabled = true;
            if (btn.dataset.move === move) {
                btn.classList.add('selected');
            }
        });

        // Create particles at button location
        const selectedBtn = document.querySelector(`[data-move="${move}"]`);
        if (selectedBtn) {
            const rect = selectedBtn.getBoundingClientRect();
            createParticles(rect.left + rect.width / 2, rect.top, '#e94560');
        }

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
            isSubmittingMove = false;  // Reset flag on error to allow retry
            moveButtons.forEach(btn => {
                btn.disabled = false;
                btn.classList.remove('selected');
            });
            startCountdown();
        }
    }

    function showYourMove(move) {
        moveSelection.classList.add('hidden');
        const moveIcon = yourChosenMove.querySelector('.move-icon');
        moveIcon.textContent = MOVE_ICONS[move];
        yourChosenMove.classList.add('revealed', 'reveal-sequence');
        yourMoveLabel.textContent = MOVE_NAMES[move];

        // Remove animation class after it completes
        setTimeout(() => {
            yourChosenMove.classList.remove('reveal-sequence');
        }, 500);
    }

    function showWaitingForOpponent() {
        waitingIndicator.classList.remove('hidden');
        battleStatus.querySelector('.status-text').textContent = 'Waiting for opponent...';
    }

    function showMoveSelection() {
        moveSelection.classList.remove('hidden');
        waitingIndicator.classList.add('hidden');
        isSubmittingMove = false;  // Reset submission lock for new round
        moveButtons.forEach(btn => {
            btn.disabled = false;
            btn.classList.remove('selected');
        });

        // Reset displays
        yourChosenMove.querySelector('.move-icon').textContent = '❓';
        yourChosenMove.classList.remove('revealed', 'winner', 'loser');
        yourMoveLabel.textContent = 'Choose your move';

        opponentChosenMove.querySelector('.move-icon').textContent = '❓';
        opponentChosenMove.classList.remove('revealed', 'winner', 'loser');
        opponentMoveLabel.textContent = 'Waiting...';

        battleStatus.querySelector('.status-text').textContent = 'Make your move!';
        battleStatus.classList.remove('hidden');
        roundResult.classList.add('hidden');
    }

    function showRoundResult(state, previousState) {
        waitingIndicator.classList.add('hidden');

        // Show clash effect first
        showClashEffect();

        // Dramatic delay before revealing
        setTimeout(() => {
            // Show opponent's move with animation
            const opponentIcon = opponentChosenMove.querySelector('.move-icon');
            opponentIcon.textContent = MOVE_ICONS[state.opponent_move];
            opponentChosenMove.classList.add('revealed', 'reveal-sequence');
            opponentMoveLabel.textContent = MOVE_NAMES[state.opponent_move];

            // Determine result and apply winner/loser styling
            let resultText, resultClass;
            const youWon = didYouWin(state.your_move, state.opponent_move);

            if (state.your_move === state.opponent_move) {
                resultText = "🤝 It's a Draw!";
                resultClass = 'draw';
                consecutiveWins = 0;
            } else if (youWon) {
                resultText = '🎉 You Win This Round!';
                resultClass = 'win';
                consecutiveWins++;
                yourChosenMove.classList.add('winner');
                opponentChosenMove.classList.add('loser');

                // Show combo if applicable
                showComboDisplay(consecutiveWins);

                // Particles for victory
                createParticles(window.innerWidth / 2, window.innerHeight / 2, '#10b981');
            } else {
                resultText = '💔 You Lose This Round!';
                resultClass = 'lose';
                consecutiveWins = 0;
                yourChosenMove.classList.add('loser');
                opponentChosenMove.classList.add('winner');
                shakeScreen();
            }

            // Show result with animation
            setTimeout(() => {
                roundResult.querySelector('.result-text').textContent = resultText;
                roundResult.className = 'round-result ' + resultClass;
                roundResult.classList.remove('hidden');
                battleStatus.classList.add('hidden');
            }, 300);

        }, 500);
    }

    function didYouWin(yourMove, opponentMove) {
        const wins = {
            rock: 'scissors',
            paper: 'rock',
            scissors: 'paper'
        };
        return wins[yourMove] === opponentMove;
    }

    function handleNewRound(previousState) {
        stopCountdown();

        // Brief delay before showing new round UI
        setTimeout(() => {
            battleStatus.classList.remove('hidden');
            showMoveSelection();
            startCountdown();
        }, 2500);
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
                resultClass = didYouWin(round.your_move, round.opponent_move) ? 'win' : 'lose';
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
        stopCountdown();

        // Determine result
        let icon, title, message;
        const modalContent = gameOverModal.querySelector('.modal-content');

        if (gameState.is_draw) {
            icon = '🤝';
            title = "It's a Draw!";
            message = 'Neither player could claim victory.';
            modalContent.classList.remove('victory', 'defeat');
        } else if (gameState.is_winner) {
            icon = '🏆';
            title = 'Victory!';
            message = 'Congratulations, you won the match!';
            modalContent.classList.add('victory');
            modalContent.classList.remove('defeat');
            showVictoryCelebration();
        } else {
            icon = '😔';
            title = 'Defeat';
            message = 'Better luck next time!';
            modalContent.classList.add('defeat');
            modalContent.classList.remove('victory');
            shakeScreen();
        }

        // Update modal
        const iconEl = document.getElementById('result-icon');
        iconEl.textContent = icon;
        iconEl.classList.add('trophy-bounce');

        document.getElementById('result-title').textContent = title;
        document.getElementById('result-message').textContent = message;
        document.getElementById('final-your-score').textContent = gameState.your_score;
        document.getElementById('final-opponent-score').textContent = gameState.opponent_score;
        document.getElementById('final-opponent-name').textContent = gameState.opponent_name;

        // Display rating change
        displayRatingChange(gameState.rating_info);

        // Show modal
        gameOverModal.classList.remove('hidden');
    }

    function displayRatingChange(ratingInfo) {
        const oldRatingEl = document.getElementById('old-rating');
        const newRatingEl = document.getElementById('new-rating');
        const ratingDiffEl = document.getElementById('rating-diff');

        if (!ratingInfo || !oldRatingEl) return;

        oldRatingEl.textContent = ratingInfo.old_rating;
        newRatingEl.textContent = ratingInfo.new_rating;

        const change = ratingInfo.change;
        if (change > 0) {
            ratingDiffEl.textContent = '+' + change;
            ratingDiffEl.className = 'rating-change positive';
            newRatingEl.style.color = '#10b981';
        } else if (change < 0) {
            ratingDiffEl.textContent = change;
            ratingDiffEl.className = 'rating-change negative';
            newRatingEl.style.color = '#ef4444';
        } else {
            ratingDiffEl.textContent = '±0';
            ratingDiffEl.className = 'rating-change';
            newRatingEl.style.color = '';
        }

        // Animate the rating number counting up/down
        animateRatingChange(oldRatingEl, newRatingEl, ratingInfo.old_rating, ratingInfo.new_rating);
    }

    function animateRatingChange(oldEl, newEl, oldValue, newValue) {
        const duration = 1500;
        const startTime = performance.now();

        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);

            // Easing function
            const easeOutQuart = 1 - Math.pow(1 - progress, 4);

            const currentValue = Math.round(oldValue + (newValue - oldValue) * easeOutQuart);
            newEl.textContent = currentValue;

            if (progress < 1) {
                requestAnimationFrame(update);
            }
        }

        // Delay the animation slightly
        setTimeout(() => requestAnimationFrame(update), 500);
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
            playAgainBtn.textContent = '🔍 Finding match...';

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

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
