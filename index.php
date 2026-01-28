<?php
/**
 * RPS Arena - Landing Page / Login
 */
require_once __DIR__ . '/includes/init.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('lobby.php');
}

$error = '';
$redirect = $_GET['redirect'] ?? '';

// Handle login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    $result = loginUser($username, $password, $remember);
    
    if ($result['success']) {
        redirect($redirect ?: 'lobby.php');
    } else {
        $error = $result['error'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(SITE_NAME) ?> - Multiplayer Rock Paper Scissors</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="game-icons">
                    <span class="icon rock">🪨</span>
                    <span class="icon paper">📄</span>
                    <span class="icon scissors">✂️</span>
                </div>
                <h1><?= e(SITE_NAME) ?></h1>
                <p>Battle players worldwide in the ultimate RPS showdown!</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>
            
            <form method="POST" class="auth-form">
                <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
                
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input type="text" id="username" name="username" required autofocus 
                           value="<?= e($_POST['username'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group checkbox">
                    <label>
                        <input type="checkbox" name="remember" value="1">
                        <span>Remember me for 30 days</span>
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Sign In</button>
            </form>
            
            <div class="auth-footer">
                <p>Don't have an account? <a href="register.php">Create one</a></p>
            </div>
        </div>
        
        <div class="features-preview">
            <div class="feature">
                <span class="feature-icon">⚔️</span>
                <h3>Real-Time Battles</h3>
                <p>Challenge players in live matches</p>
            </div>
            <div class="feature">
                <span class="feature-icon">🏆</span>
                <h3>Climb the Ranks</h3>
                <p>Compete for the top leaderboard spot</p>
            </div>
            <div class="feature">
                <span class="feature-icon">📊</span>
                <h3>Track Stats</h3>
                <p>View your wins, losses, and rating</p>
            </div>
        </div>
    </div>
    
    <script src="assets/js/app.js"></script>
</body>
</html>
