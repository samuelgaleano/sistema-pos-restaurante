<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireRole(['Administrador']);

$db = getDB();
$pageTitle = 'Configuración';
$activeMenu = 'configuracion';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $claves = [
        'empresa_nombre','empresa_nit','empresa_direccion','empresa_telefono',
        'empresa_ciudad','empresa_email','factura_prefijo',
        'impuesto_nombre','impuesto_porcentaje','moneda_simbolo','moneda_nombre',
        'propina_sugerida'
    ];
    foreach ($claves as $clave) {
        if (isset($_POST[$clave])) {
            $db->prepare("UPDATE configuracion SET valor=? WHERE clave=?")->execute([sanitize($_POST[$clave]), $clave]);
        }
    }
    $msg = 'Configuración guardada correctamente.';
}

$cfg = [];
$rows = $db->query("SELECT clave, valor FROM configuracion")->fetchAll();
foreach ($rows as $r) $cfg[$r['clave']] = $r['valor'];

require_once '../../includes/header.php';
?>

<?php if($msg): ?><div class="alert alert-success alert-auto"><i class="fas fa-check"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>

<form method="POST">
<div class="grid-2">
  <!-- Datos de la empresa -->
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fas fa-building text-accent"></i> Datos del Negocio</span></div>
    <div class="form-group">
      <label class="form-label">Nombre del Negocio</label>
      <input type="text" name="empresa_nombre" class="form-control" value="<?= htmlspecialchars($cfg['empresa_nombre']??'') ?>" required>
    </div>
    <div class="form-group">
      <label class="form-label">NIT / Documento</label>
      <input type="text" name="empresa_nit" class="form-control" value="<?= htmlspecialchars($cfg['empresa_nit']??'') ?>">
    </div>
    <div class="form-group">
      <label class="form-label">Dirección</label>
      <input type="text" name="empresa_direccion" class="form-control" value="<?= htmlspecialchars($cfg['empresa_direccion']??'') ?>">
    </div>
    <div class="form-group">
      <label class="form-label">Ciudad</label>
      <input type="text" name="empresa_ciudad" class="form-control" value="<?= htmlspecialchars($cfg['empresa_ciudad']??'') ?>">
    </div>
    <div class="form-group">
      <label class="form-label">Teléfono</label>
      <input type="text" name="empresa_telefono" class="form-control" value="<?= htmlspecialchars($cfg['empresa_telefono']??'') ?>">
    </div>
    <div class="form-group">
      <label class="form-label">Email</label>
      <input type="email" name="empresa_email" class="form-control" value="<?= htmlspecialchars($cfg['empresa_email']??'') ?>">
    </div>
  </div>

  <!-- Facturación y moneda -->
  <div>
    <div class="card mb-4">
      <div class="card-header"><span class="card-title"><i class="fas fa-file-invoice-dollar text-accent"></i> Facturación</span></div>
      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">Prefijo de Factura</label>
          <input type="text" name="factura_prefijo" class="form-control" value="<?= htmlspecialchars($cfg['factura_prefijo']??'FAC') ?>" maxlength="10">
        </div>
        <div class="form-group">
          <label class="form-label">Consecutivo Actual</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($cfg['factura_consecutivo']??'1') ?>" disabled style="opacity:.5">
          <small class="text-muted">No editable directamente</small>
        </div>
      </div>
      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">Nombre Impuesto</label>
          <input type="text" name="impuesto_nombre" class="form-control" value="<?= htmlspecialchars($cfg['impuesto_nombre']??'IVA') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">% Impuesto (0 = sin impuesto)</label>
          <input type="number" name="impuesto_porcentaje" class="form-control" value="<?= htmlspecialchars($cfg['impuesto_porcentaje']??'0') ?>" min="0" max="100" step="0.01">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">% Propina Sugerida</label>
        <input type="number" name="propina_sugerida" class="form-control" value="<?= htmlspecialchars($cfg['propina_sugerida']??'10') ?>" min="0" max="50">
      </div>
    </div>

    <div class="card">
      <div class="card-header"><span class="card-title"><i class="fas fa-coins text-accent"></i> Moneda</span></div>
      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">Símbolo</label>
          <input type="text" name="moneda_simbolo" class="form-control" value="<?= htmlspecialchars($cfg['moneda_simbolo']??'$') ?>" maxlength="5">
        </div>
        <div class="form-group">
          <label class="form-label">Código ISO</label>
          <input type="text" name="moneda_nombre" class="form-control" value="<?= htmlspecialchars($cfg['moneda_nombre']??'COP') ?>" maxlength="5">
        </div>
      </div>
    </div>
  </div>
</div>

<div class="mt-4" style="display:flex;justify-content:flex-end">
  <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Guardar Configuración</button>
</div>
</form>

<!-- Zona de categorías -->
<div class="card mt-4">
  <div class="card-header">
    <span class="card-title"><i class="fas fa-tags text-accent"></i> Categorías de Productos</span>
    <button class="btn btn-primary btn-sm" onclick="openModal('modalCat')"><i class="fas fa-plus"></i> Nueva</button>
  </div>
  <?php $cats = $db->query("SELECT * FROM categorias ORDER BY orden, nombre")->fetchAll(); ?>
  <div class="table-wrap">
    <table class="pos-table">
      <thead><tr><th>Nombre</th><th>Icono</th><th>Color</th><th>Orden</th><th>Estado</th><th></th></tr></thead>
      <tbody>
        <?php foreach($cats as $cat): ?>
        <tr>
          <td class="fw-bold"><?= htmlspecialchars($cat['nombre']) ?></td>
          <td><i class="<?= htmlspecialchars($cat['icono']) ?>"></i> <code class="fs-sm"><?= htmlspecialchars($cat['icono']) ?></code></td>
          <td><span style="background:<?= htmlspecialchars($cat['color']) ?>;padding:3px 12px;border-radius:6px;font-size:12px;"><?= htmlspecialchars($cat['color']) ?></span></td>
          <td><?= $cat['orden'] ?></td>
          <td><span class="badge badge-<?= $cat['activo']?'success':'danger' ?>"><?= $cat['activo']?'Activo':'Inactivo' ?></span></td>
          <td><button class="btn btn-sm btn-secondary" onclick='editCat(<?= json_encode($cat) ?>)'><i class="fas fa-edit"></i></button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Categoría -->
<div class="modal-overlay" id="modalCat">
  <div class="modal-box" style="max-width:420px">
    <div class="modal-header">
      <span class="modal-title" id="catModalTitle">Nueva Categoría</span>
      <button class="modal-close" onclick="closeModal('modalCat')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" action="../../ajax/categorias.php">
      <input type="hidden" name="action" value="guardar">
      <input type="hidden" name="id" id="catId">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Nombre</label>
          <input type="text" name="nombre" id="catNombre" class="form-control" required>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Icono FontAwesome</label>
            <input type="text" name="icono" id="catIcono" class="form-control" placeholder="fa-tag" value="fa-tag">
            <small class="text-muted"><a href="https://fontawesome.com/icons" target="_blank" style="color:var(--accent)">Ver iconos →</a></small>
          </div>
          <div class="form-group">
            <label class="form-label">Color</label>
            <input type="color" name="color" id="catColor" class="form-control" value="#3498db" style="height:42px;padding:4px">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Orden</label>
          <input type="number" name="orden" id="catOrden" class="form-control" value="0" min="0">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalCat')">Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
function editCat(c) {
  document.getElementById('catModalTitle').textContent = 'Editar: '+c.nombre;
  document.getElementById('catId').value = c.id;
  document.getElementById('catNombre').value = c.nombre;
  document.getElementById('catIcono').value = c.icono;
  document.getElementById('catColor').value = c.color;
  document.getElementById('catOrden').value = c.orden;
  openModal('modalCat');
}
</script>

<?php require_once '../../includes/footer.php'; ?>
