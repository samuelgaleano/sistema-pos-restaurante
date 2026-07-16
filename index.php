<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    $role = strtolower(getUserRole());
    if ($role === 'administrador') header('Location: ' . BASE_URL . '/modules/admin/dashboard.php');
    elseif ($role === 'cajero') header('Location: ' . BASE_URL . '/modules/cajero/pos.php');
    else header('Location: ' . BASE_URL . '/modules/mesero/mesas.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = login($_POST['usuario'] ?? '', $_POST['password'] ?? '');
    if ($user) {
        $role = strtolower($user['rol_nombre']);
        if ($role === 'administrador') header('Location: ' . BASE_URL . '/modules/admin/dashboard.php');
        elseif ($role === 'cajero') header('Location: ' . BASE_URL . '/modules/cajero/pos.php');
        else header('Location: ' . BASE_URL . '/modules/mesero/mesas.php');
        exit;
    } else {
        $error = 'Usuario o contraseña incorrectos';
    }
}
$empresa = getConfig('empresa_nombre', 'POS Pro');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($empresa) ?> — Iniciar Sesión</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  :root {
    --bg: #0f1117;
    --surface: #1a1d27;
    --card: #21253a;
    --accent: #6c63ff;
    --accent2: #ff6584;
    --text: #e8eaf0;
    --muted: #8b92a9;
    --border: #2d3150;
    --success: #2ecc71;
    --danger: #e74c3c;
    --radius: 14px;
  }
  * { margin:0; padding:0; box-sizing:border-box; }
  body {
    background: var(--bg);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Segoe UI', system-ui, sans-serif;
    color: var(--text);
    overflow: hidden;
  }
  .bg-shapes {
    position: fixed; inset:0; z-index:0; pointer-events:none;
    overflow: hidden;
  }
  .shape {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    opacity: 0.18;
  }
  .shape-1 { width:400px;height:400px; background:var(--accent); top:-100px; left:-100px; }
  .shape-2 { width:300px;height:300px; background:var(--accent2); bottom:-80px; right:-80px; }
  .shape-3 { width:200px;height:200px; background:#43d8c9; top:50%; left:60%; }
  .login-wrap {
    position: relative; z-index:1;
    width: 100%; max-width: 420px;
    padding: 20px;
  }
  .login-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 48px 40px;
    box-shadow: 0 24px 64px rgba(0,0,0,0.5);
  }
  .login-logo {
    text-align: center;
    margin-bottom: 36px;
  }
  .login-logo .icon-wrap {
    width: 72px; height: 72px;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    margin-bottom: 16px;
    box-shadow: 0 8px 24px rgba(108,99,255,0.35);
  }
  .login-logo h1 { font-size: 24px; font-weight: 700; }
  .login-logo p { color: var(--muted); font-size: 14px; margin-top: 4px; }
  .form-group { margin-bottom: 20px; }
  .form-group label { display:block; font-size:13px; font-weight:600; color:var(--muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:.06em; }
  .input-wrap { position: relative; }
  .input-wrap i { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--muted); font-size:15px; }
  .form-control {
    width: 100%;
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: 10px;
    padding: 12px 14px 12px 42px;
    color: var(--text);
    font-size: 15px;
    transition: border-color .2s, box-shadow .2s;
    outline: none;
  }
  .form-control:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(108,99,255,.18); }
  .btn-login {
    width: 100%;
    background: linear-gradient(135deg, var(--accent), #8b5cf6);
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 14px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: opacity .2s, transform .1s;
    margin-top: 8px;
    letter-spacing: .03em;
  }
  .btn-login:hover { opacity: .9; transform: translateY(-1px); }
  .btn-login:active { transform: translateY(0); }
  .alert-danger {
    background: rgba(231,76,60,.15);
    border: 1px solid rgba(231,76,60,.4);
    color: #ff6b6b;
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 14px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .demo-hints {
    margin-top: 28px;
    padding-top: 20px;
    border-top: 1px solid var(--border);
  }
  .demo-hints p { font-size:12px; color:var(--muted); text-align:center; margin-bottom:10px; }
  .demo-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; }
  .demo-btn {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 8px 6px;
    text-align: center;
    font-size: 12px;
    color: var(--muted);
    cursor: pointer;
    transition: border-color .2s, color .2s;
  }
  .demo-btn:hover { border-color: var(--accent); color: var(--text); }
  .demo-btn span { display:block; font-weight:700; font-size:11px; color: var(--accent); }
</style>
</head>
<body>
<div class="bg-shapes">
  <div class="shape shape-1"></div>
  <div class="shape shape-2"></div>
  <div class="shape shape-3"></div>
</div>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">
      <div class="icon-wrap"><i class="fas fa-cash-register"></i></div>
      <h1><?= htmlspecialchars($empresa) ?></h1>
      <p>Sistema POS Profesional</p>
    </div>
    <?php if ($error): ?>
    <div class="alert-danger"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" action="">
      <div class="form-group">
        <label>Usuario</label>
        <div class="input-wrap">
          <i class="fas fa-user"></i>
          <input type="text" name="usuario" class="form-control" placeholder="Ingresa tu usuario" required autofocus value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label>Contraseña</label>
        <div class="input-wrap">
          <i class="fas fa-lock"></i>
          <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>
      </div>
      <button type="submit" class="btn-login"><i class="fas fa-sign-in-alt"></i> &nbsp;Ingresar al Sistema</button>
    </form>
    <div class="demo-hints">
      <p>Demo: haz clic en un rol para entrar directo (contraseña: <b>password</b>)</p>
      <div class="demo-grid">
        <div class="demo-btn" onclick="fillLogin('admin','password')"><span>ADMIN</span>admin</div>
        <div class="demo-btn" onclick="fillLogin('cajero1','password')"><span>CAJERO</span>cajero1</div>
        <div class="demo-btn" onclick="fillLogin('mesero1','password')"><span>MESERO</span>mesero1</div>
      </div>
    </div>
  </div>
</div>
<script>
// En la demo, un clic en el rol rellena y entra directo (sin escribir credenciales).
function fillLogin(u, p) {
  document.querySelector('[name=usuario]').value = u;
  document.querySelector('[name=password]').value = p;
  document.querySelector('form').submit();
}
</script>
</body>
</html>
