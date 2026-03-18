<?php
// =============================================================
//  config.php  —  Database connection settings
//  ⚠️  Change these values to match YOUR MySQL setup
// =============================================================

define('DB_HOST', 'localhost');   // usually localhost
define('DB_USER', 'root');        // your MySQL username
define('DB_PASS', '');            // your MySQL password (often blank on XAMPP)
define('DB_NAME', 'chatapp');     // database name (must match schema.sql)
define('DB_PORT', 3306);          // default MySQL port

/**
 * Returns a connected PDO instance.
 * PDO gives us safe prepared statements (prevents SQL injection).
 */
function get_db(): PDO {
    static $pdo = null;          // reuse same connection within one request

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            DB_HOST, DB_PORT, DB_NAME
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // throw on error
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // arrays by column name
            PDO::ATTR_EMULATE_PREPARES   => false,                    // real prepared stmts
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Return a JSON error so the frontend can show a friendly message
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'ok'    => false,
                'error' => 'Database connection failed. Check config.php settings.'
            ]);
            exit;
        }
    }

    return $pdo;
}
