/**
 * RPS Arena - Lobby Page JavaScript
 * Enhanced with rank badges and engaging animations
 */

(function() {
    'use strict';

    // State
    let inQueue = false;
    let queueStartTime = null;
    let queuePollInterval = null;
    let lobbyPollInterval = null;
    let waitTimeInterval = null;

    // Private Room State
    let privateRoomPollInterval = null;
    let roomExpiresAt = null;
    let roomTimerInterval = null;

    // Config
    const POLL_QUEUE = parseInt(document.getElementById('poll-queue')?.value || 2000);
    const POLL_LOBBY = parseInt(document.getElementById('poll-lobby')?.value || 5000);

    // Rank definitions (must match PHP)
    const RANKS = [
        { min: 2000, name: 'Legend', icon: '🌟', class: 'rank-legend' },
        { min: 1800, name: 'Grandmaster', icon: '👑', class: 'rank-grandmaster' },
        { min: 1600, name: 'Master', icon: '💎', class: 'rank-master' },
        { min: 1400, name: 'Diamond', icon: '💠', class: 'rank-diamond' },
        { min: 1200, name: 'Platinum', icon: '🔷', class: 'rank-platinum' },
        { min: 1000, name: 'Gold', icon: '🥇', class: 'rank-gold' },
        { min: 800, name: 'Silver', icon: '🥈', class: 'rank-silver' },
        { min: 0, name: 'Bronze', icon: '🥉', class: 'rank-bronze' }
    ];

    // Elements
    const joinQueueBtn = document.getElementById('join-queue-btn');
    const leaveQueueBtn = document.getElementById('leave-queue-btn');
    const queueStatus = document.getElementById('queue-status');
    const waitTimeEl = document.getElementById('wait-time');
    const playersWaitingEl = document.getElementById('players-waiting');
    const onlinePlayersEl = document.getElementById('online-players');
    const onlineCountEl = document.getElementById('online-count');
    const topPlayersEl = document.getElementById('top-players');
    const recentMatchesEl = document.getElementById('recent-matches');
    const matchFoundModal = document.getElementById('match-found-modal');

    // Initialize
    function init() {
        // Bind events
        joinQueueBtn?.addEventListener('click', handleJoinQueue);
        leaveQueueBtn?.addEventListener('click', handleLeaveQueue);

        // Add hover effect to join button
        if (joinQueueBtn) {
            joinQueueBtn.addEventListener('mouseenter', () => {
                joinQueueBtn.style.transform = 'translateY(-3px) scale(1.02)';
            });
            joinQueueBtn.addEventListener('mouseleave', () => {
                joinQueueBtn.style.transform = '';
            });
        }

        // Private room event bindings
        document.getElementById('create-room-btn')?.addEventListener('click', handleCreateRoom);
        document.getElementById('join-room-btn')?.addEventListener('click', handleJoinRoom);
        document.getElementById('cancel-room-btn')?.addEventListener('click', handleCancelRoom);
        document.getElementById('copy-code-btn')?.addEventListener('click', handleCopyCode);

        // Room code input - auto uppercase and handle Enter key
        const roomCodeInput = document.getElementById('room-code-input');
        if (roomCodeInput) {
            roomCodeInput.addEventListener('input', (e) => {
                e.target.value = e.target.value.toUpperCase();
            });
            roomCodeInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    handleJoinRoom();
                }
            });
        }

        // Check for existing private room
        checkExistingPrivateRoom();

        // Load initial data
        loadOnlinePlayers();
        loadTopPlayers();
        loadRecentMatches();

        // Start lobby polling
        startLobbyPolling();

        // Heartbeat to stay "online"
        setInterval(() => API.heartbeat(), 60000);

        // Add ambient particles (subtle)
        addAmbientParticles();
    }

    // ============ Rank System ============

    function getRank(rating) {
        for (const rank of RANKS) {
            if (rating >= rank.min) {
                return rank;
            }
        }
        return RANKS[RANKS.length - 1];
    }

    function renderRankBadge(rating, showName = true) {
        const rank = getRank(rating);
        const name = showName ? ' ' + rank.name : '';
        return `<span class="rank-badge-inline ${rank.class}">${rank.icon}${name}</span>`;
    }

    // ============ Ambient Effects ============

    function addAmbientParticles() {
        // Subtle floating particles in background
        const container = document.createElement('div');
        container.className = 'ambient-particles';
        container.style.cssText = 'position:fixed;inset:0;pointer-events:none;z-index:-1;overflow:hidden;';
        document.body.appendChild(container);

        for (let i = 0; i < 20; i++) {
            createAmbientParticle(container);
        }
    }

    function createAmbientParticle(container) {
        const particle = document.createElement('div');
        particle.style.cssText = `
            position: absolute;
            width: ${Math.random() * 4 + 2}px;
            height: ${Math.random() * 4 + 2}px;
            background: rgba(233, 69, 96, ${Math.random() * 0.3 + 0.1});
            border-radius: 50%;
            left: ${Math.random() * 100}%;
            top: ${Math.random() * 100}%;
            animation: floatAmbient ${10 + Math.random() * 20}s linear infinite;
        `;
        container.appendChild(particle);
    }

    // Add CSS for ambient animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes floatAmbient {
            0%, 100% { transform: translateY(0) translateX(0); opacity: 0.3; }
            25% { transform: translateY(-30px) translateX(20px); opacity: 0.5; }
            50% { transform: translateY(-50px) translateX(-10px); opacity: 0.3; }
            75% { transform: translateY(-30px) translateX(-20px); opacity: 0.5; }
        }
    `;
    document.head.appendChild(style);

    // ============ Queue Management ============

    async function handleJoinQueue() {
        try {
            joinQueueBtn.disabled = true;
            joinQueueBtn.innerHTML = '<span class="spinner" style="width:20px;height:20px;margin-right:8px;"></span> Entering Arena...';

            const result = await API.joinQueue();

            if (result.matched) {
                showMatchFound(result.game_id, result.opponent_name);
                return;
            }

            if (result.success) {
                enterQueueState();
            }
        } catch (error) {
            alert('Failed to join queue: ' + error.message);
            resetQueueButton();
        }
    }

    async function handleLeaveQueue() {
        try {
            leaveQueueBtn.disabled = true;
            await API.leaveQueue();
            exitQueueState();
        } catch (error) {
            console.error('Failed to leave queue:', error);
            leaveQueueBtn.disabled = false;
        }
    }

    function enterQueueState() {
        inQueue = true;
        queueStartTime = Date.now();

        // Update UI with animation
        joinQueueBtn.classList.add('hidden');
        leaveQueueBtn.classList.remove('hidden');
        leaveQueueBtn.disabled = false;
        queueStatus.classList.remove('hidden');

        // Animate queue status appearing
        queueStatus.style.opacity = '0';
        queueStatus.style.transform = 'translateY(20px)';
        setTimeout(() => {
            queueStatus.style.transition = 'all 0.3s ease';
            queueStatus.style.opacity = '1';
            queueStatus.style.transform = 'translateY(0)';
        }, 50);

        // Start polling
        startQueuePolling();
        startWaitTimer();
    }

    function exitQueueState() {
        inQueue = false;
        queueStartTime = null;

        // Stop polling
        stopQueuePolling();
        stopWaitTimer();

        // Update UI
        resetQueueButton();
        queueStatus.classList.add('hidden');
    }

    function resetQueueButton() {
        joinQueueBtn.classList.remove('hidden');
        joinQueueBtn.disabled = false;
        joinQueueBtn.innerHTML = '<span class="btn-icon">⚔️</span> Enter the Arena';
        leaveQueueBtn.classList.add('hidden');
    }

    // ============ Polling ============

    function startQueuePolling() {
        stopQueuePolling();
        queuePollInterval = setInterval(pollQueueStatus, POLL_QUEUE);
        pollQueueStatus(); // Immediate first poll
    }

    function stopQueuePolling() {
        if (queuePollInterval) {
            clearInterval(queuePollInterval);
            queuePollInterval = null;
        }
    }

    async function pollQueueStatus() {
        if (!inQueue) return;

        try {
            const result = await API.getQueueStatus();

            if (result.matched) {
                showMatchFound(result.game_id, result.opponent_name);
                return;
            }

            if (!result.in_queue) {
                if (result.timeout) {
                    showQueueTimeout();
                }
                exitQueueState();
                return;
            }

            // Update waiting players count with animation
            if (playersWaitingEl) {
                const oldValue = parseInt(playersWaitingEl.textContent);
                const newValue = result.players_waiting || 0;
                if (oldValue !== newValue) {
                    playersWaitingEl.style.transform = 'scale(1.3)';
                    playersWaitingEl.textContent = newValue;
                    setTimeout(() => {
                        playersWaitingEl.style.transform = '';
                    }, 200);
                }
            }
        } catch (error) {
            console.error('Queue poll error:', error);
        }
    }

    function startLobbyPolling() {
        lobbyPollInterval = setInterval(loadOnlinePlayers, POLL_LOBBY);
    }

    function startWaitTimer() {
        stopWaitTimer();
        waitTimeInterval = setInterval(updateWaitTime, 1000);
    }

    function stopWaitTimer() {
        if (waitTimeInterval) {
            clearInterval(waitTimeInterval);
            waitTimeInterval = null;
        }
    }

    function updateWaitTime() {
        if (!queueStartTime || !waitTimeEl) return;
        const seconds = Math.floor((Date.now() - queueStartTime) / 1000);
        waitTimeEl.textContent = seconds;

        // Add urgency effect after 30 seconds
        if (seconds > 30 && seconds % 2 === 0) {
            waitTimeEl.style.color = '#f59e0b';
        } else {
            waitTimeEl.style.color = '';
        }
    }

    function showQueueTimeout() {
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="result-icon">⏰</div>
                <h2>Queue Timeout</h2>
                <p>No opponents found. The arena awaits more challengers!</p>
                <div class="modal-actions">
                    <button class="btn btn-primary" onclick="this.closest('.modal').remove()">Try Again</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    // ============ Match Found ============

    function showMatchFound(gameId, opponentName) {
        exitQueueState();

        // Update modal with opponent info
        const opponentEl = document.getElementById('opponent-name');
        if (opponentEl && opponentName) {
            opponentEl.textContent = opponentName;
        }

        // Show modal with epic animation
        matchFoundModal.classList.remove('hidden');

        // Add screen flash effect
        const flash = document.createElement('div');
        flash.style.cssText = `
            position: fixed; inset: 0; background: white; z-index: 999;
            animation: flashEffect 0.5s ease-out forwards;
        `;
        document.body.appendChild(flash);

        const flashStyle = document.createElement('style');
        flashStyle.textContent = `
            @keyframes flashEffect {
                0% { opacity: 0.8; }
                100% { opacity: 0; }
            }
        `;
        document.head.appendChild(flashStyle);

        setTimeout(() => {
            flash.remove();
            flashStyle.remove();
        }, 500);

        // Redirect after animation
        setTimeout(() => {
            window.location.href = 'game.php?id=' + gameId;
        }, 2000);
    }

    // ============ Data Loading ============

    async function loadOnlinePlayers() {
        try {
            const result = await API.getOnlinePlayers(20);
            renderOnlinePlayers(result.players);
            if (onlineCountEl) {
                const oldCount = parseInt(onlineCountEl.textContent);
                const newCount = result.count;
                if (oldCount !== newCount) {
                    onlineCountEl.style.transform = 'scale(1.2)';
                    onlineCountEl.textContent = newCount;
                    setTimeout(() => {
                        onlineCountEl.style.transform = '';
                    }, 200);
                }
            }
        } catch (error) {
            console.error('Failed to load online players:', error);
        }
    }

    async function loadTopPlayers() {
        try {
            const result = await API.getLeaderboard('rating', 5);
            renderTopPlayers(result.leaderboard);
        } catch (error) {
            console.error('Failed to load top players:', error);
        }
    }

    async function loadRecentMatches() {
        try {
            const result = await API.getUserMatches(null, 5);
            renderRecentMatches(result.matches);
        } catch (error) {
            console.error('Failed to load recent matches:', error);
        }
    }

    // ============ Rendering ============

    function renderOnlinePlayers(players) {
        if (!onlinePlayersEl) return;

        if (!players || players.length === 0) {
            onlinePlayersEl.innerHTML = '<p class="empty-message">🏜️ The arena is empty... Be the first to join!</p>';
            return;
        }

        const currentUserId = document.getElementById('user-id')?.value;

        onlinePlayersEl.innerHTML = players.map((player, index) => `
            <div class="player-card ${player.id == currentUserId ? 'is-you' : ''}" style="animation: fadeSlideIn 0.3s ease forwards; animation-delay: ${index * 0.05}s; opacity: 0;">
                <div class="player-avatar">${player.in_game ? '⚔️' : '👤'}</div>
                <div class="player-info">
                    <span class="player-name">
                        ${escapeHtml(player.username)}
                        ${player.id == currentUserId ? '<span class="you-tag">(You)</span>' : ''}
                    </span>
                    <span class="player-stats">
                        ${renderRankBadge(player.rating, false)}
                        ⭐ ${player.rating} • ${player.wins}W/${player.losses}L
                    </span>
                </div>
                <div class="player-status">
                    ${player.in_game
                        ? '<span class="status in-game">⚔️ In Battle</span>'
                        : '<span class="status online">🟢 Ready</span>'
                    }
                </div>
            </div>
        `).join('');

        // Add animation styles if not already added
        if (!document.getElementById('player-animations')) {
            const style = document.createElement('style');
            style.id = 'player-animations';
            style.textContent = `
                @keyframes fadeSlideIn {
                    from { opacity: 0; transform: translateX(-20px); }
                    to { opacity: 1; transform: translateX(0); }
                }
            `;
            document.head.appendChild(style);
        }
    }

    function renderTopPlayers(players) {
        if (!topPlayersEl) return;

        if (!players || players.length === 0) {
            topPlayersEl.innerHTML = '<p class="empty-message">No champions yet!</p>';
            return;
        }

        topPlayersEl.innerHTML = players.map((player, index) => {
            const medals = ['🥇', '🥈', '🥉'];
            const medal = medals[index] || `#${index + 1}`;
            const isTop3 = index < 3;

            return `
                <div class="top-player ${isTop3 ? 'top-3' : ''}" style="${isTop3 ? 'background: rgba(255,215,0,0.05);' : ''}">
                    <span class="rank" style="${index === 0 ? 'font-size: 1.5rem;' : ''}">${medal}</span>
                    <span class="name">${escapeHtml(player.username)}</span>
                    <span class="rating" style="${isTop3 ? 'color: #ffd700;' : ''}">⭐ ${player.rating}</span>
                </div>
            `;
        }).join('');
    }

    function renderRecentMatches(matches) {
        if (!recentMatchesEl) return;

        if (!matches || matches.length === 0) {
            recentMatchesEl.innerHTML = '<p class="empty-message">🎮 No battles yet. Enter the arena!</p>';
            return;
        }

        recentMatchesEl.innerHTML = matches.map(match => {
            const resultIcon = match.result === 'win' ? '🏆' : match.result === 'loss' ? '💔' : '🤝';
            return `
                <div class="recent-match ${match.result}">
                    <span class="result-indicator ${match.result}"></span>
                    <span class="result-icon">${resultIcon}</span>
                    <span class="opponent">vs ${escapeHtml(match.opponent)}</span>
                    <span class="score">${match.your_score}-${match.opponent_score}</span>
                </div>
            `;
        }).join('');
    }

    // ============ Utility ============

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ============ Private Games ============

    async function checkExistingPrivateRoom() {
        try {
            const result = await API.getPrivateRoomStatus();
            if (result.has_room) {
                if (result.matched) {
                    // Room was matched, redirect to game
                    showMatchFound(result.game_id, result.opponent_name || 'Opponent');
                } else if (result.room_code) {
                    // Still waiting for opponent
                    showRoomWaiting(result.room_code, result.expires_at);
                }
            }
        } catch (error) {
            console.error('Failed to check existing private room:', error);
        }
    }

    async function handleCreateRoom() {
        const createBtn = document.getElementById('create-room-btn');
        try {
            createBtn.disabled = true;
            createBtn.textContent = 'Creating...';

            const result = await API.createPrivateRoom(3);
            if (result.success) {
                showRoomWaiting(result.room_code, result.expires_at);
            } else {
                alert(result.error || 'Failed to create room');
                createBtn.disabled = false;
                createBtn.textContent = 'Create Room';
            }
        } catch (error) {
            alert(error.message || 'Failed to create room');
            createBtn.disabled = false;
            createBtn.textContent = 'Create Room';
        }
    }

    async function handleJoinRoom() {
        const codeInput = document.getElementById('room-code-input');
        const joinBtn = document.getElementById('join-room-btn');
        const code = codeInput?.value.trim();

        if (!code) {
            codeInput?.focus();
            return;
        }

        try {
            joinBtn.disabled = true;
            joinBtn.textContent = 'Joining...';

            const result = await API.joinPrivateRoom(code);
            if (result.success) {
                showMatchFound(result.game_id, 'Opponent');
            } else {
                alert(result.error || 'Invalid or expired room code');
                joinBtn.disabled = false;
                joinBtn.textContent = 'Join';
            }
        } catch (error) {
            alert(error.message || 'Failed to join room');
            joinBtn.disabled = false;
            joinBtn.textContent = 'Join';
        }
    }

    async function handleCancelRoom() {
        try {
            await API.cancelPrivateRoom();
            hideRoomWaiting();
        } catch (error) {
            console.error('Failed to cancel room:', error);
            hideRoomWaiting();
        }
    }

    function handleCopyCode() {
        const code = document.getElementById('room-code-value')?.textContent;
        if (code && code !== '------') {
            navigator.clipboard.writeText(code).then(() => {
                const btn = document.getElementById('copy-code-btn');
                const originalText = btn.textContent;
                btn.textContent = 'Copied!';
                btn.classList.add('btn-success');
                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.classList.remove('btn-success');
                }, 1500);
            }).catch(() => {
                // Fallback: select the code for manual copy
                const codeEl = document.getElementById('room-code-value');
                if (codeEl) {
                    const range = document.createRange();
                    range.selectNodeContents(codeEl);
                    const selection = window.getSelection();
                    selection.removeAllRanges();
                    selection.addRange(range);
                }
            });
        }
    }

    function showRoomWaiting(code, expiresAt) {
        document.getElementById('private-actions')?.classList.add('hidden');
        document.getElementById('room-waiting')?.classList.remove('hidden');
        document.getElementById('room-code-value').textContent = code;

        roomExpiresAt = new Date(expiresAt).getTime();
        startRoomTimer();
        startPrivateRoomPolling();
    }

    function hideRoomWaiting() {
        stopPrivateRoomPolling();
        stopRoomTimer();

        document.getElementById('room-waiting')?.classList.add('hidden');
        document.getElementById('private-actions')?.classList.remove('hidden');
        document.getElementById('room-code-value').textContent = '------';
        document.getElementById('room-code-input').value = '';

        const createBtn = document.getElementById('create-room-btn');
        if (createBtn) {
            createBtn.disabled = false;
            createBtn.textContent = 'Create Room';
        }
    }

    function startPrivateRoomPolling() {
        stopPrivateRoomPolling();
        privateRoomPollInterval = setInterval(pollPrivateRoomStatus, POLL_QUEUE);
        pollPrivateRoomStatus();
    }

    function stopPrivateRoomPolling() {
        if (privateRoomPollInterval) {
            clearInterval(privateRoomPollInterval);
            privateRoomPollInterval = null;
        }
    }

    async function pollPrivateRoomStatus() {
        try {
            const result = await API.getPrivateRoomStatus();

            if (result.matched) {
                stopPrivateRoomPolling();
                stopRoomTimer();
                showMatchFound(result.game_id, result.opponent_name || 'Opponent');
                return;
            }

            if (!result.has_room || result.expired) {
                hideRoomWaiting();
                if (result.expired) {
                    showRoomExpiredModal();
                }
            }
        } catch (error) {
            console.error('Private room poll error:', error);
        }
    }

    function startRoomTimer() {
        stopRoomTimer();
        updateRoomTimer();
        roomTimerInterval = setInterval(updateRoomTimer, 1000);
    }

    function stopRoomTimer() {
        if (roomTimerInterval) {
            clearInterval(roomTimerInterval);
            roomTimerInterval = null;
        }
    }

    function updateRoomTimer() {
        const timerEl = document.getElementById('room-timer');
        if (!timerEl || !roomExpiresAt) return;

        const remaining = Math.max(0, Math.floor((roomExpiresAt - Date.now()) / 1000));
        const minutes = Math.floor(remaining / 60);
        const seconds = remaining % 60;
        timerEl.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;

        // Add urgency styling
        if (remaining <= 60) {
            timerEl.style.color = '#ef4444';
        } else if (remaining <= 120) {
            timerEl.style.color = '#f59e0b';
        } else {
            timerEl.style.color = '';
        }

        if (remaining <= 0) {
            hideRoomWaiting();
            showRoomExpiredModal();
        }
    }

    function showRoomExpiredModal() {
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="result-icon">⏰</div>
                <h2>Room Expired</h2>
                <p>No one joined your room. Create a new one to play with friends!</p>
                <div class="modal-actions">
                    <button class="btn btn-primary" onclick="this.closest('.modal').remove()">OK</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
