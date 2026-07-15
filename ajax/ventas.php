<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireLogin();

header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { echo json_encode(['success'=>false,'error'=>'Datos inválidos']); exit; }

$db = getDB();

try {
    $db->beginTransaction();

    $numFactura = generateInvoiceNumber();
    $cajeroId   = $_SESSION['user_id'];
    $meseroId   = $input['mesero_id'] ?? null;
    $now        = date('Y-m-d H:i:s');

    // Insertar factura
    $stmt = $db->prepare("INSERT INTO facturas 
        (turno_id,mesa_id,cliente_id,cajero_id,mesero_id,numero_factura,prefijo,fecha,
         subtotal,descuento_porcentaje,descuento_valor,impuesto_porcentaje,impuesto_valor,
         propina,total,metodo_pago,pago_efectivo,pago_tarjeta,pago_transferencia,cambio,
         estado,tipo,notas)
        VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pagada','venta',?)");

    $impPct = (float)getConfig('impuesto_porcentaje', 0);
    $stmt->execute([
        $input['turno_id'] ?? null,
        $input['mesa_id'] ?? null,
        $input['cliente_id'] ?? 1,
        $cajeroId,
        $meseroId,
        $numFactura,
        getConfig('factura_prefijo','FAC'),
        $now,
        $input['subtotal'],
        $input['descuento_porcentaje'] ?? 0,
        $input['descuento_valor'] ?? 0,
        $impPct,
        $input['impuesto_valor'] ?? 0,
        $input['propina'] ?? 0,
        $input['total'],
        $input['metodo_pago'],
        $input['pago_efectivo'] ?? 0,
        $input['pago_tarjeta'] ?? 0,
        $input['pago_transferencia'] ?? 0,
        $input['cambio'] ?? 0,
        $input['notas'] ?? null
    ]);
    $facturaId = $db->lastInsertId();

    // Items
    $stmtItem = $db->prepare("INSERT INTO factura_items 
        (factura_id,producto_id,nombre_producto,cantidad,precio_unitario,descuento_porcentaje,descuento_valor,subtotal)
        VALUES(?,?,?,?,?,0,0,?)");

    // Actualizar stock
    $stmtStock = $db->prepare("UPDATE productos SET stock_actual = stock_actual - ? WHERE id = ? AND tiene_stock = 1");
    $stmtMov   = $db->prepare("INSERT INTO movimientos_inventario (producto_id,usuario_id,tipo,cantidad,referencia,nota) VALUES(?,?,?,?,?,?)");

    foreach ($input['items'] as $item) {
        $stmtItem->execute([
            $facturaId,
            $item['producto_id'],
            $item['nombre'],
            $item['cantidad'],
            $item['precio_unitario'],
            $item['subtotal']
        ]);
        $stmtStock->execute([$item['cantidad'], $item['producto_id']]);
        // Registro de movimiento
        $affected = $stmtStock->rowCount();
        if ($affected > 0) {
            $stmtMov->execute([$item['producto_id'], $cajeroId, 'venta', $item['cantidad'], $numFactura, 'Venta POS']);
        }
    }

    // Actualizar turno
    // IMPORTANTE: para efectivo se registra el TOTAL de la venta (no lo que el cliente entregó),
    // ya que pago_efectivo puede incluir cambio que no es ingreso real de caja.
    if (!empty($input['turno_id'])) {
        $efectivoNeto = 0; $tarjetaNeto = 0; $transferenciaNeto = 0;
        switch ($input['metodo_pago']) {
            case 'efectivo':       $efectivoNeto = $input['total']; break;
            case 'tarjeta':        $tarjetaNeto = $input['total']; break;
            case 'transferencia':  $transferenciaNeto = $input['total']; break;
            case 'mixto':
                $efectivoNeto      = $input['pago_efectivo'] ?? 0;
                $tarjetaNeto       = $input['pago_tarjeta'] ?? 0;
                $transferenciaNeto = $input['pago_transferencia'] ?? 0;
                break;
        }
        $db->prepare("UPDATE turnos SET total_ventas=total_ventas+?, total_efectivo=total_efectivo+?, total_tarjeta=total_tarjeta+?, total_transferencia=total_transferencia+?, total_descuentos=total_descuentos+? WHERE id=?")
           ->execute([$input['total'], $efectivoNeto, $tarjetaNeto, $transferenciaNeto, $input['descuento_valor']??0, $input['turno_id']]);
    }

    // Liberar mesa
    if (!empty($input['mesa_id'])) {
        $db->prepare("UPDATE mesas SET estado='disponible' WHERE id=?")->execute([$input['mesa_id']]);
    }

    $db->commit();
    echo json_encode(['success'=>true, 'factura_id'=>$facturaId, 'numero_factura'=>$numFactura]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
}
