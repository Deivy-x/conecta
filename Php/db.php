<?php
defined('DB_HOST') or define('DB_HOST', getenv('MYSQLHOST') ?: 'caboose.proxy.rlwy.net');
defined('DB_NAME') or define('DB_NAME', getenv('MYSQLDATABASE') ?: 'railway');
defined('DB_USER') or define('DB_USER', getenv('MYSQLUSER') ?: 'root');
defined('DB_PASS') or define('DB_PASS', getenv('MYSQLPASSWORD') ?: '');
defined('DB_PORT') or define('DB_PORT', getenv('MYSQLPORT') ?: '20815');
defined('DB_CHARSET') or define('DB_CHARSET', 'utf8mb4');
defined('BASE_URL') or define('BASE_URL', 'https://conecta-production-818e.up.railway.app');

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['ok' => false, 'msg' => $e->getMessage()]));
        }
    }
    return $pdo;
}
?>