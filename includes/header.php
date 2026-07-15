<?php
// includes/header.php — parámetros: $pageTitle, $activeMenu
$user = getCurrentUser();
$role = strtolower(getUserRole());
$empresa = getConfig('empresa_nombre', 'POS Pro');
$turno = getTurnoActivo();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'POS') ?> — <?= htmlspecialchars($empresa) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
</head>
<body>
<div class="app-layout">
  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="sidebar-brand">
        <i class="fas fa-cash-register"></i>
        <span><?= htmlspecialchars($empresa) ?></span>
      </div>
      <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    </div>

    <!-- User info -->
    <div class="sidebar-user">
      <div class="user-avatar">
        <?= strtoupper(substr($user['nombre'],0,1).substr($user['apellido'],0,1)) ?>
      </div>
      <div class="user-info">
        <span class="user-name"><?= htmlspecialchars($user['nombre'].' '.$user['apellido']) ?></span>
        <span class="user-role"><?= htmlspecialchars($user['rol_nombre']) ?></span>
      </div>
    </div>

    <nav class="sidebar-nav">
      <?php if ($role === 'administrador'): ?>
      <div class="nav-section">General</div>
      <a href="<?= BASE_URL ?>/modules/admin/dashboard.php" class="nav-item <?= ($activeMenu??'')==='dashboard'?'active':'' ?>">
        <i class="fas fa-chart-pie"></i><span>Dashboard</span>
      </a>
      <a href="<?= BASE_URL ?>/modules/cajero/pos.php" class="nav-item <?= ($activeMenu??'')==='pos'?'active':'' ?>">
        <i class="fas fa-cash-register"></i><span>Punto de Venta</span>
      </a>
      <a href="<?= BASE_URL ?>/modules/mesero/mesas.php" class="nav-item <?= ($activeMenu??'')==='mesas'?'active':'' ?>">
        <i class="fas fa-utensils"></i><span>Mesas</span>
      </a>
      <div class="nav-section">Administración</div>
      <a href="<?= BASE_URL ?>/modules/admin/productos.php" class="nav-item <?= ($activeMenu??'')==='productos'?'active':'' ?>">
        <i class="fas fa-box-open"></i><span>Productos</span>
      </a>
      <a href="<?= BASE_URL ?>/modules/admin/inventario.php" class="nav-item <?= ($activeMenu??'')==='inventario'?'active':'' ?>">
        <i class="fas fa-warehouse"></i><span>Inventario</span>
      </a>
      <a href="<?= BASE_URL ?>/modules/admin/facturas.php" class="nav-item <?= ($activeMenu??'')==='facturas'?'active':'' ?>">
        <i class="fas fa-file-invoice-dollar"></i><span>Facturas</span>
      </a>
      <a href="<?= BASE_URL ?>/modules/admin/turnos.php" class="nav-item <?= ($activeMenu??'')==='turnos'?'active':'' ?>">
        <i class="fas fa-clock"></i><span>Turnos / Caja</span>
      </a>
      <a href="<?= BASE_URL ?>/modules/admin/clientes.php" class="nav-item <?= ($activeMenu??'')==='clientes'?'active':'' ?>">
        <i class="fas fa-users"></i><span>Clientes</span>
      </a>
      <a href="<?= BASE_URL ?>/modules/admin/usuarios.php" class="nav-item <?= ($activeMenu??'')==='usuarios'?'active':'' ?>">
        <i class="fas fa-user-cog"></i><span>Usuarios</span>
      </a>
      <a href="<?= BASE_URL ?>/modules/admin/reportes.php" class="nav-item <?= ($activeMenu??'')==='reportes'?'active':'' ?>">
        <i class="fas fa-chart-bar"></i><span>Reportes</span>
      </a>
      <a href="<?= BASE_URL ?>/modules/admin/configuracion.php" class="nav-item <?= ($activeMenu??'')==='configuracion'?'active':'' ?>">
        <i class="fas fa-cog"></i><span>Configuración</span>
      </a>

      <?php elseif ($role === 'cajero'): ?>
      <div class="nav-section">Cajero</div>
      <a href="<?= BASE_URL ?>/modules/cajero/pos.php" class="nav-item <?= ($activeMenu??'')==='pos'?'active':'' ?>">
        <i class="fas fa-cash-register"></i><span>Punto de Venta</span>
      </a>
      <a href="<?= BASE_URL ?>/modules/cajero/turno.php" class="nav-item <?= ($activeMenu??'')==='turno'?'active':'' ?>">
        <i class="fas fa-clock"></i><span>Mi Turno</span>
      </a>
      <a href="<?= BASE_URL ?>/modules/cajero/facturas.php" class="nav-item <?= ($activeMenu??'')==='facturas'?'active':'' ?>">
        <i class="fas fa-receipt"></i><span>Mis Facturas</span>
      </a>

      <?php else: ?>
      <div class="nav-section">Mesero</div>
      <a href="<?= BASE_URL ?>/modules/mesero/mesas.php" class="nav-item <?= ($activeMenu??'')==='mesas'?'active':'' ?>">
        <i class="fas fa-border-all"></i><span>Mesas</span>
      </a>
      <a href="<?= BASE_URL ?>/modules/mesero/ordenes.php" class="nav-item <?= ($activeMenu??'')==='ordenes'?'active':'' ?>">
        <i class="fas fa-list-alt"></i><span>Mis Órdenes</span>
      </a>
      <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
      <?php if ($turno): ?>
      <div class="turno-badge active"><i class="fas fa-circle"></i> Turno Activo</div>
      <?php else: ?>
      <div class="turno-badge inactive"><i class="fas fa-circle"></i> Sin Turno</div>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>/logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i><span>Salir</span></a>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main class="main-content" id="main-content">
    <header class="topbar">
      <div class="topbar-left">
        <button class="topbar-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
        <h2 class="page-title"><?= htmlspecialchars($pageTitle ?? '') ?></h2>
      </div>
      <div class="topbar-right">
        <div class="topbar-time" id="topbarTime"></div>
        <?php if ($turno): ?>
        <span class="badge-turno">Turno #<?= $turno['id'] ?></span>
        <?php endif; ?>
      </div>
    </header>
    <div class="page-body">
