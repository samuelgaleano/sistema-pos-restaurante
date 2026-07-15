<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireLogin();

$db = getDB();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'abrir':
        $turnoExistente = getTurnoActivo();
        if ($turnoExistente) {
            header('Location: ' . BASE_URL . '/modules/cajero/pos.php?error=turno_existe');
            exit;
        }
        $stmt = $db->prepare("INSERT INTO turnos (usuario_id, fecha_apertura, monto_inicial, notas) VALUES(?,NOW(),?,?)");
        $stmt->execute([$_SESSION['user_id'], $_POST['monto_inicial']??0, $_POST['notas']??null]);
        $_SESSION['turno_id'] = $db->lastInsertId();
        header('Location: ' . BASE_URL . '/modules/cajero/pos.php?ok=turno_abierto');
        break;

    case 'cerrar':
        $turnoId     = (int)($_POST['turno_id'] ?? 0);
        $montoFinal  = (float)($_POST['monto_final'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM turnos WHERE id=? AND usuario_id=?");
        $stmt->execute([$turnoId, $_SESSION['user_id']]);
        $turno = $stmt->fetch();
        if ($turno) {
            $diferencia = $montoFinal - ((float)$turno['total_efectivo'] + (float)$turno['monto_inicial']);
            $db->prepare("UPDATE turnos SET fecha_cierre=NOW(), monto_final=?, diferencia=?, estado='cerrado' WHERE id=?")
               ->execute([$montoFinal, $diferencia, $turnoId]);
            $_SESSION['turno_id'] = null;
        }
        header('Location: ' . BASE_URL . '/modules/cajero/turno.php?ok=cerrado');
        break;

    default:
        header('Location: ' . BASE_URL . '/');
}
exit;
