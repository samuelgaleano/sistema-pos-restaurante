<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireRole(['Administrador','Cajero']);

$db = getDB();
$pageTitle = 'Clientes';
$activeMenu = 'clientes';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'guardar') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            sanitize($_POST['tipo_doc']),
            sanitize($_POST['num_doc'] ?? ''),
            sanitize($_POST['nombre']),
            sanitize($_POST['apellido'] ?? ''),
            sanitize($_POST['email'] ?? ''),
            sanitize($_POST['telefono'] ?? ''),
            sanitize($_POST['direccion'] ?? ''),
            sanitize($_POST['ciudad'] ?? ''),
        ];
        if ($id) {
            $db->prepare("UPDATE clientes SET tipo_doc=?,num_doc=?,nombre=?,apellido=?,email=?,telefono=?,direccion=?,ciudad=? WHERE id=?")->execute([...$data,$id]);
            $msg = 'Cliente actualizado.';
        } else {
            $db->prepare("INSERT INTO clientes (tipo_doc,num_doc,nombre,apellido,email,telefono,direccion,ciudad) VALUES(?,?,?,?,?,?,?,?)")->execute($data);
            $msg = 'Cliente creado.';
        }
    }
}

$clientes = $db->query("
    SELECT c.*, 
           COUNT(f.id) as num_compras,
           COALESCE(SUM(f.total),0) as total_compras
    FROM clientes c
    LEFT JOIN facturas f ON f.cliente_id=c.id AND f.estado='pagada'
    WHERE c.activo=1
    GROUP BY c.id
    ORDER BY c.nombre
")->fetchAll();

require_once '../../includes/header.php';
?>

<?php if($msg): ?><div class="alert alert-success alert-auto"><i class="fas fa-check"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="fas fa-users text-accent"></i> Clientes (<?= count($clientes) ?>)</span>
    <div style="display:flex;gap:8px">
      <input type="text" id="searchCli" class="form-control" placeholder="🔍 Buscar..." oninput="filterTable()" style="width:200px">
      <button class="btn btn-primary" onclick="openModal('modalCliente');limpiarForm()"><i class="fas fa-plus"></i> Nuevo Cliente</button>
    </div>
  </div>
  <div class="table-wrap">
    <table class="pos-table" id="tablaCli">
      <thead><tr><th>Doc</th><th>Nombre</th><th>Teléfono</th><th>Email</th><th>Ciudad</th><th>Compras</th><th>Total</th><th></th></tr></thead>
      <tbody>
        <?php foreach($clientes as $c): ?>
        <tr>
          <td class="fs-sm text-muted"><?= htmlspecialchars($c['tipo_doc'].' '.$c['num_doc']) ?></td>
          <td class="fw-bold"><?= htmlspecialchars($c['nombre'].' '.($c['apellido']??'')) ?></td>
          <td class="text-muted fs-sm"><?= htmlspecialchars($c['telefono']??'—') ?></td>
          <td class="text-muted fs-sm"><?= htmlspecialchars($c['email']??'—') ?></td>
          <td class="text-muted fs-sm"><?= htmlspecialchars($c['ciudad']??'—') ?></td>
          <td><span class="badge badge-info"><?= $c['num_compras'] ?></span></td>
          <td class="fw-bold text-accent"><?= formatMoney($c['total_compras']) ?></td>
          <td>
            <button class="btn btn-sm btn-secondary" onclick='editarCliente(<?= json_encode($c) ?>)'><i class="fas fa-edit"></i></button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Cliente -->
<div class="modal-overlay" id="modalCliente">
  <div class="modal-box">
    <div class="modal-header">
      <span class="modal-title" id="cliModalTitle">Nuevo Cliente</span>
      <button class="modal-close" onclick="closeModal('modalCliente')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="guardar">
      <input type="hidden" name="id" id="cliId">
      <div class="modal-body">
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Tipo Documento</label>
            <select name="tipo_doc" id="cliTipoDoc" class="form-control">
              <option value="CC">Cédula de Ciudadanía</option>
              <option value="NIT">NIT</option>
              <option value="CE">Cédula Extranjería</option>
              <option value="PPN">Pasaporte</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Número Documento</label>
            <input type="text" name="num_doc" id="cliDoc" class="form-control">
          </div>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Nombre *</label>
            <input type="text" name="nombre" id="cliNombre" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Apellido</label>
            <input type="text" name="apellido" id="cliApellido" class="form-control">
          </div>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" id="cliEmail" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Teléfono</label>
            <input type="text" name="telefono" id="cliTel" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Dirección</label>
          <input type="text" name="direccion" id="cliDir" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label">Ciudad</label>
          <input type="text" name="ciudad" id="cliCiudad" class="form-control" value="Bogotá">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalCliente')">Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
function limpiarForm() {
  document.getElementById('cliModalTitle').textContent = 'Nuevo Cliente';
  document.getElementById('cliId').value = '';
  ['cliDoc','cliNombre','cliApellido','cliEmail','cliTel','cliDir'].forEach(id=>document.getElementById(id).value='');
  document.getElementById('cliCiudad').value='Bogotá';
}
function editarCliente(c) {
  document.getElementById('cliModalTitle').textContent = 'Editar: '+c.nombre;
  document.getElementById('cliId').value=c.id;
  document.getElementById('cliTipoDoc').value=c.tipo_doc;
  document.getElementById('cliDoc').value=c.num_doc||'';
  document.getElementById('cliNombre').value=c.nombre;
  document.getElementById('cliApellido').value=c.apellido||'';
  document.getElementById('cliEmail').value=c.email||'';
  document.getElementById('cliTel').value=c.telefono||'';
  document.getElementById('cliDir').value=c.direccion||'';
  document.getElementById('cliCiudad').value=c.ciudad||'';
  openModal('modalCliente');
}
function filterTable(){
  const q=document.getElementById('searchCli').value.toLowerCase();
  document.querySelectorAll('#tablaCli tbody tr').forEach(r=>r.style.display=r.textContent.toLowerCase().includes(q)?'':'none');
}
</script>

<?php require_once '../../includes/footer.php'; ?>
