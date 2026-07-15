<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireRole(['Administrador','Cajero']);

$db = getDB();
$pageTitle = 'Facturas';
$activeMenu = 'facturas';

$msg = '';
// Anular factura
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'anular') {
    $fid = (int)$_POST['factura_id'];
    $db->prepare("UPDATE facturas SET estado='anulada' WHERE id=?")->execute([$fid]);
    $msg = 'Factura anulada.';
}

// Filtros
$desde  = $_GET['desde']  ?? date('Y-m-01');
$hasta  = $_GET['hasta']  ?? date('Y-m-d');
$estado = $_GET['estado'] ?? '';
$q      = $_GET['q']      ?? '';

$where = "WHERE DATE(f.fecha) BETWEEN ? AND ?";
$params = [$desde, $hasta];
if ($estado) { $where .= " AND f.estado=?"; $params[] = $estado; }
if ($q)      { $where .= " AND (f.numero_factura LIKE ? OR c.nombre LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; }

$facturas = $db->prepare("
    SELECT f.*, u.nombre as cajero, c.nombre as cliente, m.numero as mesa_num
    FROM facturas f
    LEFT JOIN usuarios u ON f.cajero_id=u.id
    LEFT JOIN clientes c ON f.cliente_id=c.id
    LEFT JOIN mesas m ON f.mesa_id=m.id
    $where
    ORDER BY f.created_at DESC LIMIT 200
");
$facturas->execute($params);
$facturas = $facturas->fetchAll();

$totalesStmt = $db->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(total),0) as suma FROM facturas f LEFT JOIN clientes c ON f.cliente_id=c.id $where AND f.estado='pagada' AND f.tipo='venta'");
$totalesStmt->execute($params);
$totales = $totalesStmt->fetch();

require_once '../../includes/header.php';
?>

<?php if($msg): ?><div class="alert alert-success alert-auto"><i class="fas fa-check"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>

<!-- Filtros -->
<div class="card mb-4">
  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div class="form-group" style="margin:0">
      <label class="form-label">Desde</label>
      <input type="date" name="desde" class="form-control" value="<?= $desde ?>">
    </div>
    <div class="form-group" style="margin:0">
      <label class="form-label">Hasta</label>
      <input type="date" name="hasta" class="form-control" value="<?= $hasta ?>">
    </div>
    <div class="form-group" style="margin:0">
      <label class="form-label">Estado</label>
      <select name="estado" class="form-control">
        <option value="">Todos</option>
        <option value="pagada" <?= $estado==='pagada'?'selected':'' ?>>Pagadas</option>
        <option value="abierta" <?= $estado==='abierta'?'selected':'' ?>>Abiertas</option>
        <option value="anulada" <?= $estado==='anulada'?'selected':'' ?>>Anuladas</option>
      </select>
    </div>
    <div class="form-group" style="margin:0;flex:1;min-width:180px">
      <label class="form-label">Buscar</label>
      <input type="text" name="q" class="form-control" placeholder="# factura o cliente..." value="<?= htmlspecialchars($q) ?>">
    </div>
    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filtrar</button>
    <a href="facturas.php" class="btn btn-secondary">Limpiar</a>
  </form>
</div>

<!-- Resumen -->
<div class="stats-grid mb-4" style="grid-template-columns:repeat(3,1fr)">
  <div class="stat-card">
    <div class="stat-icon green"><i class="fas fa-receipt"></i></div>
    <div><div class="stat-value"><?= number_format($totales['cnt']) ?></div><div class="stat-label">Facturas Pagadas</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple"><i class="fas fa-dollar-sign"></i></div>
    <div><div class="stat-value"><?= formatMoney($totales['suma']) ?></div><div class="stat-label">Total Período</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon teal"><i class="fas fa-chart-bar"></i></div>
    <div><div class="stat-value"><?= $totales['cnt']>0 ? formatMoney($totales['suma']/$totales['cnt']) : '$0' ?></div><div class="stat-label">Ticket Promedio</div></div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="fas fa-file-invoice-dollar text-accent"></i> Listado de Facturas (<?= count($facturas) ?>)</span>
    <a href="../../ajax/export_facturas.php?desde=<?= $desde ?>&hasta=<?= $hasta ?>" class="btn btn-secondary btn-sm"><i class="fas fa-download"></i> Exportar CSV</a>
  </div>
  <div class="table-wrap">
    <table class="pos-table">
      <thead>
        <tr><th>#Factura</th><th>Fecha</th><th>Cliente</th><th>Mesa</th><th>Cajero</th><th>Subtotal</th><th>Descuento</th><th>Total</th><th>Método</th><th>Estado</th><th>Acciones</th></tr>
      </thead>
      <tbody>
        <?php foreach($facturas as $f): ?>
        <tr>
          <td class="fw-bold text-accent"><?= htmlspecialchars($f['numero_factura']) ?></td>
          <td class="fs-sm"><?= date('d/m/Y H:i', strtotime($f['fecha'])) ?></td>
          <td><?= htmlspecialchars($f['cliente'] ?? 'General') ?></td>
          <td class="text-muted"><?= $f['mesa_num'] ? 'Mesa '.$f['mesa_num'] : '—' ?></td>
          <td class="text-muted fs-sm"><?= htmlspecialchars($f['cajero']) ?></td>
          <td><?= formatMoney($f['subtotal']) ?></td>
          <td class="text-danger"><?= $f['descuento_valor']>0 ? '-'.formatMoney($f['descuento_valor']) : '—' ?></td>
          <td class="fw-bold"><?= formatMoney($f['total']) ?></td>
          <td>
            <?php $metPago=['efectivo'=>'💵','tarjeta'=>'💳','transferencia'=>'🏦','mixto'=>'🔀']; ?>
            <span class="badge badge-info"><?= ($metPago[$f['metodo_pago']]??'').' '.ucfirst($f['metodo_pago']) ?></span>
          </td>
          <td>
            <?php $bs=['pagada'=>'success','abierta'=>'warning','anulada'=>'danger']; ?>
            <span class="badge badge-<?= $bs[$f['estado']]??'muted' ?>"><?= ucfirst($f['estado']) ?></span>
          </td>
          <td style="display:flex;gap:4px">
            <a href="../cajero/imprimir.php?id=<?= $f['id'] ?>" target="_blank" class="btn btn-sm btn-secondary"><i class="fas fa-print"></i></a>
            <?php if($f['estado']==='pagada'): ?>
            <form method="POST" onsubmit="return confirm('¿Anular factura <?= $f['numero_factura'] ?>?')">
              <input type="hidden" name="action" value="anular">
              <input type="hidden" name="factura_id" value="<?= $f['id'] ?>">
              <button class="btn btn-sm btn-danger" type="submit"><i class="fas fa-ban"></i></button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
