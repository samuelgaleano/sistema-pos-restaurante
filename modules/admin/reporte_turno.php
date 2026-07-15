<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireRole(['Administrador','Cajero']);

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$turno = $db->prepare("SELECT t.*, u.nombre, u.apellido FROM turnos t JOIN usuarios u ON t.usuario_id=u.id WHERE t.id=?");
$turno->execute([$id]);
$turno = $turno->fetch();
if (!$turno) { die('Turno no encontrado.'); }

$facturas = $db->prepare("
    SELECT f.*, c.nombre as cliente
    FROM facturas f LEFT JOIN clientes c ON f.cliente_id=c.id
    WHERE f.turno_id=? ORDER BY f.created_at
");
$facturas->execute([$id]);
$facturas = $facturas->fetchAll();

$pagadas  = array_filter($facturas, fn($f)=>$f['estado']==='pagada');
$anuladas = array_filter($facturas, fn($f)=>$f['estado']==='anulada');

$empresa = getConfig('empresa_nombre','Mi Negocio');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Reporte Turno #<?= $id ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  :root{--bg:#0f1117;--card:#1e2235;--border:#2a2e47;--text:#e8eaf0;--muted:#7b82a0;--accent:#6c63ff;--success:#2ecc71;--danger:#e74c3c;}
  *{margin:0;padding:0;box-sizing:border-box;}
  body{background:var(--bg);color:var(--text);font-family:'Segoe UI',sans-serif;padding:24px;max-width:900px;margin:0 auto;}
  .card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:16px;}
  h1{font-size:20px;margin-bottom:4px;}
  .muted{color:var(--muted);}
  table{width:100%;border-collapse:collapse;margin-top:10px;}
  th{text-align:left;font-size:11px;text-transform:uppercase;color:var(--muted);padding:8px;border-bottom:1px solid var(--border);}
  td{padding:8px;border-bottom:1px solid var(--border);font-size:13px;}
  .row{display:flex;justify-content:space-between;padding:6px 0;}
  .grand{font-size:18px;font-weight:800;color:var(--accent);border-top:1px solid var(--border);padding-top:10px;margin-top:6px;}
  .badge{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;}
  .badge-success{background:rgba(46,204,113,.15);color:var(--success);}
  .badge-danger{background:rgba(231,76,60,.15);color:var(--danger);}
  .btn{display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border-radius:8px;border:none;background:var(--accent);color:#fff;font-weight:600;cursor:pointer;text-decoration:none;font-size:14px;}
  @media print{ body{background:#fff;color:#000;} .card{border:1px solid #ddd;} .no-print{display:none;} }
</style>
</head>
<body>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px" class="no-print">
  <h2>Reporte de Cierre de Turno</h2>
  <button class="btn" onclick="window.print()"><i class="fas fa-print"></i> Imprimir</button>
</div>

<div class="card">
  <h1><?= htmlspecialchars($empresa) ?></h1>
  <p class="muted">Reporte de Turno #<?= $turno['id'] ?> — <?= htmlspecialchars($turno['nombre'].' '.$turno['apellido']) ?></p>
  <div style="display:flex;gap:24px;margin-top:12px;flex-wrap:wrap">
    <div><span class="muted">Apertura:</span> <?= date('d/m/Y H:i', strtotime($turno['fecha_apertura'])) ?></div>
    <div><span class="muted">Cierre:</span> <?= $turno['fecha_cierre'] ? date('d/m/Y H:i', strtotime($turno['fecha_cierre'])) : 'En curso' ?></div>
    <div><span class="badge badge-<?= $turno['estado']==='abierto'?'success':'danger' ?>"><?= ucfirst($turno['estado']) ?></span></div>
  </div>
</div>

<div class="card">
  <h3 style="margin-bottom:10px">Resumen Financiero</h3>
  <div class="row"><span>Monto Inicial:</span><span><?= formatMoney($turno['monto_inicial']) ?></span></div>
  <div class="row"><span>Ventas en Efectivo:</span><span><?= formatMoney($turno['total_efectivo']) ?></span></div>
  <div class="row"><span>Ventas con Tarjeta:</span><span><?= formatMoney($turno['total_tarjeta']) ?></span></div>
  <div class="row"><span>Ventas por Transferencia:</span><span><?= formatMoney($turno['total_transferencia']) ?></span></div>
  <div class="row"><span>Total Descuentos Aplicados:</span><span class="muted">-<?= formatMoney($turno['total_descuentos']) ?></span></div>
  <div class="row grand"><span>TOTAL VENTAS:</span><span><?= formatMoney($turno['total_ventas']) ?></span></div>
  <?php if($turno['monto_final'] !== null): ?>
  <div class="row" style="margin-top:10px"><span>Efectivo Esperado en Caja:</span><span><?= formatMoney($turno['monto_inicial'] + $turno['total_efectivo']) ?></span></div>
  <div class="row"><span>Efectivo Real Contado:</span><span><?= formatMoney($turno['monto_final']) ?></span></div>
  <div class="row" style="font-weight:700"><span>Diferencia:</span><span style="color:<?= $turno['diferencia']>=0?'var(--success)':'var(--danger)' ?>"><?= ($turno['diferencia']>=0?'+':'').formatMoney($turno['diferencia']) ?></span></div>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="margin-bottom:10px">Facturas del Turno (<?= count($pagadas) ?> pagadas, <?= count($anuladas) ?> anuladas)</h3>
  <table>
    <thead><tr><th>#</th><th>Hora</th><th>Cliente</th><th>Total</th><th>Método</th><th>Estado</th></tr></thead>
    <tbody>
      <?php foreach($facturas as $f): ?>
      <tr>
        <td><?= htmlspecialchars($f['numero_factura']) ?></td>
        <td><?= date('H:i', strtotime($f['created_at'])) ?></td>
        <td><?= htmlspecialchars($f['cliente']??'General') ?></td>
        <td><?= formatMoney($f['total']) ?></td>
        <td><?= ucfirst($f['metodo_pago']) ?></td>
        <td><span class="badge badge-<?= $f['estado']==='pagada'?'success':'danger' ?>"><?= ucfirst($f['estado']) ?></span></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if($turno['notas']): ?>
<div class="card"><h3 style="margin-bottom:8px">Notas</h3><p class="muted"><?= htmlspecialchars($turno['notas']) ?></p></div>
<?php endif; ?>

</body>
</html>
