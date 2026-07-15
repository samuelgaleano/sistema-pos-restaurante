<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireRole(['Administrador','Cajero']);

$db = getDB();
$pageTitle = 'Turnos de Caja';
$activeMenu = 'turnos';

$turnoActivo = getTurnoActivo();

$turnos = $db->query("
    SELECT t.*, u.nombre, u.apellido, 
           (SELECT COUNT(*) FROM facturas f WHERE f.turno_id=t.id AND f.estado='pagada') as num_facturas
    FROM turnos t
    JOIN usuarios u ON t.usuario_id = u.id
    ORDER BY t.created_at DESC LIMIT 50
")->fetchAll();

require_once '../../includes/header.php';
?>

<!-- Turno Activo Card -->
<?php if($turnoActivo): ?>
<div class="card mb-4" style="border-color:var(--success)">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <div>
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
        <span class="badge badge-success" style="font-size:13px"><i class="fas fa-circle"></i> TURNO ACTIVO</span>
        <span class="fw-bold">Turno #<?= $turnoActivo['id'] ?></span>
      </div>
      <div class="text-muted fs-sm">Apertura: <?= date('d/m/Y H:i', strtotime($turnoActivo['fecha_apertura'])) ?> 
        · Monto inicial: <?= formatMoney($turnoActivo['monto_inicial']) ?>
      </div>
    </div>
    <div style="display:flex;gap:10px">
      <div style="text-align:center">
        <div style="font-size:20px;font-weight:800;color:var(--success)"><?= formatMoney($turnoActivo['total_ventas']) ?></div>
        <div class="text-muted fs-sm">Ventas Acumuladas</div>
      </div>
      <button class="btn btn-danger" onclick="openModal('modalCerrar')"><i class="fas fa-lock"></i> Cerrar Turno</button>
    </div>
  </div>
</div>
<?php else: ?>
<div class="card mb-4" style="border-color:var(--border)">
  <div style="display:flex;align-items:center;justify-content:space-between">
    <div>
      <span class="badge badge-danger mb-2"><i class="fas fa-circle"></i> SIN TURNO ACTIVO</span>
      <p class="text-muted">No hay ningún turno de caja abierto actualmente.</p>
    </div>
    <button class="btn btn-success" onclick="openModal('modalAbrir')"><i class="fas fa-lock-open"></i> Abrir Turno</button>
  </div>
</div>
<?php endif; ?>

<!-- Tabla Turnos -->
<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="fas fa-history text-accent"></i> Historial de Turnos</span>
  </div>
  <div class="table-wrap">
    <table class="pos-table">
      <thead>
        <tr><th>#</th><th>Cajero</th><th>Apertura</th><th>Cierre</th><th>M. Inicial</th><th>Ventas</th><th>Efectivo</th><th>Tarjeta</th><th>Facturas</th><th>Diferencia</th><th>Estado</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach($turnos as $t): ?>
        <tr>
          <td class="fw-bold text-accent">#<?= $t['id'] ?></td>
          <td><?= htmlspecialchars($t['nombre'].' '.$t['apellido']) ?></td>
          <td class="fs-sm"><?= date('d/m H:i', strtotime($t['fecha_apertura'])) ?></td>
          <td class="fs-sm text-muted"><?= $t['fecha_cierre'] ? date('d/m H:i', strtotime($t['fecha_cierre'])) : '—' ?></td>
          <td><?= formatMoney($t['monto_inicial']) ?></td>
          <td class="fw-bold text-success"><?= formatMoney($t['total_ventas']) ?></td>
          <td><?= formatMoney($t['total_efectivo']) ?></td>
          <td><?= formatMoney($t['total_tarjeta']) ?></td>
          <td><span class="badge badge-info"><?= $t['num_facturas'] ?></span></td>
          <td class="<?= ($t['diferencia'] ?? 0) < 0 ? 'text-danger' : 'text-success' ?>">
            <?= $t['diferencia'] !== null ? formatMoney($t['diferencia']) : '—' ?>
          </td>
          <td>
            <?php $bs = ['abierto'=>'success','cerrado'=>'muted','revisado'=>'info']; ?>
            <span class="badge badge-<?= $bs[$t['estado']] ?>"><?= ucfirst($t['estado']) ?></span>
          </td>
          <td>
            <button class="btn btn-sm btn-secondary" onclick="verTurno(<?= $t['id'] ?>)">
              <i class="fas fa-eye"></i>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Abrir Turno -->
<div class="modal-overlay" id="modalAbrir">
  <div class="modal-box" style="max-width:400px">
    <div class="modal-header"><span class="modal-title">Abrir Turno de Caja</span><button class="modal-close" onclick="closeModal('modalAbrir')"><i class="fas fa-times"></i></button></div>
    <form method="POST" action="../../ajax/turnos.php">
      <input type="hidden" name="action" value="abrir">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Monto Inicial en Caja ($)</label>
          <input type="number" name="monto_inicial" class="form-control" placeholder="0.00" min="0" step="0.01" value="0">
        </div>
        <div class="form-group">
          <label class="form-label">Observaciones</label>
          <textarea name="notas" class="form-control" placeholder="Notas del turno..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalAbrir')">Cancelar</button>
        <button type="submit" class="btn btn-success"><i class="fas fa-lock-open"></i> Abrir Turno</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Cerrar Turno -->
<?php if($turnoActivo): ?>
<div class="modal-overlay" id="modalCerrar">
  <div class="modal-box" style="max-width:460px">
    <div class="modal-header"><span class="modal-title">Cierre de Turno #<?= $turnoActivo['id'] ?></span><button class="modal-close" onclick="closeModal('modalCerrar')"><i class="fas fa-times"></i></button></div>
    <form method="POST" action="../../ajax/turnos.php">
      <input type="hidden" name="action" value="cerrar">
      <input type="hidden" name="turno_id" value="<?= $turnoActivo['id'] ?>">
      <div class="modal-body">
        <div style="background:var(--surface);border-radius:10px;padding:16px;margin-bottom:16px">
          <div class="total-row mb-3"><span>Ventas Totales:</span><span class="fw-bold text-success"><?= formatMoney($turnoActivo['total_ventas']) ?></span></div>
          <div class="total-row mb-3"><span>Efectivo:</span><span><?= formatMoney($turnoActivo['total_efectivo']) ?></span></div>
          <div class="total-row mb-3"><span>Tarjeta:</span><span><?= formatMoney($turnoActivo['total_tarjeta']) ?></span></div>
          <div class="total-row mb-3"><span>Transferencia:</span><span><?= formatMoney($turnoActivo['total_transferencia']) ?></span></div>
          <div class="total-row"><span>Monto Inicial:</span><span><?= formatMoney($turnoActivo['monto_inicial']) ?></span></div>
        </div>
        <div class="form-group">
          <label class="form-label">Monto Real en Caja al Cierre ($)</label>
          <input type="number" name="monto_final" id="montoFinal" class="form-control" 
                 placeholder="Cuente el dinero físico" step="0.01" min="0"
                 oninput="calcDif(<?= $turnoActivo['total_efectivo'] + $turnoActivo['monto_inicial'] ?>)" required>
        </div>
        <div style="display:flex;justify-content:space-between;padding:12px;background:var(--surface);border-radius:8px">
          <span>Diferencia esperada:</span>
          <span id="difVal" style="font-weight:800">—</span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalCerrar')">Cancelar</button>
        <button type="submit" class="btn btn-danger" onclick="return confirm('¿Cerrar el turno definitivamente?')"><i class="fas fa-lock"></i> Cerrar Turno</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
function calcDif(esperado) {
  const real = parseFloat(document.getElementById('montoFinal').value)||0;
  const dif = real - esperado;
  const el = document.getElementById('difVal');
  el.textContent = (dif >= 0 ? '+' : '') + '$ ' + Math.round(dif).toLocaleString('es-CO');
  el.style.color = dif >= 0 ? 'var(--success)' : 'var(--danger)';
}
function verTurno(id) {
  window.open('<?= BASE_URL ?>/modules/admin/reporte_turno.php?id=' + id, '_blank');
}
</script>

<?php require_once '../../includes/footer.php'; ?>
