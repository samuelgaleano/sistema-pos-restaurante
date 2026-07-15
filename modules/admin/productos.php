<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireRole(['Administrador']);

$db = getDB();
$pageTitle = 'Productos';
$activeMenu = 'productos';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'guardar') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            sanitize($_POST['nombre']),
            (int)$_POST['categoria_id'],
            (int)($_POST['unidad_id'] ?: 1),
            sanitize($_POST['codigo'] ?? ''),
            sanitize($_POST['descripcion'] ?? ''),
            (float)($_POST['precio_costo'] ?? 0),
            (float)$_POST['precio_venta'],
            (float)($_POST['stock_minimo'] ?? 0),
            (int)($_POST['tiene_stock'] ?? 1),
            (int)($_POST['activo'] ?? 1),
        ];
        if ($id) {
            $db->prepare("UPDATE productos SET nombre=?,categoria_id=?,unidad_id=?,codigo=?,descripcion=?,precio_costo=?,precio_venta=?,stock_minimo=?,tiene_stock=?,activo=? WHERE id=?")
               ->execute([...$data, $id]);
            $msg = 'Producto actualizado correctamente.';
        } else {
            $db->prepare("INSERT INTO productos (nombre,categoria_id,unidad_id,codigo,descripcion,precio_costo,precio_venta,stock_minimo,tiene_stock,activo) VALUES(?,?,?,?,?,?,?,?,?,?)")
               ->execute($data);
            $msg = 'Producto creado correctamente.';
        }
    } elseif ($action === 'eliminar') {
        $db->prepare("UPDATE productos SET activo=0 WHERE id=?")->execute([(int)$_POST['id']]);
        $msg = 'Producto desactivado.';
    }
}

$categorias = $db->query("SELECT * FROM categorias WHERE activo=1 ORDER BY nombre")->fetchAll();
$unidades   = $db->query("SELECT * FROM unidades_medida ORDER BY nombre")->fetchAll();
$productos  = $db->query("
    SELECT p.*, c.nombre as cat_nombre, u.abreviatura as unidad
    FROM productos p
    LEFT JOIN categorias c ON p.categoria_id = c.id
    LEFT JOIN unidades_medida u ON p.unidad_id = u.id
    ORDER BY c.nombre, p.nombre
")->fetchAll();

require_once '../../includes/header.php';
?>

<?php if ($msg): ?><div class="alert alert-success alert-auto"><i class="fas fa-check-circle"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="fas fa-box-open text-accent"></i> Productos (<?= count($productos) ?>)</span>
    <button class="btn btn-primary" onclick="openModal('modalProducto');limpiarForm()"><i class="fas fa-plus"></i> Nuevo Producto</button>
  </div>
  <div style="padding:0 0 12px;display:flex;gap:10px">
    <input type="text" id="searchProd" class="form-control" placeholder="🔍 Buscar producto..." oninput="filterTable()" style="max-width:300px">
    <select id="filterCat" class="form-control" onchange="filterTable()" style="max-width:200px">
      <option value="">Todas las categorías</option>
      <?php foreach($categorias as $c): ?>
      <option value="<?= htmlspecialchars($c['nombre']) ?>"><?= htmlspecialchars($c['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="table-wrap">
    <table class="pos-table" id="tablaProd">
      <thead>
        <tr><th>Código</th><th>Nombre</th><th>Categoría</th><th>Precio Venta</th><th>Precio Costo</th><th>Stock</th><th>Estado</th><th>Acciones</th></tr>
      </thead>
      <tbody>
        <?php foreach($productos as $p): ?>
        <tr>
          <td class="text-muted fs-sm"><?= htmlspecialchars($p['codigo'] ?? '-') ?></td>
          <td class="fw-bold"><?= htmlspecialchars($p['nombre']) ?></td>
          <td><span class="badge badge-purple"><?= htmlspecialchars($p['cat_nombre']) ?></span></td>
          <td class="fw-bold text-accent"><?= formatMoney($p['precio_venta']) ?></td>
          <td class="text-muted"><?= formatMoney($p['precio_costo']) ?></td>
          <td>
            <?php if($p['tiene_stock']): ?>
            <span class="<?= $p['stock_actual'] <= $p['stock_minimo'] ? 'text-danger fw-bold' : '' ?>">
              <?= number_format($p['stock_actual'],1) ?> <?= $p['unidad'] ?>
            </span>
            <?php else: ?><span class="text-muted">Sin control</span><?php endif; ?>
          </td>
          <td><span class="badge badge-<?= $p['activo'] ? 'success':'danger' ?>"><?= $p['activo'] ? 'Activo':'Inactivo' ?></span></td>
          <td>
            <button class="btn btn-sm btn-secondary" onclick='editarProducto(<?= json_encode($p) ?>)'><i class="fas fa-edit"></i></button>
            <form method="POST" style="display:inline" onsubmit="return confirm('¿Desactivar producto?')">
              <input type="hidden" name="action" value="eliminar">
              <input type="hidden" name="id" value="<?= $p['id'] ?>">
              <button class="btn btn-sm btn-danger" type="submit"><i class="fas fa-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Producto -->
<div class="modal-overlay" id="modalProducto">
  <div class="modal-box">
    <div class="modal-header">
      <span class="modal-title" id="prodModalTitle">Nuevo Producto</span>
      <button class="modal-close" onclick="closeModal('modalProducto')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="guardar">
      <input type="hidden" name="id" id="prodId">
      <div class="modal-body">
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Nombre *</label>
            <input type="text" name="nombre" id="prodNombre" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Código / Referencia</label>
            <input type="text" name="codigo" id="prodCodigo" class="form-control" placeholder="Opcional">
          </div>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Categoría *</label>
            <select name="categoria_id" id="prodCat" class="form-control" required>
              <?php foreach($categorias as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Unidad de Medida</label>
            <select name="unidad_id" id="prodUnidad" class="form-control">
              <?php foreach($unidades as $u): ?>
              <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Precio de Venta *</label>
            <input type="number" name="precio_venta" id="prodPrecioV" class="form-control" step="0.01" min="0" required>
          </div>
          <div class="form-group">
            <label class="form-label">Precio de Costo</label>
            <input type="number" name="precio_costo" id="prodPrecioC" class="form-control" step="0.01" min="0" value="0">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Descripción</label>
          <textarea name="descripcion" id="prodDesc" class="form-control"></textarea>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">
              <input type="checkbox" name="tiene_stock" id="prodTieneStock" value="1" onchange="toggleStock()"> Control de Stock
            </label>
            <input type="number" name="stock_minimo" id="prodStockMin" class="form-control mt-3" placeholder="Stock mínimo" step="0.01" min="0" value="0">
          </div>
          <div class="form-group">
            <label class="form-label">Estado</label>
            <select name="activo" id="prodActivo" class="form-control">
              <option value="1">Activo</option>
              <option value="0">Inactivo</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalProducto')">Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
function limpiarForm() {
  document.getElementById('prodModalTitle').textContent = 'Nuevo Producto';
  document.getElementById('prodId').value = '';
  document.getElementById('prodNombre').value = '';
  document.getElementById('prodCodigo').value = '';
  document.getElementById('prodDesc').value = '';
  document.getElementById('prodPrecioV').value = '';
  document.getElementById('prodPrecioC').value = '0';
  document.getElementById('prodStockMin').value = '0';
  document.getElementById('prodTieneStock').checked = true;
  document.getElementById('prodActivo').value = '1';
}
function editarProducto(p) {
  document.getElementById('prodModalTitle').textContent = 'Editar: ' + p.nombre;
  document.getElementById('prodId').value = p.id;
  document.getElementById('prodNombre').value = p.nombre;
  document.getElementById('prodCodigo').value = p.codigo || '';
  document.getElementById('prodDesc').value = p.descripcion || '';
  document.getElementById('prodPrecioV').value = p.precio_venta;
  document.getElementById('prodPrecioC').value = p.precio_costo;
  document.getElementById('prodCat').value = p.categoria_id;
  document.getElementById('prodUnidad').value = p.unidad_id;
  document.getElementById('prodStockMin').value = p.stock_minimo;
  document.getElementById('prodTieneStock').checked = !!parseInt(p.tiene_stock);
  document.getElementById('prodActivo').value = p.activo;
  openModal('modalProducto');
}
function filterTable() {
  const q = document.getElementById('searchProd').value.toLowerCase();
  const cat = document.getElementById('filterCat').value.toLowerCase();
  document.querySelectorAll('#tablaProd tbody tr').forEach(row => {
    const text = row.textContent.toLowerCase();
    row.style.display = text.includes(q) && (!cat || text.includes(cat)) ? '' : 'none';
  });
}
</script>
<?php require_once '../../includes/footer.php'; ?>
