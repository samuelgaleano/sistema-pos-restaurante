<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireRole(['Administrador','Cajero','Mesero']);

$db = getDB();
$pageTitle = 'Control de Mesas';
$activeMenu = 'mesas';

$zonas = $db->query("SELECT * FROM zonas WHERE activo=1 ORDER BY nombre")->fetchAll();
$mesas = $db->query("
    SELECT m.*, z.nombre as zona_nombre,
           o.id as orden_id, o.num_personas,
           u.nombre as mesero_nombre,
           (SELECT COUNT(*) FROM orden_items oi WHERE oi.orden_id=o.id AND oi.estado != 'cancelado') as items_count
    FROM mesas m
    LEFT JOIN zonas z ON m.zona_id = z.id
    LEFT JOIN ordenes o ON o.mesa_id = m.id AND o.estado IN ('abierta','en_proceso','lista')
    LEFT JOIN usuarios u ON o.mesero_id = u.id
    WHERE m.activo=1
    ORDER BY m.zona_id, CAST(m.numero AS UNSIGNED), m.numero
")->fetchAll();

require_once '../../includes/header.php';
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <button class="btn btn-secondary active-filter" data-filter="all" onclick="filterMesas('all',this)">Todas</button>
    <button class="btn btn-secondary" data-filter="disponible" onclick="filterMesas('disponible',this)">
      <span style="width:8px;height:8px;background:var(--success);border-radius:50%;display:inline-block"></span> Disponibles
    </button>
    <button class="btn btn-secondary" data-filter="ocupada" onclick="filterMesas('ocupada',this)">
      <span style="width:8px;height:8px;background:var(--danger);border-radius:50%;display:inline-block"></span> Ocupadas
    </button>
    <button class="btn btn-secondary" data-filter="reservada" onclick="filterMesas('reservada',this)">
      <span style="width:8px;height:8px;background:var(--warning);border-radius:50%;display:inline-block"></span> Reservadas
    </button>
  </div>
  <button class="btn btn-primary" onclick="location.reload()"><i class="fas fa-sync-alt"></i> Actualizar</button>
</div>

<?php foreach($zonas as $zona): ?>
<?php $mesasZona = array_filter($mesas, fn($m) => $m['zona_id'] == $zona['id']); ?>
<?php if(count($mesasZona)): ?>
<h3 style="font-size:15px;font-weight:700;margin-bottom:12px;color:var(--muted)">
  <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($zona['nombre']) ?>
</h3>
<div class="mesas-grid mb-4">
  <?php foreach($mesasZona as $mesa): ?>
  <div class="mesa-card <?= $mesa['estado'] ?>" 
       data-estado="<?= $mesa['estado'] ?>"
       onclick="clickMesa(<?= $mesa['id'] ?>, '<?= $mesa['estado'] ?>', <?= $mesa['orden_id'] ?: 'null' ?>)">
    <div class="mesa-num"><?= htmlspecialchars($mesa['numero']) ?></div>
    <div class="mesa-name"><?= htmlspecialchars($mesa['nombre'] ?: 'Mesa '.$mesa['numero']) ?></div>
    <?php if($mesa['estado'] === 'ocupada'): ?>
      <div style="font-size:11px;color:var(--muted);margin-bottom:6px">
        <i class="fas fa-user"></i> <?= htmlspecialchars($mesa['mesero_nombre'] ?? '-') ?>
        · <?= $mesa['items_count'] ?? 0 ?> items
      </div>
    <?php endif; ?>
    <div class="mesa-status">
      <?= ['disponible'=>'● Disponible','ocupada'=>'● Ocupada','reservada'=>'● Reservada','mantenimiento'=>'● Mantenimiento'][$mesa['estado']] ?>
    </div>
    <div style="font-size:11px;color:var(--muted);margin-top:4px"><i class="fas fa-users"></i> Cap. <?= $mesa['capacidad'] ?></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endforeach; ?>

<!-- Modal Acción Mesa -->
<div class="modal-overlay" id="modalMesa">
  <div class="modal-box" style="max-width:380px">
    <div class="modal-header">
      <span class="modal-title" id="modalMesaTitle">Mesa</span>
      <button class="modal-close" onclick="closeModal('modalMesa')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body" id="modalMesaBody"></div>
  </div>
</div>

<!-- Modal Nueva Orden -->
<div class="modal-overlay" id="modalOrden">
  <div class="modal-box">
    <div class="modal-header">
      <span class="modal-title" id="ordenTitle">Nueva Orden</span>
      <button class="modal-close" onclick="closeModal('modalOrden')"><i class="fas fa-times"></i></button>
    </div>
    <form id="formOrden" onsubmit="crearOrden(event)">
      <div class="modal-body">
        <input type="hidden" id="ordenMesaId">
        <div class="form-group">
          <label class="form-label">Número de Personas</label>
          <input type="number" id="numPersonas" class="form-control" value="2" min="1" max="20">
        </div>
        <div class="form-group">
          <label class="form-label">Notas de la Mesa</label>
          <textarea id="notasMesa" class="form-control" placeholder="Alergias, preferencias..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalOrden')">Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Abrir Mesa</button>
      </div>
    </form>
  </div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';
const ROLE = '<?= strtolower(getUserRole()) ?>';

function filterMesas(f, btn) {
  document.querySelectorAll('.active-filter').forEach(b => b.classList.remove('active-filter','btn-primary'));
  btn.classList.add('active-filter','btn-primary');
  document.querySelectorAll('.mesa-card').forEach(card => {
    card.style.display = (f === 'all' || card.dataset.estado === f) ? '' : 'none';
  });
}

function clickMesa(id, estado, ordenId) {
  document.getElementById('modalMesaTitle').textContent = 'Mesa #' + id;
  let body = '';
  if (estado === 'disponible') {
    body = `
      <p class="text-muted mb-4">Esta mesa está disponible. ¿Qué deseas hacer?</p>
      <div style="display:flex;flex-direction:column;gap:10px">
        <button class="btn btn-success btn-block" onclick="abrirOrden(${id})"><i class="fas fa-plus-circle"></i> Abrir Orden / Comanda</button>
        <button class="btn btn-secondary btn-block" onclick="cambiarEstadoMesa(${id},'reservada')"><i class="fas fa-calendar-check"></i> Marcar como Reservada</button>
      </div>`;
  } else if (estado === 'ocupada') {
    body = `
      <div style="display:flex;flex-direction:column;gap:10px">
        <a class="btn btn-primary btn-block" href="orden.php?id=${ordenId}"><i class="fas fa-list"></i> Ver / Editar Orden</a>
        ${ROLE !== 'mesero' ? `<a class="btn btn-success btn-block" href="<?= BASE_URL ?>/modules/cajero/pos.php?mesa=${id}"><i class="fas fa-cash-register"></i> Cobrar Mesa</a>` : ''}
        <button class="btn btn-danger btn-block" onclick="cambiarEstadoMesa(${id},'disponible')"><i class="fas fa-times"></i> Liberar Mesa</button>
      </div>`;
  } else if (estado === 'reservada') {
    body = `
      <div style="display:flex;flex-direction:column;gap:10px">
        <button class="btn btn-success btn-block" onclick="abrirOrden(${id})"><i class="fas fa-plus-circle"></i> Abrir Orden</button>
        <button class="btn btn-secondary btn-block" onclick="cambiarEstadoMesa(${id},'disponible')"><i class="fas fa-check"></i> Marcar Disponible</button>
      </div>`;
  }
  document.getElementById('modalMesaBody').innerHTML = body;
  openModal('modalMesa');
}

function abrirOrden(mesaId) {
  document.getElementById('ordenMesaId').value = mesaId;
  closeModal('modalMesa');
  openModal('modalOrden');
}

async function crearOrden(e) {
  e.preventDefault();
  const mesaId = document.getElementById('ordenMesaId').value;
  const numP   = document.getElementById('numPersonas').value;
  const notas  = document.getElementById('notasMesa').value;
  try {
    const res = await fetch('../../ajax/ordenes.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({action:'crear', mesa_id:mesaId, num_personas:numP, notas})
    });
    const data = await res.json();
    if (data.success) {
      closeModal('modalOrden');
      window.location.href = 'orden.php?id=' + data.orden_id;
    } else { showToast(data.error || 'Error al crear orden','danger'); }
  } catch(err) { showToast('Error de conexión','danger'); }
}

async function cambiarEstadoMesa(id, estado) {
  const res = await fetch('../../ajax/mesas.php', {
    method:'POST',headers:{'Content-Type':'application/json'},
    body: JSON.stringify({action:'cambiar_estado', mesa_id:id, estado})
  });
  const data = await res.json();
  if (data.success) location.reload();
  else showToast(data.error,'danger');
}
</script>

<?php require_once '../../includes/footer.php'; ?>
