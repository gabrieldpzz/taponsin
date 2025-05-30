<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/header.php';

$uid = $_SESSION['firebase_uid'] ?? null;
if (!$uid) {
    header("Location: /index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM pedidos WHERE firebase_uid = ? ORDER BY fecha DESC");
$stmt->execute([$uid]);
$pedidos = $stmt->fetchAll();
?>

<link rel="stylesheet" href="/assets/css/historial.css">

<div class="history-container">
    <div class="page-header">
        <h2 class="page-title">Historial de Pedidos</h2>
        <p class="page-subtitle">Revisa el estado y detalles de tus compras anteriores</p>
    </div>

    <?php if (empty($pedidos)): ?>
        <div class="empty-history">
            <div class="empty-history-icon">📦</div>
            <h3 class="empty-history-title">No tienes pedidos registrados</h3>
            <p class="empty-history-message">¡Realiza tu primera compra y aparecerá aquí!</p>
            <a href="../productos/" class="btn btn-primary">
                <span class="btn-icon">🛍️</span>
                Explorar productos
            </a>
        </div>
    <?php else: ?>
        <div class="orders-content">
            <div class="orders-summary">
                <div class="summary-card">
                    <div class="summary-item">
                        <span class="summary-number"><?= count($pedidos) ?></span>
                        <span class="summary-label">Total de pedidos</span>
                    </div>
                    <div class="summary-item">
                        <?php 
                        $totalGastado = array_sum(array_column($pedidos, 'monto'));
                        ?>
                        <span class="summary-number">$<?= number_format($totalGastado, 2) ?></span>
                        <span class="summary-label">Total gastado</span>
                    </div>
                </div>
            </div>

            <div class="orders-table-card">
                <div class="table-container">
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Identificador</th>
                                <th>Monto</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pedidos as $p): ?>
                                <?php $id_clean = preg_replace('/_uid_.+$/', '', $p['identificador']); ?>
                                <tr class="order-row">
                                    <td class="order-id">
                                        <span class="id-badge">#<?= $p['id'] ?></span>
                                    </td>
                                    <td class="order-identifier">
                                        <span class="identifier-text"><?= htmlspecialchars($id_clean) ?></span>
                                    </td>
                                    <td class="order-amount">
                                        <span class="amount-text">$<?= number_format($p['monto'], 2) ?></span>
                                    </td>
                                    <td class="order-status">
                                        <?php
                                        $statusClass = '';
                                        $statusIcon = '';
                                        switch(strtolower($p['estado'])) {
                                            case 'pendiente':
                                                $statusClass = 'status-pending';
                                                $statusIcon = '⏳';
                                                break;
                                            case 'procesando':
                                                $statusClass = 'status-processing';
                                                $statusIcon = '🔄';
                                                break;
                                            case 'enviado':
                                                $statusClass = 'status-shipped';
                                                $statusIcon = '🚚';
                                                break;
                                            case 'entregado':
                                                $statusClass = 'status-delivered';
                                                $statusIcon = '✅';
                                                break;
                                            case 'cancelado':
                                                $statusClass = 'status-cancelled';
                                                $statusIcon = '❌';
                                                break;
                                            default:
                                                $statusClass = 'status-default';
                                                $statusIcon = '📋';
                                        }
                                        ?>
                                        <span class="status-badge <?= $statusClass ?>">
                                            <span class="status-icon"><?= $statusIcon ?></span>
                                            <?= ucfirst($p['estado']) ?>
                                        </span>
                                    </td>
                                    <td class="order-date">
                                        <?php
                                        $fecha = new DateTime($p['fecha']);
                                        ?>
                                        <div class="date-info">
                                            <span class="date-main"><?= $fecha->format('d/m/Y') ?></span>
                                            <span class="date-time"><?= $fecha->format('H:i') ?></span>
                                        </div>
                                    </td>
                                    <td class="order-actions">
                                        <div class="action-buttons">
                                            <a href="detalle_pedido.php?id=<?= $p['id'] ?>" class="btn btn-info btn-small">
                                                <span class="btn-icon">👁️</span>
                                                Ver detalles
                                            </a>
                                            <a href="generar_factura.php?pedido_id=<?= $p['id'] ?>" target="_blank" class="btn btn-secondary btn-small">
                                                <span class="btn-icon">📄</span>
                                                Factura PDF
                                            </a>
                                            <form method="post" action="/actions/volver_a_comprar.php" style="display:inline;">
                                                <input type="hidden" name="pedido_id" value="<?= $p['id'] ?>">
                                                <button type="submit" class="btn btn-primary btn-small" title="Volver a comprar">
                                                    <span class="btn-icon">🔁</span>
                                                    Volver a comprar
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>