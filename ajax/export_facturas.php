<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole(['Administrador','Cajero']);

$db    = getDB();
$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? date('Y-m-d');

$stmt = $db->prepare("
    SELECT f.numero_factura, f.fecha, c.nombre as cliente, c.num_doc,
           u.nombre as cajero, m.numero as mesa,
           f.subtotal, f.descuento_valor, f.impuesto_valor, f.propina, f.total,
           f.metodo_pago, f.pago_efectivo, f.pago_tarjeta, f.cambio, f.estado
    FROM facturas f
    LEFT JOIN clientes c ON f.cliente_id=c.id
    LEFT JOIN usuarios u ON f.cajero_id=u.id
    LEFT JOIN mesas m ON f.mesa_id=m.id
    WHERE DATE(f.fecha) BETWEEN ? AND ?
    ORDER BY f.fecha
");
$stmt->execute([$desde, $hasta]);
$rows = $stmt->fetchAll();

$filename = 'facturas_'.$desde.'_al_'.$hasta.'.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Pragma: no-cache');

$out = fopen('php://output', 'w');
// BOM para Excel
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, ['#Factura','Fecha','Cliente','Documento','Cajero','Mesa','Subtotal','Descuento','IVA','Propina','Total','Método Pago','Efectivo Recibido','Tarjeta','Cambio','Estado'], ';');

foreach ($rows as $row) {
    fputcsv($out, [
        $row['numero_factura'],
        $row['fecha'],
        $row['cliente'],
        $row['num_doc'],
        $row['cajero'],
        $row['mesa'],
        number_format($row['subtotal'],2,'.',''),
        number_format($row['descuento_valor'],2,'.',''),
        number_format($row['impuesto_valor'],2,'.',''),
        number_format($row['propina'],2,'.',''),
        number_format($row['total'],2,'.',''),
        $row['metodo_pago'],
        number_format($row['pago_efectivo'],2,'.',''),
        number_format($row['pago_tarjeta'],2,'.',''),
        number_format($row['cambio'],2,'.',''),
        $row['estado'],
    ], ';');
}
fclose($out);
