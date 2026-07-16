<?php
// ============================================================
// CONFIGURACIÓN DE BASE DE DATOS
// Edite estos valores con los datos de su hosting cPanel.
// Si existen como variables de entorno (p.ej. corriendo en Docker / docker-compose)
// tienen prioridad sobre los valores por defecto de abajo, que se usan tal cual
// para desarrollo local sin Docker (XAMPP, servidor embebido de PHP, etc.).
// ============================================================

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'pos_db');       // Nombre de su base de datos en cPanel
define('DB_USER', getenv('DB_USER') ?: 'root');          // Usuario de MySQL en cPanel
define('DB_PASS', getenv('DB_PASS') ?: '');              // Contraseña de MySQL en cPanel
define('DB_CHARSET', 'utf8mb4');

// URL base del sistema (sin barra al final).
// Antes era un dominio fijo ('http://localhost/pos'), lo cual rompía cualquier despliegue
// que no fuera exactamente ese host. Ahora es solo el PATH-PREFIX bajo el que se sirve la
// app, tomado de POS_BASE_PATH:
//   - Sin la variable definida (uso local normal, fuera de Docker): BASE_URL = '' y todos los
//     enlaces/redirects quedan relativos a la raíz del sitio ("/modules/...", "/assets/...").
//   - En el contenedor Docker de la demo: POS_BASE_PATH=/pos-demo, para que los enlaces y
//     redirects generados por la app coincidan con la ruta pública cuando el contenedor se
//     expone same-origin (proxy inverso) bajo /pos-demo desde el portafolio. El docroot de
//     Apache dentro del contenedor sigue siendo la raíz ("/"): este prefijo es puramente para
//     que las URLs que genera PHP (href, Location:) apunten donde el proxy externo espera.
$posBasePath = getenv('POS_BASE_PATH');
define('BASE_URL', $posBasePath !== false ? $posBasePath : '');
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
