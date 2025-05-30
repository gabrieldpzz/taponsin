<?php
session_start();
if (!isset($_SESSION['firebase_uid'])) {
    header("Location: /index.php");
    exit;
}

require_once '../includes/db.php';
$uid = $_SESSION['firebase_uid'];

// Obtener proveedor_id
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
        <title>Mis Pedidos</title>
        <link rel="stylesheet" href="/assets/css/mis_pedidos.css">
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

// Obtener pedidos que contienen productos del proveedor
$stmt = $pdo->prepare("
    SELECT 
        pe.id AS pedido_id,
        pe.fecha,
        u.email AS cliente_email,
        SUM(pd.cantidad) AS total_productos,
        SUM(pd.precio_unitario * pd.cantidad) AS total_venta,
        SUM(ROUND((pd.precio_unitario * pd.cantidad) * (1 - pr.comision_porcentaje / 100), 2)) AS total_ganancia
    FROM pedido_detalle pd
    JOIN productos p ON pd.producto_id = p.id
    JOIN pedidos pe ON pd.pedido_id = pe.id
    JOIN proveedores pr ON p.proveedor_id = pr.id
    LEFT JOIN usuarios u ON pe.firebase_uid = u.firebase_uid
    WHERE p.proveedor_id = ?
    GROUP BY pe.id
    ORDER BY pe.fecha DESC
");
$stmt->execute([$proveedor_id]);
$pedidos = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Pedidos</title>
    <link rel="stylesheet" href="/assets/css/mis_pedidos.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-title">
                    <h1>📦 Pedidos con mis productos</h1>
                    <p class="header-subtitle">Gestiona y revisa los pedidos que incluyen tus productos</p>
                </div>
                <div class="header-stats">
                    <div class="stat-card">
                        <span class="stat-number"><?= count($pedidos) ?></span>
                        <span class="stat-label">Pedidos Total</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pedidos Section -->
        <div class="orders-section">
            <?php if (count($pedidos) === 0): ?>
                <div class="empty-state">
                    <div class="empty-icon">📦</div>
                    <h3>No hay pedidos registrados</h3>
                    <p>Aún no hay pedidos que incluyan tus productos. ¡Promociona tu catálogo para generar más ventas!</p>
                    <a href="mis_productos.php" class="btn btn-primary">
                        <span class="btn-icon">🛍️</span>
                        Ver Mis Productos
                    </a>
                </div>
            <?php else: ?>
                <div class="orders-grid">
                    <?php foreach ($pedidos as $p): ?>
                        <div class="order-card">
                            <div class="order-header">
                                <div class="order-id">
                                    <span class="id-label">Pedido</span>
                                    <span class="id-value">#<?= $p['pedido_id'] ?></span>
                                </div>
                                <div class="order-date">
                                    <span class="date-icon">📅</span>
                                    <span class="date-value"><?= date('d/m/Y H:i', strtotime($p['fecha'])) ?></span>
                                </div>
                            </div>
                            
                            <div class="order-info">
                                <div class="customer-info">
                                    <span class="customer-icon">👤</span>
                                    <div class="customer-details">
                                        <span class="customer-label">Cliente:</span>
                                        <span class="customer-email"><?= htmlspecialchars($p['cliente_email']) ?></span>
                                    </div>
                                </div>
                                
                                <div class="order-metrics">
                                    <div class="metric">
                                        <span class="metric-icon">📦</span>
                                        <div class="metric-content">
                                            <span class="metric-value"><?= $p['total_productos'] ?></span>
                                            <span class="metric-label">Productos</span>
                                        </div>
                                    </div>
                                    
                                    <div class="metric">
                                        <span class="metric-icon">💰</span>
                                        <div class="metric-content">
                                            <span class="metric-value">$<?= number_format($p['total_venta'], 2) ?></span>
                                            <span class="metric-label">Total Venta</span>
                                        </div>
                                    </div>
                                    
                                    <div class="metric highlight">
                                        <span class="metric-icon">💵</span>
                                        <div class="metric-content">
                                            <span class="metric-value">$<?= number_format($p['total_ganancia'], 2) ?></span>
                                            <span class="metric-label">Tu Ganancia</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="order-actions">
                                <a href="detalle_pedido.php?id=<?= $p['pedido_id'] ?>" class="btn btn-info btn-full">
                                    <span class="btn-icon">👁️</span>
                                    Ver Detalle
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer Navigation -->
        <div class="page-footer">
            <a href="dashboard.php" class="btn btn-secondary">
                <span class="btn-icon">⬅️</span>
                Volver al Panel
            </a>
        </div>
    </div>
</body>
</html>