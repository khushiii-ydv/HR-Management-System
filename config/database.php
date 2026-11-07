
<?php
/**
 * Database Configuration & Connection
 * HR Management System
 */

// ----------------------------------------------------------------
// Composer Autoloader
// ----------------------------------------------------------------
$vendorAutoload = dirname(__DIR__) . DIRECTORY_SEPARATOR
    . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (!file_exists($vendorAutoload)) {
    throw new RuntimeException('Composer autoload file not found.');
}

require_once $vendorAutoload;

// ----------------------------------------------------------------
// Load Private Environment File
// ----------------------------------------------------------------
$privateEnvDir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'private';

$dotenv = Dotenv\Dotenv::createUnsafeImmutable(
    $privateEnvDir,
    'hrms.env'
);

$dotenv->safeLoad();

// ----------------------------------------------------------------
// Application Environment Initialization
// ----------------------------------------------------------------
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Kolkata');

$appEnv = strtolower(getenv('APP_ENV') ?: 'development');

$appDebug = filter_var(
    getenv('APP_DEBUG') !== false
        ? getenv('APP_DEBUG')
        : ($appEnv === 'development'),
    FILTER_VALIDATE_BOOLEAN
);

if (!defined('APP_ENV')) define('APP_ENV', $appEnv);
if (!defined('APP_DEBUG')) define('APP_DEBUG', $appDebug);

if ($appDebug) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');

    $logDir = dirname(__DIR__) . '/logs';

    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    ini_set('error_log', $logDir . '/app.log');
}

// ----------------------------------------------------------------
// Secure Session Cookie Defaults
// ----------------------------------------------------------------
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {

    $isHttps = (
        !empty($_SERVER['HTTPS']) &&
        $_SERVER['HTTPS'] !== 'off'
    ) || (
        isset($_SERVER['SERVER_PORT']) &&
        (int)$_SERVER['SERVER_PORT'] === 443
    );

    $sessionLifetime = (int)(getenv('SESSION_LIFETIME') ?: 1800);

    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

// ----------------------------------------------------------------
// Application Constants
// ----------------------------------------------------------------
if (!defined('APP_NAME')) {
    define('APP_NAME', getenv('APP_NAME') ?: 'HRMS');
    define('COMPANY_NAME', 'HRMS Solutions');
}

// ----------------------------------------------------------------
// Base URL
// ----------------------------------------------------------------
if (!defined('BASE_URL')) {

    $envUrl = getenv('APP_URL');

    if ($envUrl) {
        $parsedPath = parse_url($envUrl, PHP_URL_PATH);
        define('BASE_URL', rtrim($parsedPath ?: '/', '/') . '/');
    } else {
        define('BASE_URL', '/hr-management-system/');
    }
}

// ----------------------------------------------------------------
// Database Credentials
// ----------------------------------------------------------------
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_DATABASE') ?: 'hr_management_system');
define('DB_USER', getenv('DB_USERNAME') ?: 'root');

define(
    'DB_PASS',
    getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : ''
);

define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_CHARSET', 'utf8mb4');

// ----------------------------------------------------------------
// Get PDO Database Connection
// ----------------------------------------------------------------
function getDBConnection()
{
    static $pdo = null;

    if ($pdo === null) {

        $dsn = "mysql:host=" . DB_HOST .
            ";port=" . DB_PORT .
            ";dbname=" . DB_NAME .
            ";charset=" . DB_CHARSET;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
        ];

        try {

            $pdo = new PDO(
                $dsn,
                DB_USER,
                DB_PASS,
                $options
            );

        } catch (PDOException $e) {

            $logDir = dirname(__DIR__) . '/logs';

            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }

            @file_put_contents(
                $logDir . '/app.log',
                "[" . date('Y-m-d H:i:s') .
                "] [ERROR] Database connection failed.\n",
                FILE_APPEND
            );

            if (defined('APP_DEBUG') && APP_DEBUG) {

                die(
                    '<div style="font-family:Segoe UI,sans-serif;
                    margin:40px auto;max-width:600px;padding:25px;
                    border-radius:12px;background:#FFF4E5;
                    border:1px solid #EA8A19;color:#172B4D;">

                    <h3 style="color:#EA8A19;">
                    Database Connection Error
                    </h3>

                    <p>Could not connect to the MySQL database
                    <strong>' . htmlspecialchars(DB_NAME) . '</strong>.</p>

                    <p>Error Details: ' .
                    htmlspecialchars($e->getMessage()) . '</p>

                    <p>Please make sure MySQL is running in XAMPP
                    and credentials are correct.</p>

                    </div>'
                );

            } else {

                http_response_code(500);

                die(
                    '<div style="font-family:Segoe UI,sans-serif;
                    margin:40px auto;max-width:500px;padding:25px;
                    border:1px solid #E5EAF1;text-align:center;">

                    <h3>Service Temporarily Unavailable</h3>

                    <p>We are experiencing technical difficulties.
                    Please try again later.</p>

                    </div>'
                );
            }
        }
    }

    return $pdo;
}
