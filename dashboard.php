<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
requireLogin();

$role = strtolower(getUserRole());
if ($role === 'administrador') header('Location: ' . BASE_URL . '/modules/admin/dashboard.php');
elseif ($role === 'cajero') header('Location: ' . BASE_URL . '/modules/cajero/pos.php');
else header('Location: ' . BASE_URL . '/modules/mesero/mesas.php');
exit;
