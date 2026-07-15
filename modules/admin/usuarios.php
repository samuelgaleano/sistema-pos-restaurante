<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireRole(['Administrador']);

$db = getDB();
$pageTitle = 'Usuarios';
$activeMenu = 'usuarios';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'guardar') {
        $id   = (int)($_POST['id'] ?? 0);
        $data = [
            sanitize($_POST['nombre']),
            sanitize($_POST['apellido']),
            sanitize($_POST['usuario']),
            (int)$_POST['rol_id'],
            sanitize($_POST['email'] ?? ''),
            sanitize($_POST['telefono'] ?? ''),
            (int)($_POST['activo'] ?? 1),
        ];
        if ($id) {
            if (!empty($_POST['password'])) {
                $db->prepare("UPDATE usuarios SET nombre=?,apellido=?,usuario=?,rol_id=?,email=?,telefono=?,activo=?,password=? WHERE id=?")
                   ->execute([...$data, password_hash($_POST['password'], PASSWORD_DEFAULT), $id]);
            } else {
                $db->prepare("UPDATE usuarios SET nombre=?,apellido=?,usuario=?,rol_id=?,email=?,telefono=?,activo=? WHERE id=?")
                   ->execute([...$data, $id]);
            }
            $msg = 'Usuario actualizado.';
        } else {
            if (empty($_POST['password'])) { $msg = 'ERROR: La contraseña es requerida para nuevos usuarios.'; }
            else {
                $db->prepare("INSERT INTO usuarios (nombre,apellido,usuario,rol_id,email,telefono,activo,password) VALUES(?,?,?,?,?,?,?,?)")
                   ->execute([...$data, password_hash($_POST['password'], PASSWORD_DEFAULT)]);
                $msg = 'Usuario creado correctamente.';
            }
        }
    } elseif ($action === 'toggle') {
        $db->prepare("UPDATE usuarios SET activo = NOT activo WHERE id=?")->execute([(int)$_POST['id']]);
        $msg = 'Estado actualizado.';
    }
}

$roles    = $db->query("SELECT * FROM roles ORDER BY id")->fetchAll();
$usuarios = $db->query("
    SELECT u.*, r.nombre as rol_nombre
    FROM usuarios u JOIN roles r ON u.rol_id=r.id
    ORDER BY r.id, u.nombre
")->fetchAll();

require_once '../../includes/header.php';
?>

<?php if($msg): ?>
<div class="alert <?= str_starts_with($msg,'ERROR') ? 'alert-danger':'alert-success' ?> alert-auto"><i class="fas fa-<?= str_starts_with($msg,'ERROR')?'exclamation-circle':'check' ?>"></i> <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="fas fa-users text-accent"></i> Usuarios del Sistema (<?= count($usuarios) ?>)</span>
    <button class="btn btn-primary" onclick="openModal('modalUsuario');limpiarForm()"><i class="fas fa-plus"></i> Nuevo Usuario</button>
  </div>
  <div class="table-wrap">
    <table class="pos-table">
      <thead><tr><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Email</th><th>Teléfono</th><th>Último Acceso</th><th>Estado</th><th>Acciones</th></tr></thead>
      <tbody>
        <?php foreach($usuarios as $u): ?>
        <tr>
          <td class="fw-bold"><?= htmlspecialchars($u['nombre'].' '.$u['apellido']) ?></td>
          <td><code style="background:var(--surface);padding:2px 8px;border-radius:4px"><?= htmlspecialchars($u['usuario']) ?></code></td>
          <td>
            <?php $rolColors=['Administrador'=>'danger','Cajero'=>'success','Mesero'=>'info']; ?>
            <span class="badge badge-<?= $rolColors[$u['rol_nombre']]??'muted' ?>"><?= htmlspecialchars($u['rol_nombre']) ?></span>
          </td>
          <td class="text-muted fs-sm"><?= htmlspecialchars($u['email'] ?? '—') ?></td>
          <td class="text-muted fs-sm"><?= htmlspecialchars($u['telefono'] ?? '—') ?></td>
          <td class="text-muted fs-sm"><?= $u['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($u['ultimo_acceso'])) : 'Nunca' ?></td>
          <td>
            <span class="badge badge-<?= $u['activo'] ? 'success':'danger' ?>"><?= $u['activo'] ? 'Activo':'Inactivo' ?></span>
          </td>
          <td style="display:flex;gap:4px">
            <?php if($u['id'] != $_SESSION['user_id']): ?>
            <button class="btn btn-sm btn-secondary" onclick='editarUsuario(<?= json_encode($u) ?>)'><i class="fas fa-edit"></i></button>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button class="btn btn-sm btn-<?= $u['activo']?'warning':'success' ?>" type="submit">
                <i class="fas fa-<?= $u['activo']?'ban':'check' ?>"></i>
              </button>
            </form>
            <?php else: ?>
            <span class="badge badge-muted">Tú</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Usuario -->
<div class="modal-overlay" id="modalUsuario">
  <div class="modal-box">
    <div class="modal-header">
      <span class="modal-title" id="userModalTitle">Nuevo Usuario</span>
      <button class="modal-close" onclick="closeModal('modalUsuario')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="guardar">
      <input type="hidden" name="id" id="userId">
      <div class="modal-body">
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Nombre *</label>
            <input type="text" name="nombre" id="uNombre" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Apellido *</label>
            <input type="text" name="apellido" id="uApellido" class="form-control" required>
          </div>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Usuario *</label>
            <input type="text" name="usuario" id="uUsuario" class="form-control" required autocomplete="off">
          </div>
          <div class="form-group">
            <label class="form-label">Rol *</label>
            <select name="rol_id" id="uRol" class="form-control" required>
              <?php foreach($roles as $r): ?>
              <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" id="uEmail" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Teléfono</label>
            <input type="text" name="telefono" id="uTel" class="form-control">
          </div>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Contraseña <span id="passHint" class="text-muted fs-sm">(requerida)</span></label>
            <input type="password" name="password" id="uPass" class="form-control" autocomplete="new-password">
          </div>
          <div class="form-group">
            <label class="form-label">Estado</label>
            <select name="activo" id="uActivo" class="form-control">
              <option value="1">Activo</option>
              <option value="0">Inactivo</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalUsuario')">Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
function limpiarForm() {
  document.getElementById('userModalTitle').textContent = 'Nuevo Usuario';
  document.getElementById('userId').value = '';
  ['uNombre','uApellido','uUsuario','uEmail','uTel','uPass'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('uActivo').value = '1';
  document.getElementById('passHint').textContent = '(requerida)';
}
function editarUsuario(u) {
  document.getElementById('userModalTitle').textContent = 'Editar: ' + u.nombre;
  document.getElementById('userId').value    = u.id;
  document.getElementById('uNombre').value   = u.nombre;
  document.getElementById('uApellido').value = u.apellido;
  document.getElementById('uUsuario').value  = u.usuario;
  document.getElementById('uEmail').value    = u.email || '';
  document.getElementById('uTel').value      = u.telefono || '';
  document.getElementById('uRol').value      = u.rol_id;
  document.getElementById('uActivo').value   = u.activo;
  document.getElementById('uPass').value     = '';
  document.getElementById('passHint').textContent = '(dejar vacío para no cambiar)';
  openModal('modalUsuario');
}
</script>

<?php require_once '../../includes/footer.php'; ?>
