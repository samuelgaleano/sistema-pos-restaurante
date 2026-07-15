<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireLogin();

$db  = getDB();
$id  = (int)($_GET['id'] ?? 0);

$factura = $db->prepare("
    SELECT f.*, u.nombre as cajero, u.apellido as cajero_ap,
           c.nombre as cliente_n, c.apellido as cliente_ap, c.num_doc as cliente_doc,
           c.tipo_doc, m.numero as mesa_num, m.nombre as mesa_nombre
    FROM facturas f
    LEFT JOIN usuarios u ON f.cajero_id=u.id
    LEFT JOIN clientes c ON f.cliente_id=c.id
    LEFT JOIN mesas m ON f.mesa_id=m.id
    WHERE f.id=?
");
$factura->execute([$id]);
$f = $factura->fetch();

if (!$f) { die('<p>Factura no encontrada.</p>'); }

$items = $db->prepare("SELECT * FROM factura_items WHERE factura_id=? ORDER BY id");
$items->execute([$id]);
$items = $items->fetchAll();

$empresa = [
    'nombre'    => getConfig('empresa_nombre','Mi Negocio'),
    'nit'       => getConfig('empresa_nit',''),
    'direccion' => getConfig('empresa_direccion',''),
    'telefono'  => getConfig('empresa_telefono',''),
    'ciudad'    => getConfig('empresa_ciudad',''),
    'email'     => getConfig('empresa_email',''),
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Factura <?= htmlspecialchars($f['numero_factura']) ?></title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: 'Courier New', monospace; font-size:12px; background:#fff; color:#000; width:80mm; margin:0 auto; padding:8px; }
  .center { text-align:center; }
  .bold { font-weight:bold; }
  .line { border-top:1px dashed #000; margin:6px 0; }
  .row { display:flex; justify-content:space-between; margin:2px 0; }
  .row-3 { display:flex; }
  .row-3 .col-name { flex:1; }
  .row-3 .col-qty  { width:30px; text-align:center; }
  .row-3 .col-price{ width:60px; text-align:right; }
  .row-3 .col-total{ width:70px; text-align:right; }
  h1 { font-size:16px; }
  h2 { font-size:13px; }
  .total-final { font-size:14px; font-weight:bold; }
  .estado { background:#000; color:#fff; padding:2px 8px; display:inline-block; }
  @media print {
    body { margin:0; width:80mm; }
    .no-print { display:none; }
  }
</style>
</head>
<body>

<div class="center">
  <h1><?= htmlspecialchars($empresa['nombre']) ?></h1>
  <?php if($empresa['nit']): ?><p>NIT: <?= htmlspecialchars($empresa['nit']) ?></p><?php endif; ?>
  <?php if($empresa['direccion']): ?><p><?= htmlspecialchars($empresa['direccion']) ?></p><?php endif; ?>
  <?php if($empresa['ciudad']): ?><p><?= htmlspecialchars($empresa['ciudad']) ?></p><?php endif; ?>
  <?php if($empresa['telefono']): ?><p>Tel: <?= htmlspecialchars($empresa['telefono']) ?></p><?php endif; ?>
</div>

<div class="line"></div>

<div class="center">
  <h2>FACTURA DE VENTA</h2>
  <p class="bold"><?= htmlspecialchars($f['numero_factura']) ?></p>
  <?php if($f['estado']==='anulada'): ?>
  <span class="estado">*** ANULADA ***</span>
  <?php endif; ?>
</div>

<div class="line"></div>

<div class="row"><span>Fecha:</span><span><?= date('d/m/Y H:i', strtotime($f['fecha'])) ?></span></div>
<div class="row"><span>Cajero:</span><span><?= htmlspecialchars($f['cajero'].' '.$f['cajero_ap']) ?></span></div>
<?php if($f['mesa_num']): ?>
<div class="row"><span>Mesa:</span><span><?= htmlspecialchars($f['mesa_nombre'] ?: 'Mesa '.$f['mesa_num']) ?></span></div>
<?php endif; ?>
<?php if($f['cliente_n'] && $f['cliente_n'] !== 'Cliente'): ?>
<div class="row"><span>Cliente:</span><span><?= htmlspecialchars($f['cliente_n'].' '.($f['cliente_ap']??'')) ?></span></div>
<?php if($f['cliente_doc']): ?><div class="row"><span><?= $f['tipo_doc'] ?>:</span><span><?= htmlspecialchars($f['cliente_doc']) ?></span></div><?php endif; ?>
<?php endif; ?>
<?php if($f['metodo_pago']): ?>
<div class="row"><span>Pago:</span><span><?= ucfirst($f['metodo_pago']) ?></span></div>
<?php endif; ?>

<div class="line"></div>

<!-- Encabezado items -->
<div class="row-3 bold">
  <span class="col-name">Producto</span>
  <span class="col-qty">Cant</span>
  <span class="col-price">Precio</span>
  <span class="col-total">Total</span>
</div>
<div class="line"></div>

<?php foreach($items as $item): ?>
<div class="row-3">
  <span class="col-name"><?= htmlspecialchars(mb_substr($item['nombre_producto'],0,18)) ?></span>
  <span class="col-qty"><?= number_format($item['cantidad'],1) ?></span>
  <span class="col-price"><?= number_format($item['precio_unitario'],0,'','.') ?></span>
  <span class="col-total"><?= number_format($item['subtotal'],0,'','.') ?></span>
</div>
<?php if(strlen($item['nombre_producto'])>18): ?>
<div style="padding-left:2px;font-size:11px;color:#444"><?= htmlspecialchars(mb_substr($item['nombre_producto'],18)) ?></div>
<?php endif; ?>
<?php endforeach; ?>

<div class="line"></div>

<div class="row"><span>Subtotal:</span><span>$ <?= number_format($f['subtotal'],0,'','.') ?></span></div>
<?php if($f['descuento_valor'] > 0): ?>
<div class="row"><span>Descuento (<?= $f['descuento_porcentaje'] ?>%):</span><span>- $ <?= number_format($f['descuento_valor'],0,'','.') ?></span></div>
<?php endif; ?>
<?php if($f['impuesto_valor'] > 0): ?>
<div class="row"><span><?= getConfig('impuesto_nombre','IVA') ?> (<?= $f['impuesto_porcentaje'] ?>%):</span><span>$ <?= number_format($f['impuesto_valor'],0,'','.') ?></span></div>
<?php endif; ?>
<?php if($f['propina'] > 0): ?>
<div class="row"><span>Propina:</span><span>$ <?= number_format($f['propina'],0,'','.') ?></span></div>
<?php endif; ?>

<div class="line"></div>
<div class="row total-final"><span>TOTAL:</span><span>$ <?= number_format($f['total'],0,'','.') ?></span></div>

<?php if($f['metodo_pago']==='efectivo' && $f['pago_efectivo'] > 0): ?>
<div class="row"><span>Efectivo recibido:</span><span>$ <?= number_format($f['pago_efectivo'],0,'','.') ?></span></div>
<div class="row"><span>Cambio:</span><span>$ <?= number_format($f['cambio'],0,'','.') ?></span></div>
<?php endif; ?>

<div class="line"></div>

<?php if($f['notas']): ?>
<p style="font-size:11px">Nota: <?= htmlspecialchars($f['notas']) ?></p>
<div class="line"></div>
<?php endif; ?>

<div class="center" style="margin-top:8px">
  <p>¡Gracias por su preferencia!</p>
  <?php if($empresa['email']): ?><p style="font-size:10px"><?= htmlspecialchars($empresa['email']) ?></p><?php endif; ?>
  <p style="font-size:10px;margin-top:4px">Sistema POS Pro</p>
</div>

<div class="center no-print" style="margin-top:16px">
  <button onclick="window.print()" style="padding:8px 24px;font-size:14px;cursor:pointer;background:#000;color:#fff;border:none;border-radius:6px">
    🖨️ Imprimir
  </button>
  &nbsp;
  <button onclick="window.close()" style="padding:8px 16px;font-size:14px;cursor:pointer;background:#666;color:#fff;border:none;border-radius:6px">
    ✕ Cerrar
  </button>
</div>

<script>
// Auto-imprimir al abrir
window.onload = function() {
  const params = new URLSearchParams(window.location.search);
  if (params.get('auto') === '1') window.print();
};
</script>
</body>
</html>
