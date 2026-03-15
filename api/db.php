<?php
/**
 * db.php — Secure MySQL Connection via Environment Variables
 * ─────────────────────────────────────────────────────────────
 * Loads credentials from .env (above web root) using phpdotenv.
 * No passwords are hardcoded anywhere in this file.
 *
 * INSTALL PHPDOTENV (run once in cPanel Terminal):
 *   cd ~/public_html && composer require vlucas/phpdotenv
 *
 * .ENV FILE LOCATION:
 *   /home/yourusername/.env   ← one level above public_html
 *
 * The __DIR__ chain below resolves to that location:
 *   __DIR__         = /home/yourusername/public_html/api
 *   /../..          = /home/yourusername
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;

// Point phpdotenv at the directory containing .env (above public_html)
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// Validate that required variables are present — fail fast if .env is misconfigured
$dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'])->notEmpty();


/**
 * get_db()
 * Returns a singleton MySQLi connection for the current request.
 * Credentials are read from $_ENV, which phpdotenv populates from .env.
 *
 * @return mysqli
 */
function get_db(): mysqli {
    static $conn = null;

    if ($conn === null) {
        $conn = new mysqli(
            $_ENV['DB_HOST'],
            $_ENV['DB_USER'],
            $_ENV['DB_PASS'],
            $_ENV['DB_NAME']
        );

        if ($conn->connect_error) {
            // Log the real error server-side, return a generic message to the client
            error_log('MySQL connection failed: ' . $conn->connect_error);
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'A server error occurred. Please try again later.'
            ]);
            exit;
        }

        $conn->set_charset('utf8mb4');
    }

    return $conn;
}