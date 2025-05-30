<?php
require_once '../includes/db.php';
require_once '../includes/header.php';

$pedido = null;
$identificador = $_GET['pedido'] ?? null;

if ($identificador) {
    $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE identificador = ?");
    $stmt->execute([$identificador]);
    $pedido = $stmt->fetch();
}

?>

<link rel="stylesheet" href="/assets/css/confirmacion.css">

<div class="confirmation-container">
    <div class="confirmation-content">
        <?php if ($pedido): ?>
            <!-- Confirmación exitosa -->
            <div class="success-card">
                <div class="success-header">
                    <div class="success-icon">✅</div>
                    <h2 class="success-title">¡Pedido Confirmado!</h2>
                    <p class="success-subtitle">Tu pago ha sido procesado exitosamente</p>
                </div>

                <div class="order-details">
                    <div class="detail-item">
                        <span class="detail-label">Número de pedido:</span>
                        <span class="detail-value order-number"><?= htmlspecialchars($pedido['identificador']) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Estado del pago:</span>
                        <span class="detail-value">
                            <?php
                            $statusClass = '';
                            $statusIcon = '';
                            switch(strtolower($pedido['estado'])) {
                                case 'pendiente':
                                    $statusClass = 'status-pending';
                                    $statusIcon = '⏳';
                                    break;
                                case 'procesando':
                                    $statusClass = 'status-processing';
                                    $statusIcon = '🔄';
                                    break;
                                case 'confirmado':
                                case 'pagado':
                                    $statusClass = 'status-confirmed';
                                    $statusIcon = '✅';
                                    break;
                                default:
                                    $statusClass = 'status-default';
                                    $statusIcon = '📋';
                            }
                            ?>
                            <span class="status-badge <?= $statusClass ?>">
                                <span class="status-icon"><?= $statusIcon ?></span>
                                <?= ucfirst($pedido['estado']) ?>
                            </span>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Monto total:</span>
                        <span class="detail-value total-amount">$<?= number_format($pedido['monto'], 2) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Fecha del pedido:</span>
                        <span class="detail-value">
                            <?php
                            $fecha = new DateTime($pedido['fecha']);
                            echo $fecha->format('d/m/Y H:i');
                            ?>
                        </span>
                    </div>
                </div>

                <div class="next-steps">
                    <h3 class="steps-title">¿Qué sigue?</h3>
                    <div class="steps-list">
                        <div class="step-item">
                            <div class="step-icon">📧</div>
                            <div class="step-content">
                                <h4 class="step-title">Confirmación por email</h4>
                                <p class="step-description">Recibirás un correo con los detalles de tu pedido</p>
                            </div>
                        </div>
                        <div class="step-item">
                            <div class="step-icon">📦</div>
                            <div class="step-content">
                                <h4 class="step-title">Preparación del pedido</h4>
                                <p class="step-description">Comenzaremos a preparar tu pedido para el envío</p>
                            </div>
                        </div>
                        <div class="step-item">
                            <div class="step-icon">🚚</div>
                            <div class="step-content">
                                <h4 class="step-title">Seguimiento del envío</h4>
                                <p class="step-description">Te notificaremos cuando tu pedido sea enviado</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Procesando pago -->
            <div class="processing-card">
                <div class="processing-header">
                    <div class="processing-icon">🔄</div>
                    <h2 class="processing-title">Procesando tu pago</h2>
                    <p class="processing-subtitle">Tu pago está siendo verificado</p>
                </div>

                <div class="processing-info">
                    <div class="info-item">
                        <div class="info-icon">⏱️</div>
                        <div class="info-content">
                            <h4 class="info-title">Tiempo estimado</h4>
                            <p class="info-description">El proceso puede tomar entre 5 a 15 minutos</p>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon">📧</div>
                        <div class="info-content">
                            <h4 class="info-title">Notificación</h4>
                            <p class="info-description">Te enviaremos un correo cuando se confirme el pago</p>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon">🔍</div>
                        <div class="info-content">
                            <h4 class="info-title">Seguimiento</h4>
                            <p class="info-description">Puedes consultar el estado en tu historial de pedidos</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Acciones -->
        <div class="action-buttons">
            <?php if ($pedido): ?>
                <a href="../carrito/historial.php" class="btn btn-info">
                    <span class="btn-icon">📋</span>
                    Ver historial de pedidos
                </a>
                <a href="../carrito/detalle_pedido.php?id=<?= $pedido['id'] ?>" class="btn btn-secondary">
                    <span class="btn-icon">👁️</span>
                    Ver detalles del pedido
                </a>
            <?php else: ?>
                <a href="../carrito/historial.php" class="btn btn-info">
                    <span class="btn-icon">📋</span>
                    Ver mis pedidos
                </a>
            <?php endif; ?>
            <a href="/productos/index.php" class="btn btn-primary">
                <span class="btn-icon">🛍️</span>
                Continuar comprando
            </a>
        </div>

        <!-- Información adicional -->
        <div class="additional-info">
            <div class="info-cards">
                <div class="info-card">
                    <div class="card-icon">🛡️</div>
                    <div class="card-content">
                        <h5 class="card-title">Compra segura</h5>
                        <p class="card-text">Tus datos están protegidos</p>
                    </div>
                </div>
                <div class="info-card">
                    <div class="card-icon">📞</div>
                    <div class="card-content">
                        <h5 class="card-title">Soporte 24/7</h5>
                        <p class="card-text">Estamos aquí para ayudarte</p>
                    </div>
                </div>
                <div class="info-card">
                    <div class="card-icon">🔄</div>
                    <div class="card-content">
                        <h5 class="card-title">Devoluciones fáciles</h5>
                        <p class="card-text">30 días para cambios</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Limpiar URL sin recargar la página
if (window.history.replaceState) {
    const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
    window.history.replaceState({}, document.title, cleanUrl);
}

// Animación de entrada
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.success-card, .processing-card');
    cards.forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease-out';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 100);
    });
});

// Auto-refresh para pedidos en procesamiento (opcional)
<?php if (!$pedido): ?>
setTimeout(() => {
    window.location.reload();
}, 30000); // Recargar cada 30 segundos
<?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>