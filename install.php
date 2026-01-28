<?php
/**
 * RPS Arena - One-Click Installer
 * 
 * Upload all files to your cPanel hosting, create a MySQL database,
 * then visit this file in your browser to complete the installation.
 */

// Prevent running if already installed
if (file_exists(__DIR__ . '/includes/config.php')) {
    die('<h1>Already Installed</h1><p>The game is already installed. Delete includes/config.php to reinstall.</p><p><a href="index.php">Go to Game</a></p>');
}

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = $_POST['db_pass'] ?? '';
    $siteUrl = trim($_POST['site_url'] ?? '');
    
    // Validate
    if (empty($dbName)) $errors[] = 'Database name is required';
    if (empty($dbUser)) $errors[] = 'Database user is required';
    if (empty($siteUrl)) $errors[] = 'Site URL is required';
    
    // Test database connection
    if (empty($errors)) {
        try {
            $dsn = "mysql:host=$dbHost;charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            // Create database if not exists
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbName`");
            
            // Run schema - execute statements individually for compatibility
            $schema = file_get_contents(__DIR__ . '/sql/schema.sql');
            $statements = array_filter(array_map('trim', explode(';', $schema)));
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    $pdo->exec($statement);
                }
            }
            
            // Create config file
            $configTemplate = file_get_contents(__DIR__ . '/includes/config.template.php');
            $config = str_replace(
                ['{{DB_HOST}}', '{{DB_NAME}}', '{{DB_USER}}', '{{DB_PASS}}', '{{SITE_URL}}'],
                [$dbHost, $dbName, $dbUser, $dbPass, rtrim($siteUrl, '/')],
                $configTemplate
            );
            
            if (file_put_contents(__DIR__ . '/includes/config.php', $config)) {
                $success = true;
            } else {
                $errors[] = 'Could not write config file. Check directory permissions.';
            }
            
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Auto-detect site URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$path = dirname($_SERVER['SCRIPT_NAME']);
$defaultUrl = $protocol . '://' . $host . $path;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install RPS Arena</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .installer {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            font-size: 2.5rem;
            color: #1a1a2e;
            margin-bottom: 10px;
        }
        
        .logo .icons {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .logo p {
            color: #666;
            font-size: 0.95rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        input:focus {
            outline: none;
            border-color: #0f3460;
        }
        
        .hint {
            font-size: 0.85rem;
            color: #888;
            margin-top: 5px;
        }
        
        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #e94560 0%, #ff6b6b 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(233, 69, 96, 0.3);
        }
        
        .errors {
            background: #fee;
            border: 1px solid #fcc;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .errors p {
            color: #c00;
            margin: 5px 0;
        }
        
        .success {
            text-align: center;
        }
        
        .success .check {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        
        .success h2 {
            color: #2a9d8f;
            margin-bottom: 15px;
        }
        
        .success p {
            color: #666;
            margin-bottom: 20px;
        }
        
        .success a {
            display: inline-block;
            padding: 14px 30px;
            background: linear-gradient(135deg, #2a9d8f 0%, #57cc99 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        
        .success a:hover {
            transform: translateY(-2px);
        }
        
        .warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            font-size: 0.9rem;
            color: #856404;
        }
        
        .requirements {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 25px;
        }
        
        .requirements h3 {
            font-size: 0.95rem;
            margin-bottom: 10px;
            color: #333;
        }
        
        .requirements ul {
            list-style: none;
            font-size: 0.9rem;
        }
        
        .requirements li {
            padding: 5px 0;
            color: #666;
        }
        
        .requirements li::before {
            content: '✓';
            color: #2a9d8f;
            margin-right: 8px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="installer">
        <div class="logo">
            <div class="icons">🪨 📄 ✂️</div>
            <h1>RPS Arena</h1>
            <p>Multiplayer Rock Paper Scissors</p>
        </div>
        
        <?php if ($success): ?>
            <div class="success">
                <div class="check">✅</div>
                <h2>Installation Complete!</h2>
                <p>Your game is ready to play. Create an account and start battling!</p>
                <a href="index.php">Launch Game</a>
                <div class="warning">
                    <strong>Security:</strong> Delete this install.php file now to prevent unauthorized reinstallation.
                </div>
            </div>
        <?php else: ?>
            <div class="requirements">
                <h3>Requirements</h3>
                <ul>
                    <li>PHP 7.4 or higher</li>
                    <li>MySQL 5.7 or higher</li>
                    <li>PDO MySQL extension</li>
                </ul>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="errors">
                    <?php foreach ($errors as $error): ?>
                        <p>❌ <?= htmlspecialchars($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="db_host">Database Host</label>
                    <input type="text" id="db_host" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required>
                    <p class="hint">Usually "localhost" for cPanel</p>
                </div>
                
                <div class="form-group">
                    <label for="db_name">Database Name</label>
                    <input type="text" id="db_name" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" required>
                    <p class="hint">Create this in cPanel → MySQL Databases</p>
                </div>
                
                <div class="form-group">
                    <label for="db_user">Database User</label>
                    <input type="text" id="db_user" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required>
                    <p class="hint">Must have full privileges on the database</p>
                </div>
                
                <div class="form-group">
                    <label for="db_pass">Database Password</label>
                    <input type="password" id="db_pass" name="db_pass">
                </div>
                
                <div class="form-group">
                    <label for="site_url">Site URL</label>
                    <input type="text" id="site_url" name="site_url" value="<?= htmlspecialchars($_POST['site_url'] ?? $defaultUrl) ?>" required>
                    <p class="hint">Full URL to the game folder (no trailing slash)</p>
                </div>
                
                <button type="submit">Install RPS Arena</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
