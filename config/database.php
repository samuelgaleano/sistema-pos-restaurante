<?php
// ============================================================
// CONFIGURACIÓN DE BASE DE DATOS
// Edite estos valores con los datos de su hosting cPanel
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'pos_db');       // Nombre de su base de datos en cPanel
define('DB_USER', 'root');          // Usuario de MySQL en cPanel
define('DB_PASS', '');              // Contraseña de MySQL en cPanel
define('DB_CHARSET', 'utf8mb4');

// URL base del sistema (sin barra al final)
define('BASE_URL', 'http://localhost/pos');
define('BASE_PATH', dirname(__DIR__));

// Zona horaria
date_default_timezone_set('America/Bogota');

// Versión del sistema
define('POS_VERSION', '1.0.0');
define('POS_NAME', 'POS Pro');

// ============================================================
// CONEXIÓN PDO
// ============================================================
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die(json_encode(['error' => true, 'msg' => 'Error de conexión: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}
