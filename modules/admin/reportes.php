<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireRole(['Administrador']);

$db = getDB();
$pageTitle = 'Reportes';
$activeMenu = 'reportes';

$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? date('Y-m-d');

// Ventas por día
$ventasDia = $db->prepare("
    SELECT DATE(fecha) as dia, COUNT(*) as facturas, COALESCE(SUM(total),0) as total,
           COALESCE(SUM(descuento_valor),0) as descuentos
    FROM facturas WHERE DATE(fecha) BETWEEN ? AND ? AND estado='pagada' AND tipo='venta'
    GROUP BY dia ORDER BY dia
");
$ventasDia->execute([$desde,$hasta]);
$ventasDia = $ventasDia->fetchAll();

// Ventas por método de pago
$ventasPago = $db->prepare("
    SELECT metodo_pago, COUNT(*) as cnt, COALESCE(SUM(total),0) as total
    FROM facturas WHERE DATE(fecha) BETWEEN ? AND ? AND estado='pagada' AND tipo='venta'
    GROUP BY metodo_pago
");
$ventasPago->execute([$desde,$hasta]);
$ventasPago = $ventasPago->fetchAll();

// Top 10 productos
$topProd = $db->prepare("
    SELECT p.nombre, SUM(fi.cantidad) as cantidad, SUM(fi.subtotal) as total
    FROM factura_items fi
    JOIN productos p ON fi.producto_id=p.id
    JOIN facturas f ON fi.factura_id=f.id
    WHERE DATE(f.fecha) BETWEEN ? AND ? AND f.estado='pagada'
    GROUP BY p.id ORDER BY total DESC LIMIT 10
");
$topProd->execute([$desde,$hasta]);
$topProd = $topProd->fetchAll();

// Ventas por cajero
$ventasCajero = $db->prepare("
    SELECT u.nombre, u.apellido, COUNT(f.id) as facturas, COALESCE(SUM(f.total),0) as total
    FROM facturas f JOIN usuarios u ON f.cajero_id=u.id
    WHERE DATE(f.fecha) BETWEEN ? AND ? AND f.estado='pagada' AND f.tipo='venta'
    GROUP BY u.id ORDER BY total DESC
");
$ventasCajero->execute([$desde,$hasta]);
$ventasCajero = $ventasCajero->fetchAll();

// Ventas por categoría
$ventasCat = $db->prepare("
    SELECT c.nombre, SUM(fi.cantidad) as cantidad, SUM(fi.subtotal) as total
    FROM factura_items fi
    JOIN productos p ON fi.producto_id=p.id
    JOIN categorias c ON p.categoria_id=c.id
    JOIN facturas f ON fi.factura_id=f.id
    WHERE DATE(f.fecha) BETWEEN ? AND ? AND f.estado='pagada'
    GROUP BY c.id ORDER BY total DESC
");
$ventasCat->execute([$desde,$hasta]);
$ventasCat = $ventasCat->fetchAll();

// Resumen general
$resumen = $db->prepare("
    SELECT COUNT(*) as total_fact, COALESCE(SUM(total),0) as total_ventas,
           COALESCE(SUM(descuento_valor),0) as total_desc,
           COALESCE(SUM(impuesto_valor),0) as total_iva,
           COALESCE(SUM(propina),0) as total_propina
    FROM facturas WHERE DATE(fecha) BETWEEN ? AND ? AND estado='pagada' AND tipo='venta'
");
$resumen->execute([$desde,$hasta]);
$resumen = $resumen->fetch();

require_once '../../includes/header.php';
?>

<!-- Filtro de fechas -->
<div class="card mb-4 no-print">
  <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
    <div class="form-group" style="margin:0">
      <label class="form-label">Desde</label>
      <input type="date" name="desde" class="form-control" value="<?= $desde ?>">
    </div>
    <div class="form-group" style="margin:0">
      <label class="form-label">Hasta</label>
      <input type="date" name="hasta" class="form-control" value="<?= $hasta ?>">
    </div>
    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Generar Reporte</button>
    <button type="button" class="btn btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> Imprimir</button>
    <a href="../../ajax/export_facturas.php?desde=<?= $desde ?>&hasta=<?= $hasta ?>" class="btn btn-secondary"><i class="fas fa-file-csv"></i> Exportar CSV</a>
  </form>
</div>

<!-- Resumen -->
<div class="stats-grid mb-4">
  <div class="stat-card">
    <div class="stat-icon purple"><i class="fas fa-dollar-sign"></i></div>
    <div><div class="stat-value"><?= formatMoney($resumen['total_ventas']) ?></div><div class="stat-label">Total Ventas</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="fas fa-receipt"></i></div>
    <div><div class="stat-value"><?= number_format($resumen['total_fact']) ?></div><div class="stat-label">Facturas</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon teal"><i class="fas fa-chart-bar"></i></div>
    <div><div class="stat-value"><?= $resumen['total_fact']>0 ? formatMoney($resumen['total_ventas']/$resumen['total_fact']) : '$0' ?></div><div class="stat-label">Ticket Promedio</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon red"><i class="fas fa-tags"></i></div>
    <div><div class="stat-value"><?= formatMoney($resumen['total_desc']) ?></div><div class="stat-label">Total Descuentos</div></div>
  </div>
</div>

<!-- Gráfica ventas por día -->
<div class="card mb-4">
  <div class="card-header"><span class="card-title"><i class="fas fa-chart-area text-accent"></i> Ventas Diarias</span></div>
  <canvas id="chartDias" height="100"></canvas>
</div>

<div class="grid-2 mb-4">
  <!-- Por método de pago -->
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fas fa-credit-card text-accent"></i> Por Método de Pago</span></div>
    <canvas id="chartPago" height="220"></canvas>
    <div class="table-wrap mt-3">
      <table class="pos-table">
        <thead><tr><th>Método</th><th>Facturas</th><th>Total</th></tr></thead>
        <tbody>
          <?php foreach($ventasPago as $vp): ?>
          <tr>
            <td class="fw-bold"><?= ucfirst($vp['metodo_pago']) ?></td>
            <td><?= $vp['cnt'] ?></td>
            <td class="text-accent fw-bold"><?= formatMoney($vp['total']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Por cajero -->
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fas fa-user-tie text-accent"></i> Por Cajero</span></div>
    <div class="table-wrap">
      <table class="pos-table">
        <thead><tr><th>Cajero</th><th>Facturas</th><th>Total</th></tr></thead>
        <tbody>
          <?php foreach($ventasCajero as $vc): ?>
          <tr>
            <td class="fw-bold"><?= htmlspecialchars($vc['nombre'].' '.$vc['apellido']) ?></td>
            <td><span class="badge badge-info"><?= $vc['facturas'] ?></span></td>
            <td class="text-accent fw-bold"><?= formatMoney($vc['total']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="grid-2">
  <!-- Top productos -->
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fas fa-trophy text-accent"></i> Top 10 Productos</span></div>
    <div class="table-wrap">
      <table class="pos-table">
        <thead><tr><th>#</th><th>Producto</th><th>Cantidad</th><th>Total</th></tr></thead>
        <tbody>
          <?php foreach($topProd as $i => $tp): ?>
          <tr>
            <td class="fw-bold text-accent"><?= $i+1 ?></td>
            <td><?= htmlspecialchars($tp['nombre']) ?></td>
            <td><?= number_format($tp['cantidad'],1) ?></td>
            <td class="fw-bold"><?= formatMoney($tp['total']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Por categoría -->
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fas fa-layer-group text-accent"></i> Por Categoría</span></div>
    <canvas id="chartCat" height="200"></canvas>
    <div class="table-wrap mt-3">
      <table class="pos-table">
        <thead><tr><th>Categoría</th><th>Cantidad</th><th>Total</th></tr></thead>
        <tbody>
          <?php foreach($ventasCat as $vc): ?>
          <tr>
            <td class="fw-bold"><?= htmlspecialchars($vc['nombre']) ?></td>
            <td><?= number_format($vc['cantidad'],1) ?></td>
            <td class="text-accent fw-bold"><?= formatMoney($vc['total']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
// Chart Ventas Diarias
new Chart(document.getElementById('chartDias').getContext('2d'), {
  type:'bar',
  data:{
    labels: <?= json_encode(array_map(fn($r)=>date('d/m',strtotime($r['dia'])), $ventasDia)) ?>,
    datasets:[{
      label:'Ventas',
      data: <?= json_encode(array_map(fn($r)=>(float)$r['total'], $ventasDia)) ?>,
      backgroundColor:'rgba(108,99,255,.7)',
      borderColor:'#6c63ff', borderWidth:2, borderRadius:6
    }]
  },
  options:{responsive:true,plugins:{legend:{display:false}},scales:{
    x:{grid:{color:'rgba(255,255,255,.05)'},ticks:{color:'#7b82a0'}},
    y:{grid:{color:'rgba(255,255,255,.05)'},ticks:{color:'#7b82a0',callback:v=>'$'+v.toLocaleString('es-CO')}}
  }}
});

// Chart Métodos de Pago
new Chart(document.getElementById('chartPago').getContext('2d'), {
  type:'doughnut',
  data:{
    labels: <?= json_encode(array_map(fn($r)=>ucfirst($r['metodo_pago']), $ventasPago)) ?>,
    datasets:[{data: <?= json_encode(array_map(fn($r)=>(float)$r['total'], $ventasPago)) ?>,
      backgroundColor:['#6c63ff','#2ecc71','#3498db','#ff6584'], borderWidth:0}]
  },
  options:{responsive:true,plugins:{legend:{position:'bottom',labels:{color:'#e8eaf0'}}}}
});

// Chart Categorías
new Chart(document.getElementById('chartCat').getContext('2d'), {
  type:'doughnut',
  data:{
    labels: <?= json_encode(array_map(fn($r)=>$r['nombre'], $ventasCat)) ?>,
    datasets:[{data: <?= json_encode(array_map(fn($r)=>(float)$r['total'], $ventasCat)) ?>,
      backgroundColor:['#e74c3c','#e67e22','#3498db','#9b59b6','#2ecc71'], borderWidth:0}]
  },
  options:{responsive:true,plugins:{legend:{position:'bottom',labels:{color:'#e8eaf0'}}}}
});
</script>

<?php require_once '../../includes/footer.php'; ?>
