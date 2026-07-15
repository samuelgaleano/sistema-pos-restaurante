<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireRole(['Administrador','Cajero','Mesero']);

$db = getDB();
$ordenId = (int)($_GET['id'] ?? 0);

$orden = $db->prepare("
    SELECT o.*, m.numero as mesa_num, m.nombre as mesa_nombre,
           u.nombre as mesero_nombre
    FROM ordenes o
    JOIN mesas m ON o.mesa_id=m.id
    JOIN usuarios u ON o.mesero_id=u.id
    WHERE o.id=?
");
$orden->execute([$ordenId]);
$orden = $orden->fetch();

if (!$orden) { header('Location: mesas.php'); exit; }

$pageTitle = 'Orden #'.$ordenId;
$activeMenu = 'mesas';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_item') {
        $db->prepare("INSERT INTO orden_items (orden_id,producto_id,cantidad,precio_unitario,notas) VALUES(?,?,?,?,?)")
           ->execute([$ordenId, $_POST['producto_id'], $_POST['cantidad'], $_POST['precio'], $_POST['notas']??null]);
        $db->prepare("UPDATE mesas SET estado='ocupada' WHERE id=?")->execute([$orden['mesa_id']]);
        header('Location: orden.php?id='.$ordenId.'&ok=add');
        exit;
    } elseif ($action === 'remove_item') {
        $db->prepare("DELETE FROM orden_items WHERE id=? AND orden_id=?")->execute([(int)$_POST['item_id'], $ordenId]);
        header('Location: orden.php?id='.$ordenId.'&ok=remove');
        exit;
    } elseif ($action === 'cambiar_estado_item') {
        $db->prepare("UPDATE orden_items SET estado=? WHERE id=?")->execute([$_POST['estado'], (int)$_POST['item_id']]);
        header('Location: orden.php?id='.$ordenId);
        exit;
    } elseif ($action === 'cerrar_orden') {
        $db->prepare("UPDATE ordenes SET estado='cobrada' WHERE id=?")->execute([$ordenId]);
        $db->prepare("UPDATE mesas SET estado='disponible' WHERE id=?")->execute([$orden['mesa_id']]);
        header('Location: mesas.php');
        exit;
    }
}

if (isset($_GET['ok'])) {
    $msg = $_GET['ok'] === 'add' ? 'Producto agregado a la comanda.' : 'Producto eliminado.';
}

// Items de la orden
$itemsStmt = $db->prepare("
    SELECT oi.*, p.nombre as prod_nombre
    FROM orden_items oi
    JOIN productos p ON oi.producto_id=p.id
    WHERE oi.orden_id=? AND oi.estado != 'cancelado'
    ORDER BY oi.created_at
");
$itemsStmt->execute([$ordenId]);
$items = $itemsStmt->fetchAll();

$subtotal = array_sum(array_map(fn($i) => $i['precio_unitario'] * $i['cantidad'], $items));

$categorias = $db->query("SELECT * FROM categorias WHERE activo=1 ORDER BY orden")->fetchAll();
$productos  = $db->query("SELECT p.*, c.nombre as cat FROM productos p LEFT JOIN categorias c ON p.categoria_id=c.id WHERE p.activo=1 ORDER BY p.nombre")->fetchAll();

require_once '../../includes/header.php';
?>

<?php if($msg): ?><div class="alert alert-success alert-auto"><i class="fas fa-check"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
  <a href="mesas.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Volver</a>
  <h3>Mesa <?= htmlspecialchars($orden['mesa_nombre'] ?: $orden['mesa_num']) ?> — Orden #<?= $ordenId ?></h3>
  <span class="badge badge-info"><?= $orden['num_personas'] ?> personas</span>
  <span class="badge badge-<?= $orden['estado']==='abierta'?'success':'warning' ?>"><?= ucfirst($orden['estado']) ?></span>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:16px">
  <!-- Productos para agregar -->
  <div>
    <div class="card mb-3" style="padding:10px">
      <input type="text" id="buscarProd" class="form-control" placeholder="🔍 Buscar producto..." oninput="filtrarProd()">
      <div style="display:flex;gap:6px;margin-top:8px;flex-wrap:wrap">
        <button class="btn btn-sm btn-primary cat-btn" data-cat="0" onclick="filtCat(0,this)">Todos</button>
        <?php foreach($categorias as $c): ?>
        <button class="btn btn-sm btn-secondary cat-btn" data-cat="<?= $c['id'] ?>" onclick="filtCat(<?= $c['id'] ?>,this)"><?= htmlspecialchars($c['nombre']) ?></button>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="products-grid" id="prodGrid">
      <?php foreach($productos as $p): ?>
      <div class="product-card"
           data-id="<?= $p['id'] ?>"
           data-name="<?= htmlspecialchars($p['nombre']) ?>"
           data-price="<?= $p['precio_venta'] ?>"
           data-cat="<?= $p['categoria_id'] ?>"
           onclick="agregarItem(<?= $p['id'] ?>, '<?= addslashes($p['nombre']) ?>', <?= $p['precio_venta'] ?>)">
        <div class="name"><?= htmlspecialchars($p['nombre']) ?></div>
        <div class="price"><?= formatMoney($p['precio_venta']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Comanda -->
  <div class="card" style="display:flex;flex-direction:column;max-height:calc(100vh - 140px)">
    <div style="padding:14px;border-bottom:1px solid var(--border)">
      <div class="fw-bold" style="font-size:15px"><i class="fas fa-clipboard-list text-accent"></i> Comanda</div>
      <div class="text-muted fs-sm"><?= date('H:i', strtotime($orden['created_at'])) ?> — <?= htmlspecialchars($orden['mesero_nombre']) ?></div>
    </div>
    <div style="flex:1;overflow-y:auto;padding:8px">
      <?php if($items): ?>
        <?php foreach($items as $item): ?>
        <div style="background:var(--card2);border-radius:8px;padding:10px;margin-bottom:6px">
          <div style="display:flex;justify-content:space-between;align-items:flex-start">
            <div style="flex:1">
              <div class="fw-bold fs-sm"><?= htmlspecialchars($item['prod_nombre']) ?></div>
              <div class="text-muted fs-sm"><?= number_format($item['cantidad'],1) ?> × <?= formatMoney($item['precio_unitario']) ?></div>
            </div>
            <div style="text-align:right">
              <div class="fw-bold text-accent"><?= formatMoney($item['precio_unitario']*$item['cantidad']) ?></div>
              <?php $estadoColors=['pendiente'=>'warning','en_cocina'=>'info','listo'=>'success','entregado'=>'muted']; ?>
              <span class="badge badge-<?= $estadoColors[$item['estado']]??'muted' ?> fs-sm"><?= ucfirst(str_replace('_',' ',$item['estado'])) ?></span>
            </div>
          </div>
          <div style="display:flex;gap:4px;margin-top:6px">
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="cambiar_estado_item">
              <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
              <select name="estado" class="form-control" style="font-size:11px;padding:3px 6px;height:28px" onchange="this.form.submit()">
                <option value="pendiente" <?= $item['estado']==='pendiente'?'selected':'' ?>>Pendiente</option>
                <option value="en_cocina" <?= $item['estado']==='en_cocina'?'selected':'' ?>>En Cocina</option>
                <option value="listo" <?= $item['estado']==='listo'?'selected':'' ?>>Listo</option>
                <option value="entregado" <?= $item['estado']==='entregado'?'selected':'' ?>>Entregado</option>
              </select>
            </form>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="remove_item">
              <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
              <button class="btn btn-sm btn-danger btn-icon" type="submit" onclick="return confirm('¿Eliminar?')"><i class="fas fa-times"></i></button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
      <p class="text-muted text-center" style="padding:30px">Sin productos. Agrega desde el menú.</p>
      <?php endif; ?>
    </div>
    <div style="padding:14px;border-top:1px solid var(--border)">
      <div class="total-row mb-3"><span class="text-muted">Subtotal Comanda:</span><span class="fw-bold"><?= formatMoney($subtotal) ?></span></div>
      <?php if(strtolower(getUserRole()) !== 'mesero'): ?>
      <a href="../cajero/pos.php?mesa=<?= $orden['mesa_id'] ?>" class="btn btn-success btn-block mb-2">
        <i class="fas fa-cash-register"></i> Cobrar Mesa
      </a>
      <?php endif; ?>
      <form method="POST" onsubmit="return confirm('¿Marcar orden como cobrada y liberar la mesa?')">
        <input type="hidden" name="action" value="cerrar_orden">
        <button type="submit" class="btn btn-secondary btn-block"><i class="fas fa-check-circle"></i> Liberar Mesa</button>
      </form>
    </div>
  </div>
</div>

<!-- Modal agregar item -->
<div class="modal-overlay" id="modalItem">
  <div class="modal-box" style="max-width:380px">
    <div class="modal-header">
      <span class="modal-title" id="itemModalTitle">Agregar Producto</span>
      <button class="modal-close" onclick="closeModal('modalItem')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add_item">
      <input type="hidden" name="producto_id" id="itemProdId">
      <input type="hidden" name="precio" id="itemPrecio">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Cantidad</label>
          <input type="number" name="cantidad" id="itemCant" class="form-control" value="1" min="1" step="0.5">
        </div>
        <div class="form-group">
          <label class="form-label">Notas / Personalización</label>
          <input type="text" name="notas" class="form-control" placeholder="Sin cebolla, bien cocido...">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalItem')">Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Agregar</button>
      </div>
    </form>
  </div>
</div>

<script>
let currentCat = 0;
function agregarItem(id, name, price) {
  document.getElementById('itemProdId').value = id;
  document.getElementById('itemPrecio').value = price;
  document.getElementById('itemModalTitle').textContent = name;
  document.getElementById('itemCant').value = 1;
  openModal('modalItem');
}
function filtCat(id, btn) {
  currentCat = id;
  document.querySelectorAll('.cat-btn').forEach(b => { b.classList.remove('btn-primary'); b.classList.add('btn-secondary'); });
  btn.classList.add('btn-primary'); btn.classList.remove('btn-secondary');
  filtrarProd();
}
function filtrarProd() {
  const q = document.getElementById('buscarProd').value.toLowerCase();
  document.querySelectorAll('#prodGrid .product-card').forEach(c => {
    const match = (!currentCat || parseInt(c.dataset.cat)===currentCat) && (!q || c.dataset.name.toLowerCase().includes(q));
    c.style.display = match ? '' : 'none';
  });
}
</script>

<?php require_once '../../includes/footer.php'; ?>
