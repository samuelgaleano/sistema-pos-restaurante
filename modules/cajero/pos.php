<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireRole(['Administrador','Cajero']);

$db = getDB();
$pageTitle = 'Punto de Venta';
$activeMenu = 'pos';

// Verificar turno activo
$turno = getTurnoActivo();

// Categorías y productos
$categorias = $db->query("SELECT * FROM categorias WHERE activo=1 ORDER BY orden, nombre")->fetchAll();
$productos   = $db->query("SELECT p.*, c.nombre as cat_nombre, u.abreviatura as unidad FROM productos p LEFT JOIN categorias c ON p.categoria_id=c.id LEFT JOIN unidades_medida u ON p.unidad_id=u.id WHERE p.activo=1 ORDER BY p.nombre")->fetchAll();
$clientes    = $db->query("SELECT id, CONCAT(nombre,' ',COALESCE(apellido,'')) as nombre_completo, num_doc FROM clientes WHERE activo=1 ORDER BY nombre")->fetchAll();
$mesas       = $db->query("SELECT * FROM mesas WHERE activo=1 AND estado IN ('disponible','ocupada') ORDER BY numero")->fetchAll();

require_once '../../includes/header.php';
?>

<?php if (!$turno): ?>
<!-- Sin turno abierto -->
<div style="max-width:480px;margin:80px auto;text-align:center">
  <div style="font-size:64px;margin-bottom:20px">🕐</div>
  <h2 style="margin-bottom:8px">No hay turno activo</h2>
  <p class="text-muted mb-4">Debes abrir un turno de caja antes de realizar ventas.</p>
  <button class="btn btn-primary btn-lg" onclick="openModal('modalAbrirTurno')">
    <i class="fas fa-lock-open"></i> Abrir Turno de Caja
  </button>
</div>

<!-- Modal Abrir Turno -->
<div class="modal-overlay" id="modalAbrirTurno">
  <div class="modal-box">
    <div class="modal-header">
      <span class="modal-title">Abrir Turno de Caja</span>
      <button class="modal-close" onclick="closeModal('modalAbrirTurno')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" action="../../ajax/turnos.php">
      <input type="hidden" name="action" value="abrir">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Monto Inicial en Caja</label>
          <input type="number" name="monto_inicial" class="form-control" placeholder="0.00" min="0" step="0.01" required>
        </div>
        <div class="form-group">
          <label class="form-label">Notas (opcional)</label>
          <textarea name="notas" class="form-control" placeholder="Observaciones del turno..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalAbrirTurno')">Cancelar</button>
        <button type="submit" class="btn btn-success"><i class="fas fa-lock-open"></i> Abrir Turno</button>
      </div>
    </form>
  </div>
</div>
<?php else: ?>

<!-- POS Interface -->
<div class="pos-layout">
  <!-- LEFT: Products -->
  <div class="pos-products">
    <!-- Search & Filters -->
    <div class="card" style="padding:12px">
      <div style="display:flex;gap:10px;align-items:center">
        <div class="input-group" style="flex:1">
          <input type="text" id="searchProd" class="form-control" placeholder="🔍 Buscar producto o código..." oninput="filterProducts()">
        </div>
      </div>
      <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:10px">
        <button class="btn btn-sm btn-primary cat-btn active" data-cat="0" onclick="filterCat(0,this)">Todos</button>
        <?php foreach($categorias as $cat): ?>
        <button class="btn btn-sm btn-secondary cat-btn" data-cat="<?= $cat['id'] ?>" onclick="filterCat(<?= $cat['id'] ?>,this)">
          <i class="<?= htmlspecialchars($cat['icono']) ?>"></i> <?= htmlspecialchars($cat['nombre']) ?>
        </button>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Products Grid -->
    <div class="products-grid" id="productsGrid">
      <?php foreach($productos as $p): ?>
      <div class="product-card" 
           data-id="<?= $p['id'] ?>"
           data-name="<?= htmlspecialchars($p['nombre']) ?>"
           data-price="<?= $p['precio_venta'] ?>"
           data-cat="<?= $p['categoria_id'] ?>"
           data-stock="<?= $p['stock_actual'] ?>"
           data-tiene-stock="<?= $p['tiene_stock'] ?>"
           onclick="addToCart(<?= $p['id'] ?>, '<?= addslashes($p['nombre']) ?>', <?= $p['precio_venta'] ?>, <?= (int)$p['tiene_stock'] ?>, <?= $p['stock_actual'] ?>)">
        <div class="icon">
          <?php $icons=['1'=>'🥗','2'=>'🍽️','3'=>'🥤','4'=>'🍰','5'=>'➕']; echo $icons[$p['categoria_id']] ?? '📦'; ?>
        </div>
        <div class="name"><?= htmlspecialchars($p['nombre']) ?></div>
        <div class="price"><?= formatMoney($p['precio_venta']) ?></div>
        <?php if($p['tiene_stock']): ?>
        <div class="stock <?= $p['stock_actual'] <= $p['stock_minimo'] ? 'text-danger' : 'text-muted' ?>">
          Stock: <?= number_format($p['stock_actual'],0) ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- RIGHT: Cart -->
  <div class="pos-cart">
    <div class="cart-header">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
        <span class="fw-bold" style="font-size:16px"><i class="fas fa-shopping-cart text-accent"></i> Carrito</span>
        <button class="btn btn-sm btn-danger" onclick="clearCart()"><i class="fas fa-trash"></i></button>
      </div>
      <div style="display:flex;gap:8px">
        <select class="form-control" id="selCliente" style="flex:1" onchange="updateCliente()">
          <?php foreach($clientes as $c): ?>
          <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre_completo']) ?></option>
          <?php endforeach; ?>
        </select>
        <select class="form-control" id="selMesa" style="width:110px">
          <option value="">Sin Mesa</option>
          <?php foreach($mesas as $m): ?>
          <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nombre'] ?: 'Mesa '.$m['numero']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="cart-items" id="cartItems">
      <div id="emptyCart" style="text-align:center;padding:40px 16px;color:var(--muted)">
        <i class="fas fa-shopping-cart" style="font-size:36px;display:block;margin-bottom:10px;opacity:.3"></i>
        Agrega productos al carrito
      </div>
    </div>

    <div class="cart-totals">
      <div class="total-row"><span class="text-muted">Subtotal</span><span id="subtotal">$ 0</span></div>
      <div class="total-row">
        <span class="text-muted">Descuento</span>
        <span>
          <input type="number" id="descPct" min="0" max="100" value="0" style="width:48px;background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:2px 6px;color:var(--text);text-align:center" onchange="calcTotals()"> %
        </span>
      </div>
      <div class="total-row"><span class="text-muted">IVA (<?= getConfig('impuesto_porcentaje','0') ?>%)</span><span id="iva">$ 0</span></div>
      <div class="total-row"><span class="text-muted">Propina</span>
        <span><input type="number" id="propina" value="0" min="0" style="width:80px;background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:2px 6px;color:var(--text);text-align:right" onchange="calcTotals()"></span>
      </div>
      <div class="total-row grand"><span>TOTAL</span><span id="totalFinal">$ 0</span></div>
    </div>

    <div class="cart-actions">
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px">
        <button class="btn btn-secondary" onclick="setMetodoPago('efectivo')" id="btnEfectivo" style="background:rgba(46,204,113,.15);color:var(--success);border-color:var(--success)">
          <i class="fas fa-money-bill-wave"></i> Efectivo
        </button>
        <button class="btn btn-secondary" onclick="setMetodoPago('tarjeta')" id="btnTarjeta">
          <i class="fas fa-credit-card"></i> Tarjeta
        </button>
        <button class="btn btn-secondary" onclick="setMetodoPago('transferencia')" id="btnTransf">
          <i class="fas fa-university"></i> Transfer
        </button>
      </div>
      <button class="btn btn-primary btn-lg btn-block" onclick="procesarVenta()">
        <i class="fas fa-check-circle"></i> Cobrar — <span id="totalBtn">$ 0</span>
      </button>
    </div>
  </div>
</div>

<!-- Modal Pago en Efectivo -->
<div class="modal-overlay" id="modalPago">
  <div class="modal-box" style="max-width:400px">
    <div class="modal-header">
      <span class="modal-title"><i class="fas fa-cash-register text-accent"></i> Procesar Pago</span>
      <button class="modal-close" onclick="closeModal('modalPago')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <div style="text-align:center;margin-bottom:20px">
        <div style="font-size:13px;color:var(--muted);margin-bottom:4px">Total a Cobrar</div>
        <div style="font-size:36px;font-weight:800;color:var(--accent)" id="totalCobrar">$ 0</div>
      </div>
      <div id="efectivoFields">
        <div class="form-group">
          <label class="form-label">Pago con Efectivo</label>
          <input type="number" id="pagoEfectivo" class="form-control" placeholder="0" oninput="calcCambio()" style="font-size:20px;text-align:right">
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:var(--surface);border-radius:8px">
          <span class="text-muted">Cambio</span>
          <span id="cambioVal" style="font-size:20px;font-weight:800;color:var(--success)">$ 0</span>
        </div>
        <div style="display:flex;gap:6px;margin-top:10px;flex-wrap:wrap">
          <?php foreach([5000,10000,20000,50000,100000] as $b): ?>
          <button class="btn btn-secondary btn-sm" onclick="document.getElementById('pagoEfectivo').value=<?= $b ?>;calcCambio()">$ <?= number_format($b,0,'','.') ?></button>
          <?php endforeach; ?>
          <button class="btn btn-secondary btn-sm" onclick="setExacto()">Exacto</button>
        </div>
      </div>
      <div id="tarjetaFields" class="d-none">
        <div class="form-group">
          <label class="form-label">Referencia de Pago</label>
          <input type="text" id="refTarjeta" class="form-control" placeholder="Número de aprobación...">
        </div>
      </div>
      <div class="form-group mt-3">
        <label class="form-label">Notas (opcional)</label>
        <input type="text" id="notasVenta" class="form-control" placeholder="Nota para la factura...">
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('modalPago')">Cancelar</button>
      <button class="btn btn-success btn-lg" onclick="confirmarVenta()">
        <i class="fas fa-check"></i> Confirmar Venta
      </button>
    </div>
  </div>
</div>

<!-- Modal Imprimir Factura -->
<div class="modal-overlay" id="modalFactura">
  <div class="modal-box" style="max-width:420px">
    <div class="modal-header">
      <span class="modal-title">✅ Venta Exitosa</span>
      <button class="modal-close" onclick="nuevaVenta()"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body" id="facturaContent" style="text-align:center"></div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="nuevaVenta()"><i class="fas fa-plus"></i> Nueva Venta</button>
      <button class="btn btn-primary" onclick="window.open(BASE_URL+'/modules/cajero/imprimir.php?id='+lastFacturaId,'_blank')"><i class="fas fa-print"></i> Imprimir</button>
    </div>
  </div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';
const IVA_PCT  = <?= (float)getConfig('impuesto_porcentaje', 0) ?>;
const TURNO_ID = <?= $turno['id'] ?>;
let cart = [];
let metodoPago = 'efectivo';
let lastFacturaId = null;

// ---- Cart ----
function addToCart(id, name, price, tieneStock, stock) {
  if (tieneStock && stock <= 0) { showToast('Sin stock disponible', 'danger'); return; }
  const i = cart.findIndex(x => x.id === id);
  if (i >= 0) {
    if (tieneStock && cart[i].qty >= stock) { showToast('Stock insuficiente', 'warning'); return; }
    cart[i].qty++;
  } else {
    cart.push({ id, name, price, tieneStock, stock, qty: 1 });
  }
  renderCart();
}

function renderCart() {
  const wrap = document.getElementById('cartItems');
  const empty = document.getElementById('emptyCart');
  if (!cart.length) {
    wrap.innerHTML = '';
    wrap.appendChild(empty);
    calcTotals();
    return;
  }
  empty.remove ? null : null;
  wrap.innerHTML = cart.map((item, i) => `
    <div class="cart-item">
      <div class="cart-item-name">${item.name}</div>
      <div class="cart-item-qty">
        <button class="qty-btn" onclick="changeQty(${i},-1)">−</button>
        <span class="qty-val">${item.qty}</span>
        <button class="qty-btn" onclick="changeQty(${i},1)">+</button>
      </div>
      <div class="cart-item-price">${formatMoney(item.price * item.qty)}</div>
      <button class="cart-remove" onclick="removeItem(${i})"><i class="fas fa-times"></i></button>
    </div>
  `).join('');
  calcTotals();
}

function changeQty(i, d) {
  cart[i].qty += d;
  if (cart[i].qty <= 0) cart.splice(i, 1);
  else if (cart[i].tieneStock && cart[i].qty > cart[i].stock) { cart[i].qty = cart[i].stock; showToast('Stock máximo','warning'); }
  renderCart();
}

function removeItem(i) { cart.splice(i,1); renderCart(); }
function clearCart() { cart = []; renderCart(); }

function calcTotals() {
  const subtotal = cart.reduce((s,i) => s + i.price * i.qty, 0);
  const descPct  = parseFloat(document.getElementById('descPct').value)||0;
  const descVal  = subtotal * descPct / 100;
  const base     = subtotal - descVal;
  const iva      = base * IVA_PCT / 100;
  const propina  = parseFloat(document.getElementById('propina').value)||0;
  const total    = base + iva + propina;

  document.getElementById('subtotal').textContent   = formatMoney(subtotal);
  document.getElementById('iva').textContent         = formatMoney(iva);
  document.getElementById('totalFinal').textContent  = formatMoney(total);
  document.getElementById('totalBtn').textContent    = formatMoney(total);
  document.getElementById('totalCobrar').textContent = formatMoney(total);
}

function formatMoney(n) {
  return '$ ' + Math.round(parseFloat(n||0)).toLocaleString('es-CO');
}

// ---- Metodo Pago ----
function setMetodoPago(m) {
  metodoPago = m;
  ['efectivo','tarjeta','transferencia'].forEach(x => {
    document.getElementById('btn'+x.charAt(0).toUpperCase()+x.slice(1)).style.background='';
    document.getElementById('btn'+x.charAt(0).toUpperCase()+x.slice(1)).style.borderColor='';
  });
  const btn = {efectivo:'btnEfectivo', tarjeta:'btnTarjeta', transferencia:'btnTransf'}[m];
  document.getElementById(btn).style.background='rgba(108,99,255,.25)';
  document.getElementById(btn).style.borderColor='var(--accent)';
}
setMetodoPago('efectivo');

function procesarVenta() {
  if (!cart.length) { showToast('El carrito está vacío','warning'); return; }
  document.getElementById('efectivoFields').classList.toggle('d-none', metodoPago !== 'efectivo');
  document.getElementById('tarjetaFields').classList.toggle('d-none', metodoPago === 'efectivo');
  if (metodoPago === 'efectivo') {
    const total = parseFloat(document.getElementById('totalFinal').textContent.replace(/[^0-9]/g,''));
    document.getElementById('pagoEfectivo').value = total;
    calcCambio();
  }
  openModal('modalPago');
}

function calcCambio() {
  const total = getTotal();
  const pago  = parseFloat(document.getElementById('pagoEfectivo').value)||0;
  const cambio = Math.max(0, pago - total);
  document.getElementById('cambioVal').textContent = formatMoney(cambio);
}

function setExacto() {
  document.getElementById('pagoEfectivo').value = getTotal();
  calcCambio();
}

function getTotal() {
  const subtotal = cart.reduce((s,i) => s + i.price * i.qty, 0);
  const descPct  = parseFloat(document.getElementById('descPct').value)||0;
  const descVal  = subtotal * descPct / 100;
  const base     = subtotal - descVal;
  const iva      = base * IVA_PCT / 100;
  const propina  = parseFloat(document.getElementById('propina').value)||0;
  return base + iva + propina;
}

async function confirmarVenta() {
  const total    = getTotal();
  const subtotal = cart.reduce((s,i) => s + i.price * i.qty, 0);
  const descPct  = parseFloat(document.getElementById('descPct').value)||0;
  const descVal  = subtotal * descPct / 100;
  const base     = subtotal - descVal;
  const iva      = base * IVA_PCT / 100;
  const propina  = parseFloat(document.getElementById('propina').value)||0;
  const pagoEfec = parseFloat(document.getElementById('pagoEfectivo')?.value)||0;

  const payload = {
    turno_id: TURNO_ID,
    cliente_id: document.getElementById('selCliente').value,
    mesa_id:   document.getElementById('selMesa').value || null,
    metodo_pago: metodoPago,
    subtotal, descuento_porcentaje: descPct, descuento_valor: descVal,
    impuesto_valor: iva, propina, total,
    pago_efectivo: metodoPago==='efectivo' ? pagoEfec : 0,
    pago_tarjeta:  metodoPago==='tarjeta'  ? total : 0,
    pago_transferencia: metodoPago==='transferencia' ? total : 0,
    cambio: Math.max(0, pagoEfec - total),
    notas: document.getElementById('notasVenta').value,
    items: cart.map(i => ({ producto_id:i.id, nombre:i.name, cantidad:i.qty, precio_unitario:i.price, subtotal:i.price*i.qty }))
  };

  try {
    const res = await fetch('../../ajax/ventas.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.success) {
      lastFacturaId = data.factura_id;
      closeModal('modalPago');
      document.getElementById('facturaContent').innerHTML = `
        <div style="font-size:48px;margin-bottom:12px">✅</div>
        <h3>${data.numero_factura}</h3>
        <p class="text-muted" style="margin:8px 0">Venta registrada exitosamente</p>
        <div style="background:var(--surface);border-radius:10px;padding:16px;margin-top:16px;text-align:left">
          <div style="display:flex;justify-content:space-between;margin-bottom:6px"><span class="text-muted">Total</span><span class="fw-bold">${formatMoney(total)}</span></div>
          ${metodoPago==='efectivo'?`<div style="display:flex;justify-content:space-between"><span class="text-muted">Cambio</span><span class="fw-bold text-success">${formatMoney(Math.max(0,pagoEfec-total))}</span></div>`:''}
        </div>
      `;
      openModal('modalFactura');
    } else {
      showToast(data.error || 'Error al procesar la venta', 'danger');
    }
  } catch(e) { showToast('Error de conexión', 'danger'); }
}

function nuevaVenta() {
  cart = [];
  renderCart();
  document.getElementById('descPct').value = 0;
  document.getElementById('propina').value = 0;
  closeModal('modalFactura');
}

// ---- Filters ----
let currentCat = 0;
function filterCat(catId, btn) {
  currentCat = catId;
  document.querySelectorAll('.cat-btn').forEach(b => { b.classList.remove('active'); b.classList.add('btn-secondary'); b.classList.remove('btn-primary'); });
  btn.classList.add('active','btn-primary'); btn.classList.remove('btn-secondary');
  filterProducts();
}

function filterProducts() {
  const q = document.getElementById('searchProd').value.toLowerCase();
  document.querySelectorAll('.product-card').forEach(card => {
    const name    = card.dataset.name.toLowerCase();
    const catMatch  = !currentCat || parseInt(card.dataset.cat) === currentCat;
    const textMatch = !q || name.includes(q);
    card.style.display = catMatch && textMatch ? '' : 'none';
  });
}
</script>

<?php endif; ?>
<?php require_once '../../includes/footer.php'; ?>
