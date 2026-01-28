# 🪨📄✂️ RPS Arena

A multiplayer Rock Paper Scissors game with lobby, matchmaking, and leaderboards.  
Built with vanilla PHP, JavaScript, HTML, and CSS — perfect for cPanel shared hosting.

---

## ✨ Features

- **Real-Time Multiplayer** — Battle other players in live matches
- **Matchmaking Queue** — Auto-match with available opponents
- **ELO Rating System** — Climb the ranks and prove your skills
- **Leaderboards** — Global rankings by rating, wins, or win rate
- **Player Profiles** — Track your stats and match history
- **Responsive Design** — Works on desktop and mobile
- **Easy Installation** — One-click installer for cPanel hosting

---

## 📋 Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- PDO MySQL extension (usually enabled by default)

---

## 🚀 Installation

### Option 1: cPanel File Manager

1. **Upload Files**
   - Log into your cPanel
   - Open File Manager
   - Navigate to `public_html` (or your desired folder)
   - Upload and extract all files from the zip

2. **Create Database**
   - Go to cPanel → MySQL Databases
   - Create a new database (e.g., `rps_arena`)
   - Create a new user with a strong password
   - Add the user to the database with **All Privileges**

3. **Run Installer**
   - Visit `https://yourdomain.com/install.php` in your browser
   - Enter your database credentials
   - Click "Install RPS Arena"

4. **Delete Installer** ⚠️
   - After successful installation, delete `install.php` for security

5. **Play!**
   - Visit your site and create an account
   - Start battling!

### Option 2: FTP Upload

1. Connect to your server via FTP (FileZilla, etc.)
2. Upload all files to your web directory
3. Follow steps 2-5 above

---

## 📁 File Structure

```
rps-game/
├── api/                    # API endpoints
│   ├── auth.php           # Login/register/logout
│   ├── game.php           # Game actions (move, forfeit)
│   ├── leaderboard.php    # Leaderboard data
│   ├── matchmaking.php    # Queue join/leave/status
│   └── user.php           # User data, online players
├── assets/
│   ├── css/
│   │   └── style.css      # All styles
│   ├── js/
│   │   ├── api.js         # API wrapper
│   │   ├── app.js         # Global utilities
│   │   ├── game.js        # Game page logic
│   │   └── lobby.js       # Lobby page logic
│   └── images/            # Game images (optional)
├── includes/
│   ├── auth.php           # Authentication functions
│   ├── config.php         # Generated config (after install)
│   ├── config.template.php # Config template
│   ├── db.php             # Database connection
│   ├── functions.php      # Helper functions
│   ├── game_logic.php     # Core game mechanics
│   └── init.php           # Bootstrap file
├── sql/
│   └── schema.sql         # Database schema
├── game.php               # Game room page
├── index.php              # Login page
├── install.php            # One-click installer
├── leaderboard.php        # Leaderboard page
├── lobby.php              # Main lobby
├── profile.php            # User profiles
├── register.php           # Registration page
├── .htaccess              # Security rules
└── README.md              # This file
```

---

## 🎮 How to Play

1. **Create an Account** — Register with username, email, and password
2. **Join the Lobby** — See online players and your stats
3. **Find a Match** — Click "Find Match" to enter the queue
4. **Battle!** — Choose Rock, Paper, or Scissors each round
5. **Win** — First to 2 wins takes the match (best of 3)
6. **Climb** — Win matches to increase your rating

### Game Rules

- 🪨 Rock beats ✂️ Scissors
- 📄 Paper beats 🪨 Rock  
- ✂️ Scissors beats 📄 Paper

---

## ⚙️ Configuration

After installation, you can modify settings in `includes/config.php`:

```php
// Game Settings
define('DEFAULT_MAX_ROUNDS', 3);        // Best of 3, 5, etc.
define('MOVE_TIMEOUT_SECONDS', 30);     // Time to make a move
define('QUEUE_TIMEOUT_SECONDS', 300);   // Max time in queue

// Rating System
define('RATING_K_FACTOR', 32);          // ELO volatility
define('RATING_START', 1000);           // Starting rating

// Polling Intervals (milliseconds)
define('POLL_LOBBY', 5000);             // Lobby refresh rate
define('POLL_QUEUE', 2000);             // Queue check rate
define('POLL_GAME', 1500);              // In-game refresh rate
```

---

## 🔒 Security Features

- **Password Hashing** — bcrypt via `password_hash()`
- **Prepared Statements** — All SQL uses PDO prepared statements
- **Session Security** — Secure cookies, session regeneration
- **Input Validation** — All user input is sanitized
- **CSRF Protection** — Token-based form protection

---

## 🐛 Troubleshooting

### "Database connection failed"
- Verify your database credentials in the installer
- Ensure the MySQL user has privileges on the database
- Check that the database exists

### "Page not found" errors
- Make sure `.htaccess` is uploaded (it may be hidden)
- Verify `mod_rewrite` is enabled (usually is on cPanel)

### Queue not finding matches
- Need at least 2 players searching simultaneously
- Check that both players' browsers have JavaScript enabled
- Verify AJAX polling is working (check browser console)

### Styles not loading
- Clear your browser cache
- Check that `assets/css/style.css` was uploaded

---

## 📄 License

MIT License — feel free to use, modify, and distribute.

---

## 🙏 Credits

Built with ❤️ using vanilla PHP, JavaScript, HTML, and CSS.

Enjoy the game! 🎮
