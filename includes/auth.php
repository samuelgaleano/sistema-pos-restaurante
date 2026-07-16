<?php
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    // ------------------------------------------------------------------
    // Cookie de sesión compatible con embebido en <iframe> (demo same-origin
    // vía proxy inverso, p.ej. https://portafolio.com/pos-demo/ -> este contenedor).
    //
    // Los navegadores modernos exigen SameSite=None para que una cookie viaje dentro
    // de un iframe, y la spec obliga a que SameSite=None vaya siempre acompañado de
    // Secure (si no, el navegador descarta la cookie directamente). Por eso se fija
    // ANTES de session_start(), sin condicionarlo por entorno:
    //   - En producción, el iframe se sirve por HTTPS (detrás del proxy del portafolio),
    //     así que Secure se cumple de forma natural.
    //   - En la demo local con `docker compose up` (http://localhost:8080, sin TLS),
    //     Secure también funciona: Chrome/Edge/Firefox tratan "localhost" y "127.0.0.1"
    //     como "contextos seguros" y sí guardan/envían cookies Secure ahí aunque no haya
    //     HTTPS real. Por eso NO hace falta condicionar esto por HTTP/HTTPS.
    //   - Limitación aceptada: si algún día se sirve este POS por HTTP plano en un host
    //     distinto de localhost (sin TLS ni proxy), la cookie de sesión no se guardará.
    //     No aplica a los escenarios de esta demo (Docker local o iframe en producción).
    session_set_cookie_params([
        'samesite' => 'None',
        'secure'   => true,
        'httponly' => true,
    ]);
    session_start();
}

// ============================================================
// FUNCIONES DE AUTENTICACIÓN
// ============================================================

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    return $_SESSION['user'] ?? null;
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

function hasRole($role) {
    return strtolower(getUserRole()) === strtolower($role);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function requireRole($roles) {
    requireLogin();
    if (!is_array($roles)) $roles = [$roles];
    $roles = array_map('strtolower', $roles);
    if (!in_array(strtolower(getUserRole()), $roles)) {
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
}

function login($usuario, $password) {
    $db = getDB();
    $stmt = $db->prepare("SELECT u.*, r.nombre as rol_nombre FROM usuarios u 
                          JOIN roles r ON u.rol_id = r.id 
                          WHERE u.usuario = ? AND u.activo = 1");
    $stmt->execute([$usuario]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['user']     = $user;
        $_SESSION['role']     = $user['rol_nombre'];
        $_SESSION['turno_id'] = null;

        // Actualizar último acceso
        $db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?")
           ->execute([$user['id']]);

        return $user;
    }
    return false;
}

function logout() {
    session_destroy();
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// ============================================================
// FUNCIONES DE CONFIGURACIÓN
// ============================================================
function getConfig($clave, $default = '') {
    static $config = null;
    if ($config === null) {
        $db = getDB();
        $rows = $db->query("SELECT clave, valor FROM configuracion")->fetchAll();
        $config = [];
        foreach ($rows as $row) {
            $config[$row['clave']] = $row['valor'];
        }
    }
    return $config[$clave] ?? $default;
}

function formatMoney($amount) {
    $symbol = getConfig('moneda_simbolo', '$');
    return $symbol . ' ' . number_format((float)$amount, 0, ',', '.');
}

function generateInvoiceNumber() {
    $db = getDB();
    $db->beginTransaction();
    try {
        $prefijo = getConfig('factura_prefijo', 'FAC');
        $stmt = $db->query("SELECT valor FROM configuracion WHERE clave='factura_consecutivo' FOR UPDATE");
        $row = $stmt->fetch();
        $num = (int)$row['valor'];
        $db->prepare("UPDATE configuracion SET valor = ? WHERE clave='factura_consecutivo'")
           ->execute([$num + 1]);
        $db->commit();
        return $prefijo . '-' . str_pad($num, 6, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function getTurnoActivo($usuario_id = null) {
    $db = getDB();
    $uid = $usuario_id ?? $_SESSION['user_id'];
    $stmt = $db->prepare("SELECT * FROM turnos WHERE usuario_id = ? AND estado = 'abierto' ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$uid]);
    return $stmt->fetch();
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function sanitize($value) {
    return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
}
