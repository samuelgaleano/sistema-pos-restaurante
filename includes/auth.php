<?php
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
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
