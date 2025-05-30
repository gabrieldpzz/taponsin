<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/header.php';

$userId = $_SESSION['firebase_uid'] ?? null;
if (!$userId) {
    echo "Debes iniciar sesión para ver el carrito.";
    exit;
}

$stmt = $pdo->prepare("
    SELECT c.*, p.nombre, p.precio, p.imagen 
    FROM carrito c 
    JOIN productos p ON c.producto_id = p.id 
    WHERE c.usuario_id = ?");
$stmt->execute([$userId]);
$carrito = $stmt->fetchAll();
?>

<link rel="stylesheet" href="/assets/css/carrito-index.css">

<div class="cart-container">
    <div class="page-header">
        <h2 class="page-title">Tu Carrito</h2>
    </div>

    <?php if (isset($_GET['mensaje'])): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($_GET['mensaje']) ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($_GET['error']) ?>
        </div>
    <?php endif; ?>

    <?php if (count($carrito) > 0): ?>
        <div class="cart-content">
            <div class="cart-items-card">
                <div class="table-container">
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Precio</th>
                                <th>Variante</th>
                                <th>Total</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $total = 0; ?>
                            <?php foreach ($carrito as $item): ?>
                                <?php $subtotal = $item['precio'] * $item['cantidad']; ?>
                                <?php $total += $subtotal; ?>
                                <tr class="cart-item">
                                    <td class="product-info">
                                        <div class="product-details">
                                            <?php if (!empty($item['imagen'])): ?>
                                                <img src="<?= htmlspecialchars($item['imagen']) ?>" alt="<?= htmlspecialchars($item['nombre']) ?>" class="product-image">
                                            <?php endif; ?>
                                            <span class="product-name"><?= htmlspecialchars($item['nombre']) ?></span>
                                        </div>
                                    </td>
                                    <td class="quantity">
                                        <span class="quantity-badge"><?= $item['cantidad'] ?></span>
                                    </td>
                                    <td class="price">$<?= number_format($item['precio'], 2) ?></td>
                                    <td class="variant">
                                        <?php if (!empty($item['variante'])): ?>
                                            <div class="variant-list">
                                                <?php foreach (explode(',', $item['variante']) as $v): ?>
                                                    <span class="variant-tag"><?= htmlspecialchars(trim($v)) ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="no-variant">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="subtotal">$<?= number_format($subtotal, 2) ?></td>
                                    <td class="actions">
                                        <form method="post" action="../actions/remove_from_cart.php" class="remove-form">
                                            <input type="hidden" name="product_id" value="<?= $item['producto_id'] ?>">
                                            <input type="hidden" name="variant" value="<?= htmlspecialchars($item['variante']) ?>">
                                            <button type="button" class="btn btn-danger btn-small btn-show-confirm">
                                                <span class="btn-icon">🗑️</span>
                                                Eliminar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="4" class="total-label">Total:</td>
                                <td colspan="2" class="total-amount">$<?= number_format($total, 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="cart-actions">
                <div class="action-buttons">
                    <a href="../productos/index.php" class="btn btn-secondary">
                        <span class="btn-icon">🛍️</span>
                        Seguir comprando
                    </a>
                    <a href="checkout.php" class="btn btn-primary btn-large">
                        <span class="btn-icon">💳</span>
                        Proceder al pago
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="empty-cart">
            <div class="empty-cart-icon">🛒</div>
            <h3 class="empty-cart-title">Tu carrito está vacío</h3>
            <p class="empty-cart-message">¡Agrega algunos productos para comenzar tu compra!</p>
            <a href="../productos/index.php" class="btn btn-primary">
                <span class="btn-icon">🛍️</span>
                Explorar productos
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Alerta personalizada para eliminar producto -->
<div id="customConfirm" class="alert alert-error" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.25); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:white; border-radius:12px; padding:32px 24px; max-width:90vw; width:350px; box-shadow:0 4px 24px rgba(0,0,0,0.15); text-align:center;">
        <div style="font-size:2.5rem; margin-bottom:12px;">🗑️</div>
        <div style="font-size:1.1rem; color:#8b2635; margin-bottom:18px;">¿Estás seguro de eliminar este producto?</div>
        <div style="display:flex; gap:16px; justify-content:center;">
            <button id="btnCancelConfirm" class="btn btn-secondary btn-small">Cancelar</button>
            <button id="btnOkConfirm" class="btn btn-danger btn-small">Eliminar</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let formToSubmit = null;
    // Botón eliminar
    document.querySelectorAll('.btn-show-confirm').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            formToSubmit = btn.closest('form');
            document.getElementById('customConfirm').style.display = 'flex';
        });
    });
    // Cancelar
    document.getElementById('btnCancelConfirm').onclick = function() {
        document.getElementById('customConfirm').style.display = 'none';
        formToSubmit = null;
    };
    // Confirmar
    document.getElementById('btnOkConfirm').onclick = function() {
        if (formToSubmit) formToSubmit.submit();
        document.getElementById('customConfirm').style.display = 'none';
    };
    // Cerrar al hacer click fuera
    document.getElementById('customConfirm').addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
            formToSubmit = null;
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>