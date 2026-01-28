/**
 * RPS Arena - Lobby Page JavaScript
 */

(function() {
    'use strict';
    
    // State
    let inQueue = false;
    let queueStartTime = null;
    let queuePollInterval = null;
    let lobbyPollInterval = null;
    let waitTimeInterval = null;
    
    // Config
    const POLL_QUEUE = parseInt(document.getElementById('poll-queue')?.value || 2000);
    const POLL_LOBBY = parseInt(document.getElementById('poll-lobby')?.value || 5000);
    
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
        
        // Load initial data
        loadOnlinePlayers();
        loadTopPlayers();
        loadRecentMatches();
        
        // Start lobby polling
        startLobbyPolling();
        
        // Heartbeat to stay "online"
        setInterval(() => API.heartbeat(), 60000);
    }
    
    // ============ Queue Management ============
    
    async function handleJoinQueue() {
        try {
            joinQueueBtn.disabled = true;
            joinQueueBtn.textContent = 'Joining...';
            
            const result = await API.joinQueue();
            
            if (result.matched) {
                showMatchFound(result.game_id);
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
            await API.leaveQueue();
            exitQueueState();
        } catch (error) {
            console.error('Failed to leave queue:', error);
        }
    }
    
    function enterQueueState() {
        inQueue = true;
        queueStartTime = Date.now();
        
        // Update UI
        joinQueueBtn.classList.add('hidden');
        leaveQueueBtn.classList.remove('hidden');
        queueStatus.classList.remove('hidden');
        
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
        joinQueueBtn.innerHTML = '<span class="btn-icon">⚔️</span> Find Match';
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
                showMatchFound(result.game_id);
                return;
            }
            
            if (!result.in_queue) {
                if (result.timeout) {
                    alert('Queue timeout. Please try again.');
                }
                exitQueueState();
                return;
            }
            
            // Update waiting players count
            if (playersWaitingEl) {
                playersWaitingEl.textContent = result.players_waiting || 0;
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
    }
    
    // ============ Match Found ============
    
    function showMatchFound(gameId) {
        exitQueueState();
        
        // Show modal
        matchFoundModal.classList.remove('hidden');
        
        // Redirect after animation
        setTimeout(() => {
            window.location.href = 'game.php?id=' + gameId;
        }, 1500);
    }
    
    // ============ Data Loading ============
    
    async function loadOnlinePlayers() {
        try {
            const result = await API.getOnlinePlayers(20);
            renderOnlinePlayers(result.players);
            if (onlineCountEl) {
                onlineCountEl.textContent = result.count;
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
            onlinePlayersEl.innerHTML = '<p class="empty-message">No other players online</p>';
            return;
        }
        
        const currentUserId = document.getElementById('user-id')?.value;
        
        onlinePlayersEl.innerHTML = players.map(player => `
            <div class="player-card ${player.id == currentUserId ? 'is-you' : ''}">
                <div class="player-avatar">👤</div>
                <div class="player-info">
                    <span class="player-name">
                        ${escapeHtml(player.username)}
                        ${player.id == currentUserId ? '<span class="you-tag">(You)</span>' : ''}
                    </span>
                    <span class="player-stats">
                        ⭐ ${player.rating} • ${player.wins}W/${player.losses}L
                    </span>
                </div>
                <div class="player-status">
                    ${player.in_game ? '<span class="status in-game">In Game</span>' : '<span class="status online">Online</span>'}
                </div>
            </div>
        `).join('');
    }
    
    function renderTopPlayers(players) {
        if (!topPlayersEl) return;
        
        if (!players || players.length === 0) {
            topPlayersEl.innerHTML = '<p class="empty-message">No players yet</p>';
            return;
        }
        
        topPlayersEl.innerHTML = players.map((player, index) => {
            const medals = ['🥇', '🥈', '🥉'];
            const medal = medals[index] || `#${index + 1}`;
            
            return `
                <div class="top-player">
                    <span class="rank">${medal}</span>
                    <span class="name">${escapeHtml(player.username)}</span>
                    <span class="rating">⭐ ${player.rating}</span>
                </div>
            `;
        }).join('');
    }
    
    function renderRecentMatches(matches) {
        if (!recentMatchesEl) return;
        
        if (!matches || matches.length === 0) {
            recentMatchesEl.innerHTML = '<p class="empty-message">No matches yet</p>';
            return;
        }
        
        recentMatchesEl.innerHTML = matches.map(match => `
            <div class="recent-match ${match.result}">
                <span class="result-indicator ${match.result}"></span>
                <span class="opponent">vs ${escapeHtml(match.opponent)}</span>
                <span class="score">${match.your_score}-${match.opponent_score}</span>
            </div>
        `).join('');
    }
    
    // ============ Utility ============
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
