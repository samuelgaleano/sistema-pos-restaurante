<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireRole(['Administrador','Cajero']);

$db = getDB();
$pageTitle = 'Mis Facturas';
$activeMenu = 'facturas';

$turno = getTurnoActivo();
$hoy   = date('Y-m-d');

$facturas = $db->prepare("
    SELECT f.*, c.nombre as cliente
    FROM facturas f
    LEFT JOIN clientes c ON f.cliente_id=c.id
    WHERE f.cajero_id=? AND DATE(f.fecha)=?
    ORDER BY f.created_at DESC
");
$facturas->execute([$_SESSION['user_id'], $hoy]);
$facturas = $facturas->fetchAll();

$totalHoy = array_sum(array_map(fn($f) => $f['estado']==='pagada' ? (float)$f['total'] : 0, $facturas));

require_once '../../includes/header.php';
?>

<div class="stats-grid mb-4" style="grid-template-columns:repeat(3,1fr)">
  <div class="stat-card">
    <div class="stat-icon green"><i class="fas fa-receipt"></i></div>
    <div><div class="stat-value"><?= count(array_filter($facturas, fn($f)=>$f['estado']==='pagada')) ?></div><div class="stat-label">Facturas Hoy</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple"><i class="fas fa-dollar-sign"></i></div>
    <div><div class="stat-value"><?= formatMoney($totalHoy) ?></div><div class="stat-label">Total Vendido Hoy</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon teal"><i class="fas fa-chart-bar"></i></div>
    <div><div class="stat-value"><?= count(array_filter($facturas,fn($f)=>$f['estado']==='pagada'))>0 ? formatMoney($totalHoy/max(1,count(array_filter($facturas,fn($f)=>$f['estado']==='pagada')))) : '$0' ?></div><div class="stat-label">Ticket Promedio</div></div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="fas fa-receipt text-accent"></i> Mis Ventas de Hoy (<?= date('d/m/Y') ?>)</span>
    <a href="pos.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Nueva Venta</a>
  </div>
  <div class="table-wrap">
    <table class="pos-table">
      <thead><tr><th>#Factura</th><th>Hora</th><th>Cliente</th><th>Total</th><th>Método</th><th>Cambio</th><th>Estado</th><th></th></tr></thead>
      <tbody>
        <?php foreach($facturas as $f): ?>
        <tr>
          <td class="fw-bold text-accent"><?= htmlspecialchars($f['numero_factura']) ?></td>
          <td class="fs-sm text-muted"><?= date('H:i:s', strtotime($f['created_at'])) ?></td>
          <td><?= htmlspecialchars($f['cliente']??'General') ?></td>
          <td class="fw-bold"><?= formatMoney($f['total']) ?></td>
          <td><span class="badge badge-info"><?= ucfirst($f['metodo_pago']) ?></span></td>
          <td class="<?= $f['cambio']>0 ? 'text-success':'' ?>"><?= $f['cambio']>0 ? formatMoney($f['cambio']):'—' ?></td>
          <td><?php $bs=['pagada'=>'success','anulada'=>'danger','abierta'=>'warning']; ?>
            <span class="badge badge-<?= $bs[$f['estado']]??'muted' ?>"><?= ucfirst($f['estado']) ?></span>
          </td>
          <td><a href="imprimir.php?id=<?= $f['id'] ?>" target="_blank" class="btn btn-sm btn-secondary"><i class="fas fa-print"></i></a></td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$facturas): ?>
        <tr><td colspan="8" class="text-center text-muted" style="padding:40px">Sin ventas hoy. <a href="pos.php" class="text-accent">Ir al POS →</a></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
