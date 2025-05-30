<?php
session_start();
if (!isset($_SESSION['firebase_uid'])) {
    header("Location: /index.php");
    exit;
}

require_once '../includes/header.php';
require_once '../includes/db.php';

$uid = $_SESSION['firebase_uid'];
$pedido_id = $_GET['id'] ?? null;

if (!$pedido_id) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Detalle del Pedido</title>
        <link rel="stylesheet" href="/assets/css/detalle_pedido_prov.css?v=2">
    </head>
    <body>
        <div class="container">
            <div class="error-card">
                <div class="error-icon">⚠️</div>
                <h2>Error</h2>
                <p>Pedido no especificado.</p>
                <a href="mis_pedidos.php" class="btn btn-info">Volver a Pedidos</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Obtener proveedor
$stmt = $pdo->prepare("SELECT id FROM proveedores WHERE firebase_uid = ?");
$stmt->execute([$uid]);
$proveedor = $stmt->fetch();
if (!$proveedor) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Detalle del Pedido</title>
        <link rel="stylesheet" href="/assets/css/detalle_pedido_prov.css?v=2">
    </head>
    <body>
        <div class="container">
            <div class="error-card">
                <div class="error-icon">⚠️</div>
                <h2>Acceso Denegado</h2>
                <p>No estás registrado como proveedor.</p>
                <a href="/dashboard.php" class="btn btn-info">Volver al Dashboard</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
$proveedor_id = $proveedor['id'];

// Verificar que el proveedor tenga productos en este pedido
$stmt = $pdo->prepare("
    SELECT p.id
    FROM pedido_detalle pd
    JOIN productos p ON pd.producto_id = p.id
    WHERE pd.pedido_id = ? AND p.proveedor_id = ?
    LIMIT 1
");
$stmt->execute([$pedido_id, $proveedor_id]);
if (!$stmt->fetch()) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Detalle del Pedido</title>
        <link rel="stylesheet" href="/assets/css/detalle_pedido_prov.css?v=2">
    </head>
    <body>
        <div class="container">
            <div class="error-card">
                <div class="error-icon">❌</div>
                <h2>Sin Productos</h2>
                <p>No tienes productos en este pedido.</p>
                <a href="mis_pedidos.php" class="btn btn-info">Volver a Pedidos</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Obtener resumen del pedido
$stmt = $pdo->prepare("
    SELECT 
        pe.identificador,
        pe.email,
        pe.estado,
        pe.forma_pago,
        pe.fecha,
        pe.tipo_entrega,
        pe.estado_envio,
        s.nombre AS sucursal
    FROM pedidos pe
    LEFT JOIN sucursales s ON pe.sucursal_id = s.id
    WHERE pe.id = ?
");
$stmt->execute([$pedido_id]);
$pedido = $stmt->fetch();

// Obtener detalles del pedido (solo del proveedor)
$stmt = $pdo->prepare("
    SELECT 
        p.nombre,
        p.imagen,
        pd.cantidad,
        pd.precio_unitario,
        pd.variante,
        pd.ganancia_proveedor,
        ROUND((pd.precio_unitario * pd.cantidad), 2) AS total_producto
    FROM pedido_detalle pd
    JOIN productos p ON pd.producto_id = p.id
    WHERE pd.pedido_id = ? AND p.proveedor_id = ?
");
$stmt->execute([$pedido_id, $proveedor_id]);
$productos = $stmt->fetchAll();

// Calcular totales
$total_productos = 0;
$total_ganancia = 0;
foreach ($productos as $prod) {
    $total_productos += $prod['total_producto'];
    $total_ganancia += $prod['ganancia_proveedor'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle del Pedido</title>
    <link rel="stylesheet" href="/assets/css/detalle_pedido_prov.css?v=2">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-title">
                    <h1>📦 Detalle del Pedido</h1>
                    <p class="header-subtitle">Pedido #<?= htmlspecialchars($pedido['identificador']) ?></p>
                </div>
                <div class="header-actions">
                    <a href="comprobante_pedido.php?pedido_id=<?= $pedido_id ?>" target="_blank" class="btn btn-secondary">
                        <span class="btn-icon">📄</span>
                        Ver Comprobante PDF
                    </a>
                </div>
            </div>
        </div>

        <!-- Estado del pedido -->
        <div class="status-section">
            <div class="status-card <?= strtolower($pedido['estado']) ?>">
                <div class="status-icon">
                    <?php
                    switch($pedido['estado']) {
                        case 'pendiente': echo '⏳'; break;
                        case 'confirmado': echo '✅'; break;
                        case 'enviado': echo '🚚'; break;
                        case 'entregado': echo '📦'; break;
                        case 'cancelado': echo '❌'; break;
                        default: echo '📋'; break;
                    }
                    ?>
                </div>
                <div class="status-content">
                    <span class="status-label">Estado del pedido:</span>
                    <span class="status-value"><?= ucfirst(htmlspecialchars($pedido['estado'])) ?></span>
                </div>
            </div>
        </div>

        <!-- Información del pedido -->
        <div class="order-info-section">
            <h2 class="section-title">📋 Información del Pedido</h2>
            <div class="info-grid">
                <div class="info-card">
                    <h3>👤 Cliente</h3>
                    <div class="info-content">
                        <div class="info-item">
                            <span class="info-icon">📧</span>
                            <span class="info-text"><?= htmlspecialchars($pedido['email']) ?></span>
                        </div>
                    </div>
                </div>

                <div class="info-card">
                    <h3>💳 Pago y Entrega</h3>
                    <div class="info-content">
                        <div class="info-item">
                            <span class="info-icon">💰</span>
                            <span class="info-text"><?= htmlspecialchars($pedido['forma_pago']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-icon">🚚</span>
                            <span class="info-text"><?= htmlspecialchars($pedido['tipo_entrega']) ?></span>
                        </div>
                        <?php if ($pedido['tipo_entrega'] === 'sucursal'): ?>
                            <div class="info-item">
                                <span class="info-icon">🏪</span>
                                <span class="info-text"><?= htmlspecialchars($pedido['sucursal'] ?? 'No asignada') ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="info-card">
                    <h3>📅 Fechas y Estado</h3>
                    <div class="info-content">
                        <div class="info-item">
                            <span class="info-icon">📅</span>
                            <span class="info-text"><?= date("d/m/Y H:i", strtotime($pedido['fecha'])) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-icon">📦</span>
                            <span class="info-text"><?= htmlspecialchars($pedido['estado_envio']) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resumen financiero -->
        <div class="summary-section">
            <h2 class="section-title">💰 Resumen Financiero</h2>
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="summary-icon">🛒</div>
                    <div class="summary-content">
                        <span class="summary-value">$<?= number_format($total_productos, 2) ?></span>
                        <span class="summary-label">Total Productos</span>
                    </div>
                </div>
                
                <div class="summary-card highlight">
                    <div class="summary-icon">💵</div>
                    <div class="summary-content">
                        <span class="summary-value">$<?= number_format($total_ganancia, 2) ?></span>
                        <span class="summary-label">Tu Ganancia</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Productos del pedido -->
        <div class="products-section">
            <h2 class="section-title">🛒 Productos de este Pedido</h2>
            
            <div class="products-container">
                <?php if (empty($productos)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📦</div>
                        <h3>No hay productos</h3>
                        <p>No se encontraron productos en este pedido.</p>
                    </div>
                <?php else: ?>
                    <div class="products-grid">
                        <?php foreach ($productos as $prod): ?>
                            <div class="product-card">
                                <div class="product-image">
                                    <?php if ($prod['imagen']): ?>
                                        <img src="<?= htmlspecialchars($prod['imagen']) ?>" alt="<?= htmlspecialchars($prod['nombre']) ?>">
                                    <?php else: ?>
                                        <div class="no-image">
                                            <span class="no-image-icon">📦</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="product-info">
                                    <h3 class="product-name"><?= htmlspecialchars($prod['nombre']) ?></h3>
                                    
                                    <?php if ($prod['variante']): ?>
                                        <div class="product-variant">
                                            <span class="variant-label">Variante:</span>
                                            <span class="variant-value"><?= htmlspecialchars($prod['variante']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="product-details">
                                        <div class="detail-item">
                                            <span class="detail-label">Cantidad:</span>
                                            <span class="detail-value quantity"><?= $prod['cantidad'] ?></span>
                                        </div>
                                        
                                        <div class="detail-item">
                                            <span class="detail-label">Precio unitario:</span>
                                            <span class="detail-value price">$<?= number_format($prod['precio_unitario'], 2) ?></span>
                                        </div>
                                        
                                        <div class="detail-item">
                                            <span class="detail-label">Total:</span>
                                            <span class="detail-value price total">$<?= number_format($prod['total_producto'], 2) ?></span>
                                        </div>
                                        
                                        <div class="detail-item highlight">
                                            <span class="detail-label">Tu ganancia:</span>
                                            <span class="detail-value price earnings">$<?= number_format($prod['ganancia_proveedor'], 2) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Footer Navigation -->
        <div class="page-footer">
            <a href="mis_pedidos.php" class="btn btn-info">
                <span class="btn-icon">⬅️</span>
                Volver a Pedidos
            </a>
        </div>
    </div>
</body>
</html>