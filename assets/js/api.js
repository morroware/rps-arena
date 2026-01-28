/**
 * RPS Arena - API Wrapper
 */

const API = {
    baseUrl: 'api/',
    
    /**
     * Make an API request
     */
    async request(endpoint, options = {}) {
        const url = this.baseUrl + endpoint;
        const defaults = {
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };
        
        const config = { ...defaults, ...options };
        
        if (config.body && typeof config.body === 'object') {
            config.body = JSON.stringify(config.body);
        }
        
        try {
            const response = await fetch(url, config);
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || 'Request failed');
            }
            
            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    },
    
    /**
     * GET request
     */
    async get(endpoint) {
        return this.request(endpoint, { method: 'GET' });
    },
    
    /**
     * POST request
     */
    async post(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'POST',
            body: data
        });
    },
    
    // ============ Auth ============
    
    async login(username, password, remember = false) {
        return this.post('auth.php?action=login', { username, password, remember });
    },
    
    async register(username, email, password) {
        return this.post('auth.php?action=register', { username, email, password });
    },
    
    async logout() {
        return this.post('auth.php?action=logout');
    },
    
    async checkAuth() {
        return this.get('auth.php?action=check');
    },
    
    // ============ Matchmaking ============
    
    async joinQueue() {
        return this.post('matchmaking.php?action=join');
    },
    
    async leaveQueue() {
        return this.post('matchmaking.php?action=leave');
    },
    
    async getQueueStatus() {
        return this.get('matchmaking.php?action=status');
    },
    
    // ============ Game ============
    
    async getGameState(gameId) {
        return this.get(`game.php?action=state&id=${gameId}`);
    },
    
    async submitMove(gameId, move) {
        return this.post('game.php?action=move', { game_id: gameId, move });
    },
    
    async forfeitGame(gameId) {
        return this.post('game.php?action=forfeit', { game_id: gameId });
    },
    
    // ============ User ============
    
    async getOnlinePlayers(limit = 20) {
        return this.get(`user.php?action=online&limit=${limit}`);
    },
    
    async getUserStats(userId = null) {
        const param = userId ? `&id=${userId}` : '';
        return this.get(`user.php?action=stats${param}`);
    },
    
    async getUserMatches(userId = null, limit = 10) {
        const idParam = userId ? `&id=${userId}` : '';
        return this.get(`user.php?action=matches${idParam}&limit=${limit}`);
    },
    
    async heartbeat() {
        return this.get('user.php?action=heartbeat');
    },
    
    // ============ Leaderboard ============
    
    async getLeaderboard(sort = 'rating', limit = 10) {
        return this.get(`leaderboard.php?sort=${sort}&limit=${limit}`);
    }
};

// Export for use
window.API = API;
