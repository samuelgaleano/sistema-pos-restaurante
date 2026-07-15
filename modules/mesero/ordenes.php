<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireRole(['Administrador','Cajero','Mesero']);

$db = getDB();
$pageTitle = 'Mis Órdenes';
$activeMenu = 'ordenes';

$meseroId = hasRole('Administrador') ? null : $_SESSION['user_id'];

$sql = "
    SELECT o.*, m.numero as mesa_num, m.nombre as mesa_nombre,
           u.nombre as mesero_n,
           COUNT(oi.id) as num_items,
           COALESCE(SUM(oi.precio_unitario * oi.cantidad),0) as subtotal
    FROM ordenes o
    JOIN mesas m ON o.mesa_id=m.id
    JOIN usuarios u ON o.mesero_id=u.id
    LEFT JOIN orden_items oi ON oi.orden_id=o.id AND oi.estado != 'cancelado'
    WHERE o.estado IN ('abierta','en_proceso','lista')
";
$params = [];
if ($meseroId) { $sql .= " AND o.mesero_id=?"; $params[] = $meseroId; }
$sql .= " GROUP BY o.id ORDER BY o.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$ordenes = $stmt->fetchAll();

require_once '../../includes/header.php';
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
  <span class="text-muted"><?= count($ordenes) ?> órdenes activas</span>
  <a href="mesas.php" class="btn btn-primary"><i class="fas fa-border-all"></i> Ver Mesas</a>
</div>

<?php if($ordenes): ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px">
  <?php foreach($ordenes as $o): ?>
  <div class="card" style="border-left:4px solid <?= $o['estado']==='lista'?'var(--success)':($o['estado']==='en_proceso'?'var(--warning)':'var(--accent)') ?>">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <div>
        <div class="fw-bold" style="font-size:16px">Mesa <?= htmlspecialchars($o['mesa_nombre']?:$o['mesa_num']) ?></div>
        <div class="text-muted fs-sm">Orden #<?= $o['id'] ?> · <?= date('H:i', strtotime($o['created_at'])) ?></div>
      </div>
      <?php $sc=['abierta'=>'purple','en_proceso'=>'warning','lista'=>'success']; ?>
      <span class="badge badge-<?= $sc[$o['estado']]??'muted' ?>"><?= ucfirst(str_replace('_',' ',$o['estado'])) ?></span>
    </div>
    <div style="display:flex;justify-content:space-between;margin-bottom:12px">
      <span class="text-muted fs-sm"><i class="fas fa-users"></i> <?= $o['num_personas'] ?> personas · <?= $o['num_items'] ?> items</span>
      <span class="fw-bold text-accent"><?= formatMoney($o['subtotal']) ?></span>
    </div>
    <?php if(!hasRole('Administrador')): ?>
    <div class="text-muted fs-sm mb-2"><i class="fas fa-user"></i> <?= htmlspecialchars($o['mesero_n']) ?></div>
    <?php endif; ?>
    <a href="orden.php?id=<?= $o['id'] ?>" class="btn btn-primary btn-block btn-sm">
      <i class="fas fa-list-alt"></i> Ver Comanda
    </a>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div style="text-align:center;padding:80px 20px">
  <div style="font-size:56px;margin-bottom:16px">📋</div>
  <h3 style="margin-bottom:8px">Sin órdenes activas</h3>
  <p class="text-muted mb-4">Las órdenes abiertas aparecerán aquí.</p>
  <a href="mesas.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nueva Orden desde Mesas</a>
</div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
