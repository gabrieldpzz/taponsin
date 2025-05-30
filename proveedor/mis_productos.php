<?php
session_start();
if (!isset($_SESSION['firebase_uid'])) {
    header("Location: /index.php");
    exit;
}

require_once '../includes/header.php';
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
        <title>Mis Productos</title>
        <link rel="stylesheet" href="/assets/css/mis_productos.css">
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

// Obtener productos del proveedor
$stmt = $pdo->prepare("SELECT * FROM productos WHERE proveedor_id = ?");
$stmt->execute([$proveedor_id]);
$productos = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Productos</title>
    <link rel="stylesheet" href="/assets/css/mis_productos.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-title">
                    <h1>🛍️ Mis Productos</h1>
                    <p class="header-subtitle">Gestiona tu catálogo de productos</p>
                </div>
                <div class="header-actions">
                    <a href="agregar_producto.php" class="btn btn-primary">
                        <span class="btn-icon">➕</span>
                        Agregar Producto
                    </a>
                </div>
            </div>
        </div>

        <!-- Productos Grid -->
        <div class="products-section">
            <?php if (!empty($productos)): ?>
                <div class="products-grid">
                    <?php foreach ($productos as $p): ?>
                        <div class="product-card">
                            <div class="product-image">
                                <?php if ($p['imagen']): ?>
                                    <img src="<?= htmlspecialchars($p['imagen']) ?>" alt="<?= htmlspecialchars($p['nombre']) ?>">
                                <?php else: ?>
                                    <div class="no-image">
                                        <span class="no-image-icon">📦</span>
                                        <span class="no-image-text">Sin imagen</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="product-info">
                                <h3 class="product-name"><?= htmlspecialchars($p['nombre']) ?></h3>
                                <p class="product-description"><?= htmlspecialchars($p['descripcion']) ?></p>
                                
                                <div class="product-details">
                                    <div class="product-price">
                                        <span class="price-label">Precio:</span>
                                        <span class="price-value">$<?= number_format($p['precio'], 2) ?></span>
                                    </div>
                                    <div class="product-category">
                                        <span class="category-label">Categoría:</span>
                                        <span class="category-value"><?= htmlspecialchars($p['categoria_id']) ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="product-actions">
                                <a href="editar_producto.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn-small">
                                    <span class="btn-icon">✏️</span>
                                    Editar
                                </a>
                                <a href="eliminar_producto.php?id=<?= $p['id'] ?>" 
                                   class="btn btn-danger btn-small" 
                                   onclick="return confirm('¿Estás seguro de eliminar este producto?')">
                                    <span class="btn-icon">🗑️</span>
                                    Eliminar
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📦</div>
                    <h3>No hay productos registrados</h3>
                    <p>Aún no tienes productos en tu catálogo. ¡Agrega tu primer producto!</p>
                    <a href="agregar_producto.php" class="btn btn-primary">
                        <span class="btn-icon">➕</span>
                        Agregar Primer Producto
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer Navigation -->
        <div class="page-footer">
            <a href="dashboard.php" class="btn btn-info">
                <span class="btn-icon">⬅️</span>
                Volver al Panel
            </a>
        </div>
    </div>
</body>
</html>