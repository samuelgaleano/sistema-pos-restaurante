<?php
// ajax/ordenes.php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireLogin();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_POST['action'] ?? '';
$db = getDB();

switch ($action) {
    case 'crear':
        $mesaId = (int)($input['mesa_id'] ?? 0);
        // Verificar si ya existe una orden abierta en esa mesa
        $existe = $db->prepare("SELECT id FROM ordenes WHERE mesa_id=? AND estado IN ('abierta','en_proceso','lista')");
        $existe->execute([$mesaId]);
        $existente = $existe->fetch();
        if ($existente) {
            echo json_encode(['success'=>true, 'orden_id'=>$existente['id'], 'existente'=>true]);
            break;
        }
        $stmt = $db->prepare("INSERT INTO ordenes (mesa_id,mesero_id,num_personas,notas) VALUES(?,?,?,?)");
        $stmt->execute([$mesaId, $_SESSION['user_id'], $input['num_personas']??1, $input['notas']??null]);
        $ordenId = $db->lastInsertId();
        $db->prepare("UPDATE mesas SET estado='ocupada' WHERE id=?")->execute([$mesaId]);
        echo json_encode(['success'=>true, 'orden_id'=>$ordenId]);
        break;

    default:
        echo json_encode(['success'=>false,'error'=>'Acción no válida']);
}
