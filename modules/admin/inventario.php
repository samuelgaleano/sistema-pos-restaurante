<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireRole(['Administrador']);

$db = getDB();
$pageTitle = 'Inventario';
$activeMenu = 'inventario';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'ajuste') {
        $prodId   = (int)$_POST['producto_id'];
        $tipo     = $_POST['tipo']; // entrada|ajuste
        $cantidad = (float)$_POST['cantidad'];
        $nota     = sanitize($_POST['nota'] ?? '');

        $stmt = $db->prepare("SELECT stock_actual FROM productos WHERE id=?");
        $stmt->execute([$prodId]);
        $stockAnterior = (float)$stmt->fetchColumn();

        if ($tipo === 'entrada') {
            $nuevoStock = $stockAnterior + $cantidad;
        } else {
            $nuevoStock = $cantidad; // ajuste directo
        }

        $db->prepare("UPDATE productos SET stock_actual=? WHERE id=?")->execute([$nuevoStock, $prodId]);
        $db->prepare("INSERT INTO movimientos_inventario (producto_id,usuario_id,tipo,cantidad,stock_anterior,stock_nuevo,nota) VALUES(?,?,?,?,?,?,?)")
           ->execute([$prodId, $_SESSION['user_id'], $tipo, abs($nuevoStock - $stockAnterior), $stockAnterior, $nuevoStock, $nota]);
        $msg = 'Inventario actualizado.';
    }
}

$productos = $db->query("
    SELECT p.*, c.nombre as cat, u.abreviatura as unidad
    FROM productos p
    LEFT JOIN categorias c ON p.categoria_id=c.id
    LEFT JOIN unidades_medida u ON p.unidad_id=u.id
    WHERE p.tiene_stock=1 AND p.activo=1
    ORDER BY p.nombre
")->fetchAll();

$movimientos = $db->query("
    SELECT m.*, p.nombre as prod_nombre, u.nombre as user_nombre
    FROM movimientos_inventario m
    JOIN productos p ON m.producto_id=p.id
    JOIN usuarios u ON m.usuario_id=u.id
    ORDER BY m.created_at DESC LIMIT 50
")->fetchAll();

require_once '../../includes/header.php';
?>

<?php if($msg): ?><div class="alert alert-success alert-auto"><i class="fas fa-check"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="grid-2">
  <!-- Stock Actual -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fas fa-boxes text-accent"></i> Stock Actual</span>
      <button class="btn btn-primary btn-sm" onclick="openModal('modalAjuste')"><i class="fas fa-edit"></i> Ajustar</button>
    </div>
    <div class="table-wrap">
      <table class="pos-table">
        <thead><tr><th>Producto</th><th>Categoría</th><th>Stock</th><th>Mínimo</th><th>Estado</th></tr></thead>
        <tbody>
          <?php foreach($productos as $p): ?>
          <?php $bajo = $p['stock_actual'] <= $p['stock_minimo']; ?>
          <tr>
            <td class="fw-bold"><?= htmlspecialchars($p['nombre']) ?></td>
            <td class="text-muted fs-sm"><?= htmlspecialchars($p['cat']) ?></td>
            <td class="<?= $bajo ? 'text-danger fw-bold' : 'text-success' ?>">
              <?= number_format($p['stock_actual'],2) ?> <?= $p['unidad'] ?>
            </td>
            <td class="text-muted fs-sm"><?= number_format($p['stock_minimo'],2) ?></td>
            <td>
              <?php if($bajo): ?>
              <span class="badge badge-danger"><i class="fas fa-exclamation-triangle"></i> Bajo</span>
              <?php elseif($p['stock_actual'] == 0): ?>
              <span class="badge badge-danger">Sin Stock</span>
              <?php else: ?>
              <span class="badge badge-success">OK</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Movimientos -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fas fa-exchange-alt text-accent"></i> Últimos Movimientos</span>
    </div>
    <div class="table-wrap">
      <table class="pos-table">
        <thead><tr><th>Producto</th><th>Tipo</th><th>Cant.</th><th>Usuario</th><th>Fecha</th></tr></thead>
        <tbody>
          <?php foreach($movimientos as $m): ?>
          <?php $colors=['entrada'=>'success','salida'=>'danger','ajuste'=>'info','venta'=>'warning','devolucion'=>'purple']; ?>
          <tr>
            <td class="fw-bold fs-sm"><?= htmlspecialchars($m['prod_nombre']) ?></td>
            <td><span class="badge badge-<?= $colors[$m['tipo']] ?>"><?= ucfirst($m['tipo']) ?></span></td>
            <td><?= number_format($m['cantidad'],2) ?></td>
            <td class="text-muted fs-sm"><?= htmlspecialchars($m['user_nombre']) ?></td>
            <td class="text-muted fs-sm"><?= date('d/m H:i', strtotime($m['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal Ajuste Inventario -->
<div class="modal-overlay" id="modalAjuste">
  <div class="modal-box" style="max-width:420px">
    <div class="modal-header">
      <span class="modal-title">Ajuste de Inventario</span>
      <button class="modal-close" onclick="closeModal('modalAjuste')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="ajuste">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Producto</label>
          <select name="producto_id" id="ajusteProd" class="form-control" required onchange="cargarStock()">
            <option value="">Seleccionar producto...</option>
            <?php foreach($productos as $p): ?>
            <option value="<?= $p['id'] ?>" data-stock="<?= $p['stock_actual'] ?>">
              <?= htmlspecialchars($p['nombre']) ?> (<?= number_format($p['stock_actual'],1) ?> <?= $p['unidad'] ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div id="stockActualInfo" class="alert alert-info d-none">
          Stock actual: <strong id="stockActualVal">—</strong>
        </div>
        <div class="form-group">
          <label class="form-label">Tipo de Movimiento</label>
          <select name="tipo" id="ajusteTipo" class="form-control" onchange="toggleTipo()">
            <option value="entrada">Entrada de Mercancía</option>
            <option value="ajuste">Ajuste / Inventario Físico</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" id="cantLabel">Cantidad a Ingresar</label>
          <input type="number" name="cantidad" class="form-control" step="0.01" min="0" required placeholder="0">
        </div>
        <div class="form-group">
          <label class="form-label">Nota / Razón</label>
          <input type="text" name="nota" class="form-control" placeholder="Compra, conteo físico...">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalAjuste')">Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Aplicar Ajuste</button>
      </div>
    </form>
  </div>
</div>

<script>
function cargarStock() {
  const sel = document.getElementById('ajusteProd');
  const opt = sel.options[sel.selectedIndex];
  const stock = opt.dataset.stock;
  if (stock !== undefined) {
    document.getElementById('stockActualInfo').classList.remove('d-none');
    document.getElementById('stockActualVal').textContent = parseFloat(stock).toFixed(2);
  }
}
function toggleTipo() {
  const tipo = document.getElementById('ajusteTipo').value;
  document.getElementById('cantLabel').textContent = tipo === 'entrada' ? 'Cantidad a Ingresar' : 'Nuevo Stock Total';
}
</script>

<?php require_once '../../includes/footer.php'; ?>
