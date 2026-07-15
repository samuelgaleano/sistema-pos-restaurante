<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireLogin();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';
$db = getDB();

switch ($action) {
    case 'cambiar_estado':
        $estados = ['disponible','ocupada','reservada','mantenimiento'];
        $estado  = $input['estado'] ?? '';
        $mesaId  = (int)($input['mesa_id'] ?? 0);
        if (!in_array($estado, $estados)) { echo json_encode(['success'=>false,'error'=>'Estado inválido']); break; }
        $db->prepare("UPDATE mesas SET estado=? WHERE id=?")->execute([$estado, $mesaId]);
        echo json_encode(['success'=>true]);
        break;
    default:
        echo json_encode(['success'=>false,'error'=>'Acción no válida']);
}
