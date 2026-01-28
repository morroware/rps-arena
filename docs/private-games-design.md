# Private Games — Design Document

## Overview

Add the ability for users to create password-protected game rooms. The host sets a room code (password), shares it out-of-band (chat, text, etc.), and only a player who enters the correct code can join.

Private games **do not** go through the matchmaking queue. They use a separate "room" workflow: create room, share code, opponent joins with code, game starts.

---

## Database Changes

### New table: `private_rooms`

```sql
CREATE TABLE IF NOT EXISTS private_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    host_id INT NOT NULL,
    room_code VARCHAR(20) NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    max_rounds INT DEFAULT 3,
    game_id INT DEFAULT NULL,
    status ENUM('waiting', 'started', 'expired') DEFAULT 'waiting',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (host_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE SET NULL,
    INDEX idx_code_hash (code_hash),
    INDEX idx_host (host_id),
    INDEX idx_status (status),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Key fields:**

| Column | Purpose |
|--------|---------|
| `host_id` | The user who created the room |
| `room_code` | Plaintext display code (shown only to creator, never logged after creation) |
| `code_hash` | `password_hash()` of the room code — used for verification |
| `max_rounds` | Host can choose best-of-3, best-of-5, etc. |
| `game_id` | Set when an opponent joins and the game is created |
| `status` | `waiting` (open), `started` (game created), `expired` (timed out or cancelled) |
| `expires_at` | Auto-expire after 10 minutes if no one joins |

### Changes to `games` table

Add one column to distinguish private games from public matchmaking games:

```sql
ALTER TABLE games ADD COLUMN is_private BOOLEAN DEFAULT FALSE;
ALTER TABLE games ADD COLUMN private_room_id INT DEFAULT NULL;
ALTER TABLE games ADD FOREIGN KEY (private_room_id) REFERENCES private_rooms(id) ON DELETE SET NULL;
```

This lets the rest of the game logic (rounds, moves, scoring, ELO) work identically. Private vs. public is just how the game gets *created*.

---

## Schema Migration

Add to `sql/schema.sql` (or a separate migration file):

```sql
-- Private game rooms
CREATE TABLE IF NOT EXISTS private_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    host_id INT NOT NULL,
    room_code VARCHAR(20) NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    max_rounds INT DEFAULT 3,
    game_id INT DEFAULT NULL,
    status ENUM('waiting', 'started', 'expired') DEFAULT 'waiting',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (host_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE SET NULL,
    INDEX idx_code_hash (code_hash),
    INDEX idx_host (host_id),
    INDEX idx_status (status),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add private flag to games
ALTER TABLE games ADD COLUMN is_private BOOLEAN DEFAULT FALSE AFTER winner_id;
ALTER TABLE games ADD COLUMN private_room_id INT DEFAULT NULL AFTER is_private;
```

---

## Configuration

Add to `includes/config.template.php`:

```php
// Private Games
define('PRIVATE_ROOM_TIMEOUT_SECONDS', 600); // 10 minutes to join
define('PRIVATE_ROOM_CODE_LENGTH', 6);       // Length of generated codes
define('PRIVATE_ROOM_MAX_ACTIVE', 1);        // Max active rooms per user
```

---

## Backend: New Functions

### `includes/game_logic.php` — Add these functions

#### `createPrivateRoom($hostId, $maxRounds = DEFAULT_MAX_ROUNDS)`

```php
function createPrivateRoom($hostId, $maxRounds = DEFAULT_MAX_ROUNDS) {
    // Check if host already has a waiting room
    $stmt = db()->prepare("SELECT id FROM private_rooms WHERE host_id = ? AND status = 'waiting'");
    $stmt->execute([$hostId]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'You already have an open room'];
    }

    // Check if host is in an active game
    $stmt = db()->prepare("SELECT id FROM games WHERE (player1_id = ? OR player2_id = ?) AND status IN ('waiting', 'active')");
    $stmt->execute([$hostId, $hostId]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'Already in a game'];
    }

    // Check host is not in matchmaking queue
    $stmt = db()->prepare("SELECT id FROM matchmaking_queue WHERE user_id = ?");
    $stmt->execute([$hostId]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'Leave the matchmaking queue first'];
    }

    // Generate room code (alphanumeric, uppercase, easy to read/share)
    $code = generateRoomCode(PRIVATE_ROOM_CODE_LENGTH);
    $codeHash = password_hash($code, PASSWORD_DEFAULT);
    $expiresAt = date('Y-m-d H:i:s', time() + PRIVATE_ROOM_TIMEOUT_SECONDS);

    $stmt = db()->prepare("
        INSERT INTO private_rooms (host_id, room_code, code_hash, max_rounds, expires_at)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$hostId, $code, $codeHash, $maxRounds, $expiresAt]);

    return [
        'success' => true,
        'room_id' => db()->lastInsertId(),
        'room_code' => $code,
        'expires_at' => $expiresAt
    ];
}
```

#### `joinPrivateRoom($userId, $code)`

```php
function joinPrivateRoom($userId, $code) {
    // Cannot join your own room
    // Find a waiting, non-expired room matching the code
    $stmt = db()->prepare("
        SELECT * FROM private_rooms
        WHERE status = 'waiting' AND expires_at > NOW()
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $rooms = $stmt->fetchAll();

    $matchedRoom = null;
    foreach ($rooms as $room) {
        if (password_verify($code, $room['code_hash'])) {
            $matchedRoom = $room;
            break;
        }
    }

    if (!$matchedRoom) {
        return ['success' => false, 'error' => 'Invalid or expired room code'];
    }

    if ($matchedRoom['host_id'] == $userId) {
        return ['success' => false, 'error' => 'Cannot join your own room'];
    }

    // Check if joiner is in an active game
    $stmt = db()->prepare("SELECT id FROM games WHERE (player1_id = ? OR player2_id = ?) AND status IN ('waiting', 'active')");
    $stmt->execute([$userId, $userId]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'Already in a game'];
    }

    // Create the game (host is player1, joiner is player2)
    db()->beginTransaction();
    try {
        $stmt = db()->prepare("
            INSERT INTO games (player1_id, player2_id, max_rounds, status, is_private, private_room_id)
            VALUES (?, ?, ?, 'active', TRUE, ?)
        ");
        $stmt->execute([$matchedRoom['host_id'], $userId, $matchedRoom['max_rounds'], $matchedRoom['id']]);
        $gameId = db()->lastInsertId();

        // Create first round
        $stmt = db()->prepare("INSERT INTO game_rounds (game_id, round_number) VALUES (?, 1)");
        $stmt->execute([$gameId]);

        // Update room status
        $stmt = db()->prepare("UPDATE private_rooms SET status = 'started', game_id = ? WHERE id = ?");
        $stmt->execute([$gameId, $matchedRoom['id']]);

        // Remove both players from matchmaking queue (safety)
        $stmt = db()->prepare("DELETE FROM matchmaking_queue WHERE user_id IN (?, ?)");
        $stmt->execute([$matchedRoom['host_id'], $userId]);

        db()->commit();

        return [
            'success' => true,
            'game_id' => $gameId,
            'host_name' => null // caller can look this up
        ];
    } catch (Exception $e) {
        db()->rollBack();
        return ['success' => false, 'error' => 'Failed to create game'];
    }
}
```

#### `cancelPrivateRoom($userId)`

```php
function cancelPrivateRoom($userId) {
    $stmt = db()->prepare("UPDATE private_rooms SET status = 'expired' WHERE host_id = ? AND status = 'waiting'");
    $stmt->execute([$userId]);
    return ['success' => true];
}
```

#### `checkPrivateRoomStatus($userId)`

```php
function checkPrivateRoomStatus($userId) {
    $stmt = db()->prepare("
        SELECT pr.*, g.id as game_id, u.username as opponent_name
        FROM private_rooms pr
        LEFT JOIN games g ON pr.game_id = g.id
        LEFT JOIN users u ON (g.player1_id = u.id OR g.player2_id = u.id) AND u.id != ?
        WHERE pr.host_id = ? AND pr.status IN ('waiting', 'started')
        ORDER BY pr.created_at DESC LIMIT 1
    ");
    $stmt->execute([$userId, $userId]);
    $room = $stmt->fetch();

    if (!$room) {
        return ['success' => true, 'has_room' => false];
    }

    // Check expiration
    if ($room['status'] === 'waiting' && strtotime($room['expires_at']) < time()) {
        $stmt = db()->prepare("UPDATE private_rooms SET status = 'expired' WHERE id = ?");
        $stmt->execute([$room['id']]);
        return ['success' => true, 'has_room' => false, 'expired' => true];
    }

    if ($room['status'] === 'started') {
        return [
            'success' => true,
            'has_room' => true,
            'matched' => true,
            'game_id' => $room['game_id'],
            'opponent_name' => $room['opponent_name']
        ];
    }

    return [
        'success' => true,
        'has_room' => true,
        'matched' => false,
        'room_id' => $room['id'],
        'expires_at' => $room['expires_at']
    ];
}
```

#### `generateRoomCode($length)`

```php
function generateRoomCode($length = 6) {
    // Use characters that are easy to read and share (no ambiguous chars like 0/O, 1/l)
    $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}
```

---

## Backend: New API Endpoint

### `api/private.php`

```php
<?php
require_once __DIR__ . '/../includes/init.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
requireAuthApi();
$userId = getCurrentUserId();

switch ($action) {
    case 'create':
        handleCreate($userId);
        break;
    case 'join':
        handleJoin($userId);
        break;
    case 'cancel':
        handleCancel($userId);
        break;
    case 'status':
        handleStatus($userId);
        break;
    default:
        jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
}

function handleCreate($userId) {
    $data = getPostData();
    $maxRounds = (int)($data['max_rounds'] ?? DEFAULT_MAX_ROUNDS);
    // Validate max_rounds is odd and between 1-9
    if ($maxRounds < 1 || $maxRounds > 9 || $maxRounds % 2 === 0) {
        $maxRounds = DEFAULT_MAX_ROUNDS;
    }
    $result = createPrivateRoom($userId, $maxRounds);
    jsonResponse($result, $result['success'] ? 200 : 400);
}

function handleJoin($userId) {
    $data = getPostData();
    $code = strtoupper(trim($data['code'] ?? ''));
    if (empty($code)) {
        jsonResponse(['success' => false, 'error' => 'Room code required'], 400);
    }
    $result = joinPrivateRoom($userId, $code);
    jsonResponse($result, $result['success'] ? 200 : 400);
}

function handleCancel($userId) {
    $result = cancelPrivateRoom($userId);
    jsonResponse($result);
}

function handleStatus($userId) {
    $result = checkPrivateRoomStatus($userId);
    jsonResponse($result);
}
```

---

## Backend: JS API Client Addition

### `assets/js/api.js` — Add to the API object

```js
// ============ Private Games ============

async createPrivateRoom(maxRounds = 3) {
    return this.post('private.php?action=create', { max_rounds: maxRounds });
},

async joinPrivateRoom(code) {
    return this.post('private.php?action=join', { code });
},

async cancelPrivateRoom() {
    return this.post('private.php?action=cancel');
},

async getPrivateRoomStatus() {
    return this.get('private.php?action=status');
},
```

---

## Frontend: Lobby UI Changes

### Lobby layout changes (`lobby.php`)

Add a "Private Game" section below the existing matchmaking card, inside `.matchmaking-section`:

```html
<!-- Private Game Section -->
<div class="private-game-card">
    <h3>Private Game</h3>
    <p>Play with a friend using a room code.</p>

    <div class="private-game-actions" id="private-actions">
        <button id="create-room-btn" class="btn btn-secondary">
            Create Room
        </button>

        <div class="join-room-form">
            <input type="text" id="room-code-input" placeholder="Enter code"
                   maxlength="6" class="form-input"
                   style="text-transform: uppercase; letter-spacing: 4px; text-align: center;">
            <button id="join-room-btn" class="btn btn-primary">
                Join
            </button>
        </div>
    </div>

    <!-- Shown after creating a room -->
    <div id="room-waiting" class="hidden">
        <div class="room-code-display">
            <p>Share this code with your opponent:</p>
            <div class="code-box" id="room-code-value">------</div>
            <button id="copy-code-btn" class="btn btn-small btn-outline">Copy Code</button>
        </div>
        <p class="status-text">Waiting for opponent to join...</p>
        <p class="expire-text">Room expires in <span id="room-timer">10:00</span></p>
        <button id="cancel-room-btn" class="btn btn-small btn-danger">Cancel Room</button>
    </div>
</div>
```

### JavaScript: Lobby private game logic (`assets/js/lobby.js`)

Add alongside the existing queue polling logic:

```js
// ============ Private Games ============

let privateRoomPollInterval = null;

document.getElementById('create-room-btn')?.addEventListener('click', async () => {
    try {
        const result = await API.createPrivateRoom(3);
        if (result.success) {
            document.getElementById('private-actions').classList.add('hidden');
            document.getElementById('room-waiting').classList.remove('hidden');
            document.getElementById('room-code-value').textContent = result.room_code;
            startPrivateRoomPolling();
        }
    } catch (e) {
        alert(e.message || 'Failed to create room');
    }
});

document.getElementById('join-room-btn')?.addEventListener('click', async () => {
    const code = document.getElementById('room-code-input').value.trim();
    if (!code) return;
    try {
        const result = await API.joinPrivateRoom(code);
        if (result.success) {
            window.location.href = 'game.php?id=' + result.game_id;
        }
    } catch (e) {
        alert(e.message || 'Invalid or expired room code');
    }
});

document.getElementById('cancel-room-btn')?.addEventListener('click', async () => {
    await API.cancelPrivateRoom();
    stopPrivateRoomPolling();
    document.getElementById('room-waiting').classList.add('hidden');
    document.getElementById('private-actions').classList.remove('hidden');
});

document.getElementById('copy-code-btn')?.addEventListener('click', () => {
    const code = document.getElementById('room-code-value').textContent;
    navigator.clipboard.writeText(code);
    // Brief visual feedback
    const btn = document.getElementById('copy-code-btn');
    btn.textContent = 'Copied!';
    setTimeout(() => btn.textContent = 'Copy Code', 1500);
});

function startPrivateRoomPolling() {
    privateRoomPollInterval = setInterval(async () => {
        try {
            const result = await API.getPrivateRoomStatus();
            if (result.matched) {
                stopPrivateRoomPolling();
                // Show match found, redirect
                document.getElementById('opponent-name').textContent = result.opponent_name;
                document.getElementById('match-found-modal').classList.remove('hidden');
                setTimeout(() => {
                    window.location.href = 'game.php?id=' + result.game_id;
                }, 2000);
            } else if (!result.has_room || result.expired) {
                stopPrivateRoomPolling();
                document.getElementById('room-waiting').classList.add('hidden');
                document.getElementById('private-actions').classList.remove('hidden');
                if (result.expired) alert('Room expired. No one joined.');
            }
        } catch (e) {
            console.error('Private room poll error:', e);
        }
    }, POLL_QUEUE); // reuse the queue poll interval (2s)
}

function stopPrivateRoomPolling() {
    if (privateRoomPollInterval) {
        clearInterval(privateRoomPollInterval);
        privateRoomPollInterval = null;
    }
}
```

---

## Frontend: CSS Additions

Add to `assets/css/style.css`:

```css
/* Private Game Card */
.private-game-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: var(--radius-lg);
    padding: var(--space-lg);
    margin-top: var(--space-md);
    text-align: center;
}

.private-game-card h3 {
    margin-bottom: var(--space-xs);
}

.join-room-form {
    display: flex;
    gap: var(--space-sm);
    margin-top: var(--space-sm);
    justify-content: center;
}

.join-room-form .form-input {
    width: 140px;
    font-size: 1.1rem;
    font-family: monospace;
}

.code-box {
    font-size: 2rem;
    font-family: monospace;
    letter-spacing: 8px;
    font-weight: 700;
    color: var(--accent);
    background: rgba(0, 217, 255, 0.08);
    border: 2px dashed var(--accent);
    border-radius: var(--radius-md);
    padding: var(--space-md) var(--space-lg);
    margin: var(--space-md) auto;
    display: inline-block;
    user-select: all;
}

.expire-text {
    font-size: 0.85rem;
    color: var(--text-muted);
    margin-top: var(--space-xs);
}
```

---

## Gameplay & ELO Behavior

Private games use the **exact same** game logic as public matchmaking games:

- Same `submitMove()`, `resolveRound()`, `checkGameEnd()`, `endGame()` functions
- Same ELO rating adjustments
- Same forfeit handling
- Same round timer (30 seconds)
- Appear in match history and leaderboard stats

The only difference is how the game gets created (room code instead of queue).

**Design decision**: Private games **do** affect ELO. This prevents farming by only playing weaker friends. If a "casual" no-ELO mode is wanted later, add `is_ranked BOOLEAN DEFAULT TRUE` to the `games` table and skip rating updates in `endGame()` when false.

---

## Cleanup

Add to `cleanupStaleData()` in `includes/functions.php`:

```php
// Expire old private rooms
$stmt = db()->prepare("UPDATE private_rooms SET status = 'expired' WHERE status = 'waiting' AND expires_at < NOW()");
$stmt->execute();
```

---

## Interaction with Existing Systems

| System | Impact |
|--------|--------|
| **Matchmaking queue** | No change. Private game creation blocks queue join (and vice versa). |
| **Active game check** | No change. `getUserActiveGame()` already finds all active games regardless of `is_private`. |
| **Lobby redirect** | No change. If a private game is active, the lobby redirects to it. |
| **Leaderboard** | No change. Private game stats count the same. |
| **Profile / Match history** | No change. Could optionally show a "Private" badge on private matches. |
| **Cleanup** | Add private room expiration to `cleanupStaleData()`. |

---

## Security Considerations

1. **Room codes are hashed** — `password_hash()` / `password_verify()`, same as user passwords. The plaintext code is only returned once on creation.
2. **Cannot join own room** — Explicit check prevents self-play.
3. **Room expiration** — 10-minute timeout prevents orphaned rooms.
4. **One room per user** — Prevents resource exhaustion.
5. **Rate limiting** — The `code_hash` lookup iterates waiting rooms. With `PRIVATE_ROOM_MAX_ACTIVE = 1` per user and 10-minute expiration, the table stays small. For scale, add an index on a truncated hash prefix or use a simpler code scheme (e.g., unique plaintext codes stored directly if the threat model permits).
6. **Auth required** — All endpoints require authentication.

---

## Implementation Order

1. Add `private_rooms` table and `games` columns (schema migration)
2. Add config constants
3. Add backend functions to `game_logic.php`
4. Create `api/private.php`
5. Add API methods to `api.js`
6. Add lobby UI (HTML + CSS)
7. Add lobby JS logic
8. Add cleanup for expired rooms
9. Test: create room, copy code, join from another account, play full game
