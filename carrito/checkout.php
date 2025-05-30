<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

$userId = $_SESSION['firebase_uid'] ?? null;
if (!$userId) {
    echo "Inicia sesión para continuar.";
    exit;
}

// Obtener ítems del carrito
$stmt = $pdo->prepare("SELECT c.*, p.nombre, p.precio, p.imagen FROM carrito c 
                       JOIN productos p ON c.producto_id = p.id 
                       WHERE c.usuario_id = ?");
$stmt->execute([$userId]);
$items = $stmt->fetchAll();

if (!$items) {
    echo "<p>No tienes productos en el carrito.</p>";
    require_once '../includes/footer.php';
    exit;
}

// Calcular subtotal total
$total = 0;
foreach ($items as $item) {
    $total += $item['precio'] * $item['cantidad'];
}

// Validar cupón si se envió
$descuento = 0;
$codigo = $_GET['codigo'] ?? null;
$mensaje = null;

if ($codigo) {
    $stmt = $pdo->prepare("SELECT * FROM cupones WHERE codigo = ? AND usados < uso_maximo AND fecha_expiracion >= CURDATE()");
    $stmt->execute([$codigo]);
    $cupon = $stmt->fetch();

    if ($cupon) {
        $descuento = $total * ($cupon['descuento_porcentaje'] / 100);
        $mensaje = "Cupón aplicado: {$cupon['descuento_porcentaje']}% de descuento.";
    } else {
        $mensaje = "Cupón inválido, vencido o ya utilizado.";
    }
}

$sucursales = $pdo->query("SELECT * FROM sucursales")->fetchAll();

$departamentos = $pdo->query("SELECT * FROM departamentos")->fetchAll(PDO::FETCH_ASSOC);
$municipios = $pdo->query("SELECT * FROM municipios")->fetchAll(PDO::FETCH_ASSOC);

?>

<link rel="stylesheet" href="/assets/css/carrito.css">

<div class="checkout-container">
    <div class="page-header">
        <h2 class="page-title">Resumen del Pedido</h2>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert <?= $descuento > 0 ? 'alert-success' : 'alert-error' ?>">
            <?= $mensaje ?>
        </div>
    <?php endif; ?>

    <div class="order-summary-card">
        <div class="table-container">
            <table class="order-table">
                <thead>
                    <tr>
                        <th>Foto</th>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Precio</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <img src="<?= htmlspecialchars($item['imagen']) ?>" alt="<?= htmlspecialchars($item['nombre']) ?>" style="width:60px; height:60px; object-fit:cover; border-radius:6px; border:1px solid #e5e7eb;">
                        </td>
                        <td class="product-name"><?= htmlspecialchars($item['nombre']) ?></td>
                        <td class="quantity"><?= $item['cantidad'] ?></td>
                        <td class="price"><?= formatearPrecio($item['precio']) ?></td>
                        <td class="subtotal"><?= formatearPrecio($item['precio'] * $item['cantidad']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="3" class="total-label">Total sin descuento:</td>
                        <td class="total-amount"><?= formatearPrecio($total) ?></td>
                    </tr>
                    <?php if ($descuento > 0): ?>
                    <tr class="discount-row">
                        <td colspan="3" class="total-label">Descuento:</td>
                        <td class="discount-amount">-<?= formatearPrecio($descuento) ?></td>
                    </tr>
                    <tr class="final-total-row">
                        <td colspan="3" class="total-label">Total con descuento:</td>
                        <td class="final-total-amount"><?= formatearPrecio($total - $descuento) ?></td>
                    </tr>
                    <?php endif; ?>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="coupon-section">
        <h3 class="section-title">Código de Descuento</h3>
        <form method="get" class="coupon-form">
            <div class="form-group">
                <label for="codigo" class="form-label">Código de cupón (opcional):</label>
                <div class="input-group">
                    <input type="text" name="codigo" id="codigo" class="form-input" placeholder="Ingresa tu código">
                    <button type="submit" class="btn btn-secondary">Aplicar cupón</button>
                </div>
            </div>
        </form>
    </div>

    <div class="checkout-section">
        <h3 class="section-title">Finalizar Compra</h3>
        
        <form method="post" action="../actions/create_order.php" class="checkout-form">
            <input type="hidden" name="entrega" id="entrega_tipo" value="">

            <div class="delivery-options">
                <label class="form-label">Selecciona tipo de entrega:</label>
                <div class="delivery-buttons">
                    <button type="button" class="btn btn-outline" onclick="seleccionarEntrega('domicilio')">
                        <span class="btn-icon">🏠</span>
                        Entrega a domicilio
                    </button>
                    <button type="button" class="btn btn-outline" onclick="seleccionarEntrega('sucursal')">
                        <span class="btn-icon">🏪</span>
                        Recoger en sucursal
                    </button>
                </div>
            </div>

            <!-- Formulario DOMICILIO -->
            <div id="domicilio" class="delivery-form" style="display:none;">
                <div class="form-card">
                    <h4 class="card-title">Dirección para entrega</h4>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="dir_departamento" class="form-label">Departamento:</label>
                            <select name="dir_departamento" id="dir_departamento" class="form-select" required>
                                <option value="">-- Selecciona --</option>
                                <?php foreach ($departamentos as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="dir_municipio" class="form-label">Municipio:</label>
                            <select name="dir_municipio" id="dir_municipio" class="form-select" required>
                                <option value="">-- Selecciona el departamento primero --</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="dir_colonia" class="form-label">Colonia / Residencial:</label>
                        <input type="text" name="dir_colonia" id="dir_colonia" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label for="dir_calle" class="form-label">Calle / Avenida:</label>
                        <input type="text" name="dir_calle" id="dir_calle" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label for="dir_numero" class="form-label">Número de casa o lote:</label>
                        <input type="text" name="dir_numero" id="dir_numero" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label for="dir_referencia" class="form-label">Referencias adicionales:</label>
                        <textarea name="dir_referencia" id="dir_referencia" rows="3" class="form-textarea" placeholder="Puntos de referencia, indicaciones especiales..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Formulario SUCURSAL -->
            <div id="sucursal" class="delivery-form" style="display:none;">
                <div class="form-card">
                    <h4 class="card-title">Seleccionar Sucursal</h4>
                    
                    <div class="form-group">
                        <label for="sucursal_id" class="form-label">Sucursal:</label>
                        <select name="sucursal_id" id="sucursal_id" class="form-select">
                            <option value="">-- Selecciona --</option>
                            <?php foreach ($sucursales as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nombre']) ?> - <?= htmlspecialchars($s['direccion']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="comentarios" class="form-label">Comentarios (opcional):</label>
                        <textarea name="comentarios" id="comentarios" rows="3" class="form-textarea" placeholder="Instrucciones especiales para el pedido..."></textarea>
                    </div>
                </div>
            </div>

            <?php if ($codigo): ?>
            <input type="hidden" name="codigo" value="<?= htmlspecialchars($codigo) ?>">
            <?php endif; ?>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-large">
                    <span class="btn-icon">✓</span>
                    Confirmar pedido
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function seleccionarEntrega(tipo) {
    document.getElementById('entrega_tipo').value = tipo;
    document.getElementById('domicilio').style.display = (tipo === 'domicilio') ? 'block' : 'none';
    document.getElementById('sucursal').style.display = (tipo === 'sucursal') ? 'block' : 'none';

    // Actualizar estilos de botones
    const buttons = document.querySelectorAll('.delivery-buttons .btn');
    buttons.forEach(btn => btn.classList.remove('btn-active'));
    
    if (tipo === 'domicilio') {
        buttons[0].classList.add('btn-active');
    } else if (tipo === 'sucursal') {
        buttons[1].classList.add('btn-active');
    }

    // Hacer requeridos solo los campos visibles
    document.getElementById('dir_departamento').required = (tipo === 'domicilio');
    document.getElementById('dir_municipio').required = (tipo === 'domicilio');
    document.getElementsByName('dir_colonia')[0].required = (tipo === 'domicilio');
    document.getElementsByName('dir_calle')[0].required = (tipo === 'domicilio');
    document.getElementsByName('dir_numero')[0].required = (tipo === 'domicilio');
    document.getElementById('sucursal_id').required = (tipo === 'sucursal');
}

document.addEventListener('DOMContentLoaded', function () {
    const municipios = <?= json_encode($municipios) ?>;
    const deptoSelect = document.getElementById('dir_departamento');
    const municipioSelect = document.getElementById('dir_municipio');

    if (deptoSelect) {
        deptoSelect.addEventListener('change', function () {
            const deptoId = this.value;
            municipioSelect.innerHTML = '<option value="">-- Selecciona --</option>';
            municipios.forEach(m => {
                if (m.departamento_id == deptoId) {
                    const opt = document.createElement('option');
                    opt.value = m.id;
                    opt.textContent = m.nombre;
                    municipioSelect.appendChild(opt);
                }
            });
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>