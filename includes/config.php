<?php
/**
 * Database Configuration for Shebamiles EMS
 */

// Database credentials
// Defaults suit a local XAMPP/MySQL setup. On a server, set the environment
// variables DB_HOST, DB_PORT, DB_USER, DB_PASS and DB_NAME instead of editing this file.
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_NAME', getenv('DB_NAME') ?: 'shebamiles_ems_new');

// BASE_URL: web path of the project root (e.g. '' or '/shebamiles-ems').
// Detected automatically so links work from any subfolder and any install location.
if (!defined('BASE_URL')) {
    $rootFs   = str_replace('\\', '/', (string) realpath(dirname(__DIR__)));
    $scriptFs = isset($_SERVER['SCRIPT_FILENAME']) ? str_replace('\\', '/', (string) realpath(dirname($_SERVER['SCRIPT_FILENAME']))) : $rootFs;
    $urlDir   = isset($_SERVER['SCRIPT_NAME']) ? rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') : '';
    $relDir   = trim(substr($scriptFs, strlen($rootFs)), '/');
    if ($relDir !== '' && substr($urlDir, -strlen('/' . $relDir)) === '/' . $relDir) {
        $urlDir = substr($urlDir, 0, -strlen('/' . $relDir));
    }
    define('BASE_URL', $urlDir);
}

// Build a URL from a project-root-relative path, e.g. appUrl('hr/employees.php')
function appUrl($path) {
    return BASE_URL . '/' . ltrim($path, '/');
}

// Create database connection
class Database {
    private $host = DB_HOST;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $dbname = DB_NAME;
    public $conn;
    private $error;

    // CONSTRUCTOR: Called when new Database() is instantiated
    // Automatically establishes database connection
    public function __construct() {
        $this->connect();
    }

    // CONNECT METHOD: Establish database connection using PDO
    // This is called automatically when Database class is instantiated
    private function connect() {
        // Initialize connection as null
        $this->conn = null;
        
        try {
            // BUILD DSN (Data Source Name) for PDO connection
            // Format: mysql:host=localhost;dbname=database_name;charset=utf8mb4
            // charset=utf8mb4 ensures proper Unicode character support
            $dsn = 'mysql:host=' . $this->host . (DB_PORT !== '' ? ';port=' . DB_PORT : '') . ';dbname=' . $this->dbname . ';charset=utf8mb4';
            
            // SET PDO OPTIONS for security and consistency
            // ATTR_ERRMODE=EXCEPTION: Throw exceptions on errors (don't suppress)
            // ATTR_DEFAULT_FETCH_MODE=ASSOC: Return results as associative arrays
            // ATTR_EMULATE_PREPARES=false: Use native prepared statements (safer)
            $options = array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            );
            
            // CREATE new PDO connection with credentials and options
            // Throws PDOException if connection fails (caught below)
            $this->conn = new PDO($dsn, $this->user, $this->pass, $options);
            
        } catch(PDOException $e) {
            // CATCH database connection errors
            $this->error = $e->getMessage();
            
            // LOG ERROR in development environment only
            // In production, don't expose database errors to maintain security
            if (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] === 'localhost') {
                error_log("Database Connection Error: " . $this->error);
            }
        }
        
        // RETURN the connection object (or null if failed)
        return $this->conn;
    }

    public function getConnection() {
        return $this->conn;
    }
}
?>
