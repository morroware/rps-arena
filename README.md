# RPS Arena

A fully functional multiplayer Rock Paper Scissors game with real-time matchmaking, ELO ratings, and leaderboards. Built with vanilla PHP, JavaScript, HTML, and CSS — designed for cPanel shared hosting with zero external dependencies.

---

## Features

- **Real-Time Multiplayer** — Battle other players in live best-of-3 matches
- **Matchmaking Queue** — FIFO auto-matching with race-condition-safe locking
- **ELO Rating System** — Dynamic ratings with K-factor of 32, applied on wins, losses, draws, and forfeits
- **Rank Tiers** — Bronze through Legend with animated badges
- **Leaderboards** — Global rankings sortable by rating, wins, or win rate
- **Player Profiles** — Stats, win streaks, match history, and performance visualizations
- **30-Second Move Timer** — Auto-selects a random move on timeout
- **Forfeit System** — Forfeit counts as a loss with full ELO adjustment
- **Responsive Design** — Works on desktop and mobile
- **One-Click Installer** — Browser-based setup for cPanel hosting
- **Stale Game Cleanup** — Automatic cleanup of abandoned games and expired queue entries

---

## Requirements

- PHP 7.4+
- MySQL 5.7+
- PDO MySQL extension (enabled by default)

---

## Installation

### 1. Upload Files

Upload all project files to your web directory (e.g., `public_html/rps/` on cPanel).

### 2. Create Database

In cPanel, go to **MySQL Databases**:
- Create a new database (e.g., `rps_arena`)
- Create a new user with a strong password
- Add the user to the database with **All Privileges**

### 3. Run Installer

Visit `https://yourdomain.com/rps/install.php` in your browser and enter your database credentials.

### 4. Delete Installer

After successful installation, **delete `install.php`** for security.

### 5. Play

Visit your site, create an account, and start battling.

---

## Project Structure

```
rps-arena/
├── api/                        # REST API endpoints
│   ├── auth.php               # Login / register / logout
│   ├── game.php               # Game state, move submission, forfeit
│   ├── leaderboard.php        # Leaderboard data
│   ├── matchmaking.php        # Queue join / leave / status
│   └── user.php               # User stats, online players, heartbeat
├── assets/
│   ├── css/
│   │   └── style.css          # Complete stylesheet (dark theme, animations)
│   └── js/
│       ├── api.js             # Fetch-based API client wrapper
│       ├── app.js             # Global utilities (modals, forms)
│       ├── game.js            # Game page logic (moves, timer, effects)
│       └── lobby.js           # Lobby page logic (queue, polling, players)
├── includes/
│   ├── auth.php               # Authentication (sessions, bcrypt, CSRF, remember-me)
│   ├── config.php             # Generated config (after install)
│   ├── config.template.php    # Config template with defaults
│   ├── db.php                 # PDO singleton with retry/reconnect logic
│   ├── functions.php          # Helpers (leaderboard, stats, ranks, cleanup)
│   ├── game_logic.php         # Core mechanics (matchmaking, rounds, ELO)
│   └── init.php               # Bootstrap (loads config, starts session)
├── sql/
│   └── schema.sql             # Database schema (6 tables + 1 view)
├── cron/
│   └── cleanup.php            # Optional cron-based cleanup
├── game.php                   # Game room page
├── index.php                  # Login page (landing)
├── install.php                # One-click browser installer
├── leaderboard.php            # Leaderboard page
├── lobby.php                  # Main lobby with matchmaking
├── profile.php                # User profile and match history
├── register.php               # Registration page
└── README.md
```

---

## How It Works

### Gameplay Flow

1. **Register/Login** — Create an account or sign in
2. **Lobby** — View online players, stats, and top players
3. **Enter Queue** — Click "Enter the Arena" to join matchmaking
4. **Match Found** — Automatically paired with another queued player
5. **Play Rounds** — Choose Rock, Paper, or Scissors each round (30s timer)
6. **Win the Match** — First to 2 round wins takes the best-of-3
7. **Rating Updated** — ELO ratings adjusted based on outcome

### Matchmaking

- Players join a FIFO queue
- Server polls every 2 seconds for a match
- Row-level locking prevents duplicate game creation
- 5-minute queue timeout with automatic removal
- Stale queue entries cleaned up on lobby load

### Rating System (ELO)

- **Starting Rating**: 1000
- **K-Factor**: 32
- **Win/Loss**: Standard ELO formula
- **Draw**: Both players move 10% toward the average
- **Forfeit**: Full ELO loss/gain applied
- **Minimum Rating**: 100

### Rank Tiers

| Rating | Rank        | Badge |
|--------|-------------|-------|
| 2000+  | Legend      | Rainbow animated |
| 1800+  | Grandmaster | Red glow |
| 1600+  | Master      | Purple |
| 1400+  | Diamond     | Cyan |
| 1200+  | Platinum    | Silver |
| 1000+  | Gold        | Gold |
| 800+   | Silver      | Gray |
| 0+     | Bronze      | Bronze |

---

## Configuration

After installation, edit `includes/config.php`:

```php
// Game Settings
define('DEFAULT_MAX_ROUNDS', 3);        // Best of 3 (or 5, 7, etc.)
define('MOVE_TIMEOUT_SECONDS', 30);     // Seconds to make a move
define('QUEUE_TIMEOUT_SECONDS', 300);   // Max time in queue (5 min)

// Rating System
define('RATING_K_FACTOR', 32);          // ELO volatility
define('RATING_START', 1000);           // Starting rating

// Polling Intervals (milliseconds)
define('POLL_LOBBY', 5000);             // Lobby refresh rate
define('POLL_QUEUE', 2000);             // Queue check rate
define('POLL_GAME', 1500);              // In-game refresh rate

// Debug
define('DEBUG_MODE', false);            // Set true for error details
```

---

## API Endpoints

All endpoints return JSON. Authentication required unless noted.

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `api/auth.php?action=login` | Login |
| POST | `api/auth.php?action=register` | Register |
| GET/POST | `api/auth.php?action=logout` | Logout |
| GET | `api/auth.php?action=check` | Check auth status |
| POST | `api/matchmaking.php?action=join` | Join queue |
| POST | `api/matchmaking.php?action=leave` | Leave queue |
| GET | `api/matchmaking.php?action=status` | Queue status / match check |
| GET | `api/game.php?action=state&id=N` | Get game state |
| POST | `api/game.php?action=move` | Submit move |
| POST | `api/game.php?action=forfeit` | Forfeit match |
| GET | `api/user.php?action=online` | Online players |
| GET | `api/user.php?action=stats` | User statistics |
| GET | `api/user.php?action=matches` | Match history |
| GET | `api/user.php?action=heartbeat` | Update online status |
| GET | `api/leaderboard.php?sort=rating` | Leaderboard data |

---

## Security

- **Passwords**: bcrypt via `password_hash()`
- **SQL Injection**: All queries use PDO prepared statements
- **Sessions**: Secure cookies, httponly, samesite=Lax, regeneration on login
- **CSRF**: Token-based protection on state-changing operations
- **Input Validation**: Server-side validation on all user input
- **XSS Prevention**: HTML escaping via `htmlspecialchars()` on all output
- **Authorization**: Game operations verify user participation

---

## Troubleshooting

**Database connection failed**
- Verify credentials in the installer
- Ensure MySQL user has privileges on the database

**Queue not finding matches**
- Need at least 2 players searching simultaneously
- Check browser console for JavaScript errors
- Verify AJAX polling is working

**Stuck in a game**
- Return to lobby — stale games are automatically cleaned up
- Use the forfeit button to exit an active game

**Styles not loading**
- Clear browser cache
- Verify `assets/css/style.css` is accessible

---

## License

MIT License — free to use, modify, and distribute.
