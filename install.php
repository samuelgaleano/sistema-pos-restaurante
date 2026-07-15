<?php
// ============================================================
// INSTALADOR — Ejecutar UNA SOLA VEZ y luego eliminar este archivo
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step == 2) {
    $host = $_POST['db_host'];
    $name = $_POST['db_name'];
    $user = $_POST['db_user'];
    $pass = $_POST['db_pass'];
    $baseUrl = rtrim($_POST['base_url'], '/');

    try {
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$name`");

        $sql = file_get_contents(__DIR__ . '/database.sql');
        // Remover líneas CREATE DATABASE y USE del script (ya gestionado arriba)
        $sql = preg_replace('/CREATE DATABASE.*?;/is', '', $sql);
        $sql = preg_replace('/USE\s+\w+\s*;/is', '', $sql);

        $pdo->exec($sql);

        // Escribir config/database.php
        $configContent = "<?php
define('DB_HOST', '$host');
define('DB_NAME', '$name');
define('DB_USER', '$user');
define('DB_PASS', '$pass');
define('DB_CHARSET', 'utf8mb4');
define('BASE_URL', '$baseUrl');
define('BASE_PATH', dirname(__DIR__));
date_default_timezone_set('America/Bogota');
define('POS_VERSION', '1.0.0');
define('POS_NAME', 'POS Pro');

function getDB() {
    static \$pdo = null;
    if (\$pdo === null) {
        try {
            \$dsn = \"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=\" . DB_CHARSET;
            \$options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, \$options);
        } catch (PDOException \$e) {
            die(json_encode(['error' => true, 'msg' => 'Error de conexión: ' . \$e->getMessage()]));
        }
    }
    return \$pdo;
}
";
        file_put_contents(__DIR__ . '/config/database.php', $configContent);

        $success = true;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Instalador POS Pro</title>
<style>
  body { font-family:system-ui,sans-serif; background:#0f1117; color:#e8eaf0; max-width:560px; margin:60px auto; padding:20px; }
  .card { background:#1e2235; border:1px solid #2a2e47; border-radius:14px; padding:32px; }
  h1 { font-size:22px; margin-bottom:8px; }
  p.muted { color:#7b82a0; margin-bottom:24px; }
  label { display:block; font-size:13px; font-weight:600; color:#7b82a0; margin-bottom:6px; text-transform:uppercase; }
  input { width:100%; background:#181c2a; border:1.5px solid #2a2e47; border-radius:8px; padding:10px 12px; color:#fff; margin-bottom:16px; font-size:14px; }
  button { width:100%; background:#6c63ff; color:#fff; border:none; border-radius:8px; padding:14px; font-size:15px; font-weight:700; cursor:pointer; }
  .alert { padding:14px; border-radius:8px; margin-bottom:20px; }
  .alert-error { background:rgba(231,76,60,.15); border:1px solid rgba(231,76,60,.4); color:#ff6b6b; }
  .alert-success { background:rgba(46,204,113,.15); border:1px solid rgba(46,204,113,.4); color:#2ecc71; }
  a.btn-link { display:block; text-align:center; background:#2ecc71; color:#fff; padding:14px; border-radius:8px; text-decoration:none; font-weight:700; margin-top:10px;}
  code { background:#181c2a; padding:2px 6px; border-radius:4px; }
</style>
</head>
<body>
<div class="card">
<h1>🛠️ Instalador — Sistema POS Pro</h1>

<?php if ($success): ?>
  <div class="alert alert-success">
    ✅ ¡Instalación completada! La base de datos y las tablas fueron creadas correctamente.
  </div>
  <p class="muted">
    Usuarios de prueba creados (contraseña para todos: <code>password</code>):<br><br>
    👤 <b>admin</b> — Administrador<br>
    👤 <b>cajero1</b> — Cajero<br>
    👤 <b>mesero1</b> — Mesero
  </p>
  <a href="index.php" class="btn-link">Ir al Sistema →</a>
  <p class="muted" style="margin-top:20px;font-size:12px">
    ⚠️ IMPORTANTE: Por seguridad, elimine el archivo <code>install.php</code> y <code>database.sql</code> del servidor ahora que la instalación terminó.
  </p>

<?php elseif ($step == 2): ?>
  <?php if ($error): ?>
  <div class="alert alert-error">❌ Error: <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <p class="muted">Ingresa los datos de conexión de tu base de datos MySQL en cPanel.</p>
  <form method="POST" action="?step=2">
    <label>Host de la Base de Datos</label>
    <input type="text" name="db_host" value="localhost" required>

    <label>Nombre de la Base de Datos</label>
    <input type="text" name="db_name" placeholder="usuario_posdb" required>

    <label>Usuario de MySQL</label>
    <input type="text" name="db_user" placeholder="usuario_dbuser" required>

    <label>Contraseña de MySQL</label>
    <input type="password" name="db_pass" required>

    <label>URL Base del Sistema (sin barra al final)</label>
    <input type="text" name="base_url" placeholder="https://tudominio.com/pos" required>

    <button type="submit">Instalar Sistema</button>
  </form>

<?php else: ?>
  <p class="muted">
    Este asistente creará la base de datos, las tablas y los datos iniciales necesarios
    para que el sistema POS funcione correctamente en tu hosting cPanel.
  </p>
  <p class="muted">Antes de continuar asegúrate de:</p>
  <ul style="color:#7b82a0;margin-bottom:20px;line-height:1.8">
    <li>Haber creado una base de datos MySQL en cPanel</li>
    <li>Haber creado un usuario MySQL con todos los privilegios sobre esa base</li>
    <li>Tener a la mano el host, nombre de base de datos, usuario y contraseña</li>
  </ul>
  <a href="?step=2" class="btn-link" style="background:#6c63ff">Comenzar Instalación →</a>
<?php endif; ?>

</div>
</body>
</html>
