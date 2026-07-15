<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireRole(['Administrador','Cajero']);

$db = getDB();
$pageTitle = 'Mi Turno';
$activeMenu = 'turno';

$turnoActivo = getTurnoActivo();

// Historial turnos del cajero
$misTurnos = $db->prepare("
    SELECT t.*, (SELECT COUNT(*) FROM facturas f WHERE f.turno_id=t.id AND f.estado='pagada') as num_fact
    FROM turnos t WHERE t.usuario_id=? ORDER BY t.created_at DESC LIMIT 20
");
$misTurnos->execute([$_SESSION['user_id']]);
$misTurnos = $misTurnos->fetchAll();

$msg = '';
if (isset($_GET['ok'])) $msg = $_GET['ok'] === 'turno_abierto' ? 'Turno abierto correctamente.' : 'Turno cerrado.';

require_once '../../includes/header.php';
?>

<?php if($msg): ?><div class="alert alert-success alert-auto"><i class="fas fa-check"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>

<?php if($turnoActivo): ?>
<!-- Turno activo -->
<div class="card mb-4" style="border-color:var(--success)">
  <div class="card-header">
    <span class="card-title"><i class="fas fa-circle text-success"></i> Turno #<?= $turnoActivo['id'] ?> — Activo</span>
    <button class="btn btn-danger" onclick="openModal('modalCerrar')"><i class="fas fa-lock"></i> Cerrar Turno</button>
  </div>
  <div class="stats-grid" style="margin-bottom:0">
    <div class="stat-card">
      <div class="stat-icon green"><i class="fas fa-dollar-sign"></i></div>
      <div><div class="stat-value"><?= formatMoney($turnoActivo['total_ventas']) ?></div><div class="stat-label">Total Vendido</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon purple"><i class="fas fa-money-bill-wave"></i></div>
      <div><div class="stat-value"><?= formatMoney($turnoActivo['total_efectivo']) ?></div><div class="stat-label">Efectivo</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon teal"><i class="fas fa-credit-card"></i></div>
      <div><div class="stat-value"><?= formatMoney($turnoActivo['total_tarjeta']) ?></div><div class="stat-label">Tarjeta</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
      <div><div class="stat-value"><?= date('H:i', strtotime($turnoActivo['fecha_apertura'])) ?></div><div class="stat-label">Hora Apertura</div></div>
    </div>
  </div>
</div>

<!-- Facturas del turno actual -->
<?php
$factsTurno = $db->prepare("SELECT f.*, c.nombre as cliente FROM facturas f LEFT JOIN clientes c ON f.cliente_id=c.id WHERE f.turno_id=? ORDER BY f.created_at DESC");
$factsTurno->execute([$turnoActivo['id']]);
$factsTurno = $factsTurno->fetchAll();
?>
<div class="card">
  <div class="card-header"><span class="card-title">Facturas de este Turno (<?= count($factsTurno) ?>)</span></div>
  <div class="table-wrap">
    <table class="pos-table">
      <thead><tr><th>#Factura</th><th>Hora</th><th>Cliente</th><th>Total</th><th>Método</th><th>Estado</th><th></th></tr></thead>
      <tbody>
        <?php foreach($factsTurno as $f): ?>
        <tr>
          <td class="fw-bold text-accent"><?= htmlspecialchars($f['numero_factura']) ?></td>
          <td class="fs-sm text-muted"><?= date('H:i', strtotime($f['created_at'])) ?></td>
          <td><?= htmlspecialchars($f['cliente']??'General') ?></td>
          <td class="fw-bold"><?= formatMoney($f['total']) ?></td>
          <td><span class="badge badge-info"><?= ucfirst($f['metodo_pago']) ?></span></td>
          <td><?php $bs=['pagada'=>'success','anulada'=>'danger','abierta'=>'warning']; ?>
            <span class="badge badge-<?= $bs[$f['estado']]??'muted' ?>"><?= ucfirst($f['estado']) ?></span></td>
          <td><a href="imprimir.php?id=<?= $f['id'] ?>" target="_blank" class="btn btn-sm btn-secondary"><i class="fas fa-print"></i></a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php else: ?>
<div style="text-align:center;padding:80px 20px">
  <div style="font-size:64px;margin-bottom:16px">🔒</div>
  <h2 style="margin-bottom:8px">No tienes turno activo</h2>
  <p class="text-muted mb-4">Abre un turno de caja para comenzar a vender.</p>
  <button class="btn btn-success btn-lg" onclick="openModal('modalAbrir')"><i class="fas fa-lock-open"></i> Abrir Turno</button>
</div>
<?php endif; ?>

<!-- Historial -->
<div class="card mt-4">
  <div class="card-header"><span class="card-title"><i class="fas fa-history text-accent"></i> Mis Turnos Anteriores</span></div>
  <div class="table-wrap">
    <table class="pos-table">
      <thead><tr><th>#</th><th>Apertura</th><th>Cierre</th><th>M.Inicial</th><th>Ventas</th><th>Facturas</th><th>Diferencia</th><th>Estado</th></tr></thead>
      <tbody>
        <?php foreach($misTurnos as $t): ?>
        <tr>
          <td class="fw-bold text-accent">#<?= $t['id'] ?></td>
          <td class="fs-sm"><?= date('d/m H:i', strtotime($t['fecha_apertura'])) ?></td>
          <td class="fs-sm text-muted"><?= $t['fecha_cierre'] ? date('d/m H:i', strtotime($t['fecha_cierre'])) : '—' ?></td>
          <td><?= formatMoney($t['monto_inicial']) ?></td>
          <td class="fw-bold text-success"><?= formatMoney($t['total_ventas']) ?></td>
          <td><span class="badge badge-info"><?= $t['num_fact'] ?></span></td>
          <td class="<?= ($t['diferencia']??0) < 0 ? 'text-danger':'text-success' ?>"><?= $t['diferencia']!==null ? formatMoney($t['diferencia']):'—' ?></td>
          <td><?php $bs=['abierto'=>'success','cerrado'=>'muted','revisado'=>'info']; ?>
            <span class="badge badge-<?= $bs[$t['estado']] ?>"><?= ucfirst($t['estado']) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Abrir -->
<div class="modal-overlay" id="modalAbrir">
  <div class="modal-box" style="max-width:400px">
    <div class="modal-header"><span class="modal-title">Abrir Turno</span><button class="modal-close" onclick="closeModal('modalAbrir')"><i class="fas fa-times"></i></button></div>
    <form method="POST" action="../../ajax/turnos.php">
      <input type="hidden" name="action" value="abrir">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Monto Inicial en Caja ($)</label>
          <input type="number" name="monto_inicial" class="form-control" value="0" min="0" step="0.01">
        </div>
        <div class="form-group">
          <label class="form-label">Notas</label>
          <textarea name="notas" class="form-control" placeholder="Observaciones..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalAbrir')">Cancelar</button>
        <button type="submit" class="btn btn-success"><i class="fas fa-lock-open"></i> Abrir</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Cerrar -->
<?php if($turnoActivo): ?>
<div class="modal-overlay" id="modalCerrar">
  <div class="modal-box" style="max-width:420px">
    <div class="modal-header"><span class="modal-title">Cierre de Caja</span><button class="modal-close" onclick="closeModal('modalCerrar')"><i class="fas fa-times"></i></button></div>
    <form method="POST" action="../../ajax/turnos.php">
      <input type="hidden" name="action" value="cerrar">
      <input type="hidden" name="turno_id" value="<?= $turnoActivo['id'] ?>">
      <div class="modal-body">
        <div style="background:var(--surface);border-radius:10px;padding:16px;margin-bottom:16px">
          <div class="total-row mb-2"><span>Ventas Totales:</span><span class="fw-bold text-success"><?= formatMoney($turnoActivo['total_ventas']) ?></span></div>
          <div class="total-row mb-2"><span>Efectivo:</span><span><?= formatMoney($turnoActivo['total_efectivo']) ?></span></div>
          <div class="total-row"><span>Monto Inicial:</span><span><?= formatMoney($turnoActivo['monto_inicial']) ?></span></div>
        </div>
        <div class="form-group">
          <label class="form-label">Dinero Real en Caja</label>
          <input type="number" name="monto_final" id="mf" class="form-control" min="0" step="0.01" required placeholder="Cuente el efectivo físico" oninput="calcDif(<?= $turnoActivo['total_efectivo']+$turnoActivo['monto_inicial'] ?>)">
        </div>
        <div style="display:flex;justify-content:space-between;padding:12px;background:var(--surface);border-radius:8px">
          <span>Diferencia:</span><span id="difV" style="font-weight:800">—</span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalCerrar')">Cancelar</button>
        <button type="submit" class="btn btn-danger" onclick="return confirm('¿Cerrar turno definitivamente?')"><i class="fas fa-lock"></i> Cerrar</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
function calcDif(esp){
  const r=parseFloat(document.getElementById('mf').value)||0;
  const d=r-esp;
  const el=document.getElementById('difV');
  el.textContent=(d>=0?'+':'')+'$ '+Math.round(d).toLocaleString('es-CO');
  el.style.color=d>=0?'var(--success)':'var(--danger)';
}
</script>

<?php require_once '../../includes/footer.php'; ?>
