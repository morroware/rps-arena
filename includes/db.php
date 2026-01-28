<?php
/**
 * Database Connection Class
 * Singleton pattern for PDO connection
 * Optimized for cPanel shared hosting environments
 */

if (!defined('RPS_GAME')) {
    die('Direct access not permitted');
}

class Database {
    private static $instance = null;
    private $pdo;
    private $retryAttempts = 3;
    private $retryDelay = 100; // milliseconds

    private function __construct() {
        $this->connect();
    }

    private function connect() {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";

        $options = [
            // Error handling
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,

            // Connection settings optimized for shared hosting
            PDO::ATTR_TIMEOUT => 10, // 10 second connection timeout
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",

            // Persistent connections can help on shared hosting
            // But may cause issues if too many connections are held
            // Uncomment if your host supports it and you experience connection issues:
            // PDO::ATTR_PERSISTENT => true,
        ];

        $lastException = null;

        // Retry logic for transient connection failures (common on shared hosting)
        for ($attempt = 1; $attempt <= $this->retryAttempts; $attempt++) {
            try {
                $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

                // Set session variables for better performance
                $this->pdo->exec("SET SESSION wait_timeout = 300");
                $this->pdo->exec("SET SESSION interactive_timeout = 300");

                return; // Success
            } catch (PDOException $e) {
                $lastException = $e;

                // Only retry on connection errors, not auth errors
                if ($attempt < $this->retryAttempts && $this->isRetryableError($e)) {
                    usleep($this->retryDelay * 1000 * $attempt); // Exponential backoff
                    continue;
                }
                break;
            }
        }

        // All retries failed
        $this->handleConnectionError($lastException);
    }

    private function isRetryableError(PDOException $e) {
        $retryableCodes = [
            2002, // Can't connect to server
            2003, // Can't connect to MySQL server
            2006, // MySQL server has gone away
            2013, // Lost connection during query
            1040, // Too many connections
            1205, // Lock wait timeout
        ];

        return in_array($e->getCode(), $retryableCodes);
    }

    private function handleConnectionError(PDOException $e) {
        // Log error (if error logging is available)
        error_log("RPS Arena DB Error: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");

        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            die("Database connection failed: " . $e->getMessage());
        } else {
            // User-friendly error for production
            http_response_code(503);
            die(json_encode([
                'success' => false,
                'error' => 'Service temporarily unavailable. Please try again in a moment.',
                'retry' => true
            ]));
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        // Check if connection is still alive
        if ($this->pdo === null) {
            $this->connect();
        }

        try {
            // Ping the connection
            $this->pdo->query('SELECT 1');
        } catch (PDOException $e) {
            // Connection lost, try to reconnect
            $this->connect();
        }

        return $this->pdo;
    }

    /**
     * Execute query with automatic retry on deadlock/timeout
     */
    public function executeWithRetry($callback, $maxRetries = 3) {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            try {
                return $callback($this->pdo);
            } catch (PDOException $e) {
                $lastException = $e;

                // Retry on deadlock or lock timeout
                if (in_array($e->getCode(), [1205, 1213, 40001])) {
                    $attempt++;
                    usleep(50000 * $attempt); // 50ms, 100ms, 150ms
                    continue;
                }

                throw $e;
            }
        }

        throw $lastException;
    }

    // Prevent cloning
    private function __clone() {}

    // Prevent unserialization
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Helper function to get database connection
 */
function db() {
    return Database::getInstance()->getConnection();
}

/**
 * Helper for retryable transactions
 */
function dbTransaction($callback) {
    return Database::getInstance()->executeWithRetry(function($pdo) use ($callback) {
        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    });
}
