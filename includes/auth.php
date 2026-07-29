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
    // use_strict_mode: el servidor rechaza un ID de sesion que el no haya emitido, asi que
    // un atacante no puede "fijar" un ID conocido en el navegador de otro visitante. Es la
    // raiz real de la fijacion de sesion; el regenerate del login (abajo) la cierra ademas.
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// ============================================================
// CSRF: verificacion de origen (defensa central, sin token por formulario)
// ============================================================
// La cookie de sesion es SameSite=None (obligatorio para el iframe de la demo), asi que
// el navegador la adjunta tambien en peticiones cross-site: sin defensa, cualquier pagina
// externa podia hacer que el navegador de un visitante logueado ejecutara acciones de
// escritura. Como TODOS los endpoints de escritura incluyen este auth.php, se verifica
// aqui, en un solo sitio, que las peticiones que cambian estado vengan de un origen
// de confianza. No hace falta tocar formulario por formulario.
function pos_csrf_hosts_confiables() {
    $hosts = [strtolower($_SERVER['HTTP_HOST'] ?? ''), 'localhost', '127.0.0.1',
              'portafolio-pixies.vercel.app', 'pos-restaurante-demo.onrender.com'];
    $extra = getenv('CSRF_TRUSTED_HOSTS');
    if ($extra) {
        foreach (explode(',', $extra) as $h) $hosts[] = strtolower(trim($h));
    }
    return array_filter($hosts);
}

function pos_csrf_host_de($url) {
    // extrae solo el host (sin esquema, puerto ni ruta) de un Origin o Referer
    $host = parse_url((string)$url, PHP_URL_HOST);
    return $host ? strtolower($host) : '';
}

function pos_csrf_verificar() {
    $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($metodo, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
        return; // las lecturas no cambian estado
    }
    $origin  = $_SERVER['HTTP_ORIGIN']  ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    // Si el navegador no manda ninguno (curl, integraciones no-navegador), no se bloquea:
    // un CSRF real desde otra pestaña siempre lleva al menos uno de los dos.
    if ($origin === '' && $referer === '') {
        return;
    }
    $host = $origin !== '' ? pos_csrf_host_de($origin) : pos_csrf_host_de($referer);
    if (!in_array($host, pos_csrf_hosts_confiables(), true)) {
        http_response_code(403);
        if (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
            || stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Origen no permitido']);
        } else {
            echo 'Origen no permitido';
        }
        exit;
    }
}

// Se ejecuta al incluir auth.php (lo hacen todos los endpoints), antes de procesar nada.
pos_csrf_verificar();

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
        // Rotar el ID de sesion al autenticar: si el navegador traia un PHPSESSID de antes
        // (posible vector de fijacion), queda invalidado y se emite uno nuevo ya autenticado.
        session_regenerate_id(true);
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
    // getDB() devuelve SIEMPRE la misma conexion (singleton estatico) y PDO no soporta
    // transacciones anidadas. Quien nos llama a menudo ya tiene una abierta -- ajax/ventas.php
    // envuelve la venta entera en una -- y abrir otra aqui lanzaba "There is already an active
    // transaction", que reventaba TODAS las ventas. Si ya hay una transaccion en curso nos
    // sumamos a ella y dejamos que el commit lo haga el llamador; el FOR UPDATE de abajo
    // sigue tomando el lock dentro de esa transaccion, que es justo lo que se buscaba.
    $transaccionPropia = !$db->inTransaction();
    if ($transaccionPropia) {
        $db->beginTransaction();
    }
    try {
        $prefijo = getConfig('factura_prefijo', 'FAC');
        $stmt = $db->query("SELECT valor FROM configuracion WHERE clave='factura_consecutivo' FOR UPDATE");
        $row = $stmt->fetch();
        $num = (int)$row['valor'];
        $db->prepare("UPDATE configuracion SET valor = ? WHERE clave='factura_consecutivo'")
           ->execute([$num + 1]);
        if ($transaccionPropia) {
            $db->commit();
        }
        return $prefijo . '-' . str_pad($num, 6, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        if ($transaccionPropia && $db->inTransaction()) {
            $db->rollBack();
        }
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
