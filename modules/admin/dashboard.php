<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireRole('Administrador');

$db = getDB();
$pageTitle = 'Dashboard';
$activeMenu = 'dashboard';

// ---- Stats hoy ----
$hoy = date('Y-m-d');
$statsHoy = $db->prepare("
    SELECT 
        COUNT(*) as total_facturas,
        COALESCE(SUM(total),0) as total_ventas,
        COALESCE(SUM(CASE WHEN metodo_pago='efectivo' THEN pago_efectivo ELSE 0 END),0) as efectivo,
        COALESCE(SUM(CASE WHEN metodo_pago='tarjeta' THEN pago_tarjeta ELSE 0 END),0) as tarjeta,
        COALESCE(SUM(descuento_valor),0) as descuentos
    FROM facturas WHERE DATE(fecha)=? AND estado='pagada' AND tipo='venta'
");
$statsHoy->execute([$hoy]);
$hoyData = $statsHoy->fetch();

// Ayer
$ayer = date('Y-m-d', strtotime('-1 day'));
$ventasAyer = $db->prepare("SELECT COALESCE(SUM(total),0) as total FROM facturas WHERE DATE(fecha)=? AND estado='pagada' AND tipo='venta'");
$ventasAyer->execute([$ayer]);
$ayerTotal = $ventasAyer->fetchColumn();
$cambio = $ayerTotal > 0 ? round((($hoyData['total_ventas'] - $ayerTotal) / $ayerTotal) * 100, 1) : 100;

// Mes actual
$mesInicio = date('Y-m-01');
$ventasMes = $db->prepare("SELECT COALESCE(SUM(total),0) as total, COUNT(*) as cnt FROM facturas WHERE DATE(fecha)>=? AND estado='pagada' AND tipo='venta'");
$ventasMes->execute([$mesInicio]);
$mesData = $ventasMes->fetch();

// Productos bajos en stock
$stockBajo = $db->query("SELECT nombre, stock_actual, stock_minimo FROM productos WHERE tiene_stock=1 AND stock_actual <= stock_minimo AND activo=1 ORDER BY (stock_actual - stock_minimo) ASC LIMIT 5")->fetchAll();

// Mesas ocupadas
$mesasStats = $db->query("SELECT estado, COUNT(*) as cnt FROM mesas WHERE activo=1 GROUP BY estado")->fetchAll(PDO::FETCH_KEY_PAIR);

// Últimas 10 facturas
$ultFacturas = $db->query("
    SELECT f.*, u.nombre as cajero_nombre, c.nombre as cliente_nombre
    FROM facturas f
    LEFT JOIN usuarios u ON f.cajero_id = u.id
    LEFT JOIN clientes c ON f.cliente_id = c.id
    WHERE f.tipo='venta'
    ORDER BY f.created_at DESC LIMIT 10
")->fetchAll();

// Ventas por día (últimos 7 días)
$ventasSemana = $db->query("
    SELECT DATE(fecha) as dia, COALESCE(SUM(total),0) as total
    FROM facturas WHERE fecha >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND estado='pagada' AND tipo='venta'
    GROUP BY dia ORDER BY dia
")->fetchAll();

// Top productos hoy
$topProductos = $db->prepare("
    SELECT p.nombre, SUM(fi.cantidad) as cant, SUM(fi.subtotal) as total
    FROM factura_items fi
    JOIN productos p ON fi.producto_id = p.id
    JOIN facturas f ON fi.factura_id = f.id
    WHERE DATE(f.fecha)=? AND f.estado='pagada'
    GROUP BY p.id ORDER BY total DESC LIMIT 5
");
$topProductos->execute([$hoy]);
$top5 = $topProductos->fetchAll();

// Turno activo
$turnoActivo = $db->query("SELECT t.*, u.nombre FROM turnos t JOIN usuarios u ON t.usuario_id=u.id WHERE t.estado='abierto' ORDER BY t.created_at DESC LIMIT 5")->fetchAll();

require_once '../../includes/header.php';
?>

<!-- Stats Row -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon purple"><i class="fas fa-dollar-sign"></i></div>
    <div>
      <div class="stat-value"><?= formatMoney($hoyData['total_ventas']) ?></div>
      <div class="stat-label">Ventas Hoy</div>
      <div class="stat-change <?= $cambio >= 0 ? 'up':'down' ?>">
        <i class="fas fa-arrow-<?= $cambio >= 0 ? 'up':'down' ?>"></i> <?= abs($cambio) ?>% vs ayer
      </div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="fas fa-receipt"></i></div>
    <div>
      <div class="stat-value"><?= number_format($hoyData['total_facturas']) ?></div>
      <div class="stat-label">Facturas Hoy</div>
      <div class="stat-change up"><?= formatMoney($hoyData['total_facturas'] > 0 ? $hoyData['total_ventas']/$hoyData['total_facturas'] : 0) ?> promedio</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon teal"><i class="fas fa-calendar-alt"></i></div>
    <div>
      <div class="stat-value"><?= formatMoney($mesData['total']) ?></div>
      <div class="stat-label">Ventas del Mes</div>
      <div class="stat-change up"><?= $mesData['cnt'] ?> facturas</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange"><i class="fas fa-utensils"></i></div>
    <div>
      <div class="stat-value"><?= $mesasStats['ocupada'] ?? 0 ?></div>
      <div class="stat-label">Mesas Ocupadas</div>
      <div class="stat-change"><?= $mesasStats['disponible'] ?? 0 ?> disponibles</div>
    </div>
  </div>
</div>

<!-- Charts + Top productos -->
<div class="grid-2 mb-4">
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fas fa-chart-line text-accent"></i> Ventas Últimos 7 Días</span>
    </div>
    <canvas id="chartVentas" height="200"></canvas>
  </div>
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fas fa-trophy text-accent"></i> Top Productos Hoy</span>
    </div>
    <?php if ($top5): ?>
    <div class="table-wrap">
      <table class="pos-table">
        <thead><tr><th>Producto</th><th>Cant.</th><th>Total</th></tr></thead>
        <tbody>
          <?php foreach($top5 as $p): ?>
          <tr>
            <td><?= htmlspecialchars($p['nombre']) ?></td>
            <td><?= number_format($p['cant'],0) ?></td>
            <td class="fw-bold text-accent"><?= formatMoney($p['total']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <p class="text-muted text-center" style="padding:40px 0">Sin ventas hoy</p>
    <?php endif; ?>
  </div>
</div>

<!-- Últimas Facturas + Stock Bajo -->
<div class="grid-2">
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fas fa-file-invoice-dollar text-accent"></i> Últimas Facturas</span>
      <a href="<?= BASE_URL ?>/modules/admin/facturas.php" class="btn btn-sm btn-secondary">Ver todas</a>
    </div>
    <div class="table-wrap">
      <table class="pos-table">
        <thead><tr><th>#</th><th>Cliente</th><th>Cajero</th><th>Total</th><th>Estado</th></tr></thead>
        <tbody>
          <?php foreach($ultFacturas as $f): ?>
          <tr>
            <td><span class="fw-bold text-accent"><?= htmlspecialchars($f['numero_factura']) ?></span></td>
            <td><?= htmlspecialchars($f['cliente_nombre'] ?? 'General') ?></td>
            <td><?= htmlspecialchars($f['cajero_nombre']) ?></td>
            <td class="fw-bold"><?= formatMoney($f['total']) ?></td>
            <td>
              <?php $badges = ['pagada'=>'success','abierta'=>'warning','anulada'=>'danger']; ?>
              <span class="badge badge-<?= $badges[$f['estado']] ?? 'muted' ?>"><?= ucfirst($f['estado']) ?></span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fas fa-exclamation-triangle text-warning"></i> Stock Bajo</span>
      <a href="<?= BASE_URL ?>/modules/admin/inventario.php" class="btn btn-sm btn-secondary">Inventario</a>
    </div>
    <?php if ($stockBajo): ?>
    <div class="table-wrap">
      <table class="pos-table">
        <thead><tr><th>Producto</th><th>Stock</th><th>Mínimo</th><th>Estado</th></tr></thead>
        <tbody>
          <?php foreach($stockBajo as $s): ?>
          <tr>
            <td><?= htmlspecialchars($s['nombre']) ?></td>
            <td class="fw-bold"><?= number_format($s['stock_actual'],1) ?></td>
            <td class="text-muted"><?= number_format($s['stock_minimo'],1) ?></td>
            <td><span class="badge badge-danger">Stock Bajo</span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <p class="text-muted text-center" style="padding:40px 0"><i class="fas fa-check-circle" style="color:var(--success)"></i>&nbsp; Todo el stock está en orden</p>
    <?php endif; ?>

    <?php if ($turnoActivo): ?>
    <div class="card-header mt-4">
      <span class="card-title"><i class="fas fa-clock text-teal"></i> Turnos Activos</span>
    </div>
    <?php foreach($turnoActivo as $t): ?>
    <div style="padding:8px 0;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border)">
      <span><i class="fas fa-user text-muted"></i> <?= htmlspecialchars($t['nombre']) ?></span>
      <span class="text-muted fs-sm"><?= date('H:i', strtotime($t['fecha_apertura'])) ?></span>
      <span class="badge badge-success">Activo</span>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<script>
// Chart.js se carga en el footer (después de este bloque); esperar a 'load' para
// que la librería ya exista al instanciar el gráfico.
window.addEventListener('load', function () {
const labels = <?= json_encode(array_column($ventasSemana, 'dia')) ?>;
const data   = <?= json_encode(array_map(fn($r)=>(float)$r['total'], $ventasSemana)) ?>;
const ctx = document.getElementById('chartVentas').getContext('2d');
new Chart(ctx, {
  type:'line',
  data:{
    labels: labels.map(d => new Date(d+'T00:00:00').toLocaleDateString('es-CO',{weekday:'short',day:'numeric'})),
    datasets:[{
      label:'Ventas',
      data,
      borderColor:'#6c63ff',
      backgroundColor:'rgba(108,99,255,.1)',
      fill:true,
      tension:.4,
      pointBackgroundColor:'#6c63ff',
      pointRadius:4
    }]
  },
  options:{
    responsive:true,
    plugins:{ legend:{display:false} },
    scales:{
      x:{ grid:{color:'rgba(255,255,255,.05)'}, ticks:{color:'#7b82a0'} },
      y:{ grid:{color:'rgba(255,255,255,.05)'}, ticks:{color:'#7b82a0', callback:v=>'$'+v.toLocaleString('es-CO')} }
    }
  }
});
});
</script>

<?php require_once '../../includes/footer.php'; ?>
