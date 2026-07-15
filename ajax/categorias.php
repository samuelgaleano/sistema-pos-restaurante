<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole(['Administrador']);

$db = getDB();
$action = $_POST['action'] ?? '';

if ($action === 'guardar') {
    $id = (int)($_POST['id'] ?? 0);
    $nombre = sanitize($_POST['nombre']);
    $icono  = sanitize($_POST['icono'] ?? 'fa-tag');
    $color  = sanitize($_POST['color'] ?? '#3498db');
    $orden  = (int)($_POST['orden'] ?? 0);

    if ($id) {
        $db->prepare("UPDATE categorias SET nombre=?,icono=?,color=?,orden=? WHERE id=?")->execute([$nombre,$icono,$color,$orden,$id]);
    } else {
        $db->prepare("INSERT INTO categorias (nombre,icono,color,orden) VALUES(?,?,?,?)")->execute([$nombre,$icono,$color,$orden]);
    }
}
header('Location: ../modules/admin/configuracion.php?ok=cat');
