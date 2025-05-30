<?php
session_start();
if (!isset($_SESSION['firebase_uid']) || $_SESSION['rol'] !== 'admin') {
    header("Location: /index.php");
    exit;
}
require_once '../includes/db.php';
require_once '../includes/header.php';

$stmt = $pdo->query("SELECT productos.*, categorias.nombre AS categoria FROM productos LEFT JOIN categorias ON productos.categoria_id = categorias.id");
$productos = $stmt->fetchAll();

// Calcular estadísticas
$total_productos = count($productos);
$productos_bajo_stock = array_filter($productos, function($p) { return $p['stock'] <= 5; });
$count_bajo_stock = count($productos_bajo_stock);
$valor_total_inventario = array_sum(array_map(function($p) { return $p['precio'] * $p['stock']; }, $productos));
?>

<link rel="stylesheet" href="/assets/css/productos.css">

<div class="admin-container">
    <div class="page-header">
        <h2>Gestión de Inventario</h2>
        <p class="page-subtitle">Administra todos los productos de tu tienda</p>
    </div>

    <!-- Estadísticas del inventario -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📦</div>
            <div class="stat-info">
                <span class="stat-number"><?= $total_productos ?></span>
                <span class="stat-label">Total Productos</span>
            </div>
        </div>
        
        <div class="stat-card <?= $count_bajo_stock > 0 ? 'warning' : '' ?>">
            <div class="stat-icon">⚠️</div>
            <div class="stat-info">
                <span class="stat-number"><?= $count_bajo_stock ?></span>
                <span class="stat-label">Stock Bajo</span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">💰</div>
            <div class="stat-info">
                <span class="stat-number">$<?= number_format($valor_total_inventario, 0) ?></span>
                <span class="stat-label">Valor Inventario</span>
            </div>
        </div>
        
        <div class="stat-card action">
            <a href="nuevo_producto.php" class="btn-new-product">
                <span class="btn-icon">+</span>
                <span>Nuevo Producto</span>
            </a>
        </div>
    </div>

    <!-- Filtros y búsqueda -->
    <div class="filters-card">
        <div class="filters-section">
            <div class="search-group">
                <input type="text" id="buscar-producto" placeholder="Buscar productos..." class="search-input">
            </div>
            
            <div class="filter-group">
                <select id="filtro-categoria" class="filter-select">
                    <option value="">Todas las categorías</option>
                    <?php 
                    $categorias_unicas = array_unique(array_column($productos, 'categoria'));
                    foreach ($categorias_unicas as $categoria): 
                        if ($categoria): ?>
                            <option value="<?= htmlspecialchars($categoria) ?>"><?= htmlspecialchars($categoria) ?></option>
                        <?php endif;
                    endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <select id="filtro-stock" class="filter-select">
                    <option value="">Todos los stocks</option>
                    <option value="bajo">Stock bajo (≤5)</option>
                    <option value="normal">Stock normal (>5)</option>
                    <option value="sin-stock">Sin stock (0)</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Tabla de productos -->
    <div class="products-card">
        <div class="card-header">
            <h3>Lista de Productos</h3>
            <p>Gestiona tu inventario completo desde aquí</p>
        </div>
        
        <div class="card-content">
            <?php if (empty($productos)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📦</div>
                    <h4>No hay productos</h4>
                    <p>Comienza agregando tu primer producto al inventario</p>
                    <a href="nuevo_producto.php" class="btn-primary">
                        <span class="btn-icon">+</span>
                        Crear Primer Producto
                    </a>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="products-table" id="tabla-productos">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Precio</th>
                                <th>Categoría</th>
                                <th>Stock</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos as $p): ?>
                                <tr class="product-row <?= $p['stock'] <= 5 ? 'low-stock' : '' ?> <?= $p['stock'] == 0 ? 'no-stock' : '' ?>" 
                                    data-nombre="<?= strtolower(htmlspecialchars($p['nombre'])) ?>"
                                    data-categoria="<?= strtolower(htmlspecialchars($p['categoria'] ?? '')) ?>"
                                    data-stock="<?= $p['stock'] ?>">
                                    <td>
                                        <div class="product-info">
                                            <?php if ($p['imagen']): ?>
                                                <img src="<?= htmlspecialchars($p['imagen']) ?>" alt="<?= htmlspecialchars($p['nombre']) ?>" class="product-image">
                                            <?php else: ?>
                                                <div class="product-placeholder">
                                                    <span class="placeholder-icon">📷</span>
                                                </div>
                                            <?php endif; ?>
                                            <div class="product-details">
                                                <span class="product-name"><?= htmlspecialchars($p['nombre']) ?></span>
                                                <span class="product-id">ID: #<?= $p['id'] ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="price">$<?= number_format($p['precio'], 2) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($p['categoria']): ?>
                                            <span class="category-badge"><?= htmlspecialchars($p['categoria']) ?></span>
                                        <?php else: ?>
                                            <span class="category-badge no-category">Sin categoría</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="stock-info">
                                            <span class="stock-number"><?= $p['stock'] ?></span>
                                            <span class="stock-label">unidades</span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($p['stock'] == 0): ?>
                                            <span class="status-badge status-out">Agotado</span>
                                        <?php elseif ($p['stock'] <= 5): ?>
                                            <span class="status-badge status-low">Stock Bajo</span>
                                        <?php else: ?>
                                            <span class="status-badge status-ok">Disponible</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="editar_producto.php?id=<?= $p['id'] ?>" class="btn-edit" title="Editar producto">
                                                <span class="btn-icon">✏️</span>
                                            </a>
                                            <a href="eliminar_producto.php?id=<?= $p['id'] ?>" 
                                               class="btn-delete" 
                                               title="Eliminar producto"
                                               onclick="return confirm('¿Seguro que deseas eliminar este producto?\n\nEsta acción no se puede deshacer.')">
                                                <span class="btn-icon">🗑️</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alerta de stock bajo -->
    <?php if ($count_bajo_stock > 0): ?>
        <div class="alert-card warning">
            <div class="alert-icon">⚠️</div>
            <div class="alert-content">
                <h4>Productos con Stock Bajo</h4>
                <p>Tienes <strong><?= $count_bajo_stock ?></strong> producto<?= $count_bajo_stock !== 1 ? 's' : '' ?> con 5 o menos unidades en stock. Considera reabastecer pronto.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const buscarInput = document.getElementById('buscar-producto');
    const filtroCategoria = document.getElementById('filtro-categoria');
    const filtroStock = document.getElementById('filtro-stock');
    const filas = document.querySelectorAll('.product-row');

    function filtrarProductos() {
        const textoBusqueda = buscarInput.value.toLowerCase();
        const categoriaSeleccionada = filtroCategoria.value.toLowerCase();
        const stockSeleccionado = filtroStock.value;

        filas.forEach(fila => {
            const nombre = fila.dataset.nombre;
            const categoria = fila.dataset.categoria;
            const stock = parseInt(fila.dataset.stock);

            let mostrar = true;

            // Filtro de búsqueda por nombre
            if (textoBusqueda && !nombre.includes(textoBusqueda)) {
                mostrar = false;
            }

            // Filtro por categoría
            if (categoriaSeleccionada && categoria !== categoriaSeleccionada) {
                mostrar = false;
            }

            // Filtro por stock
            if (stockSeleccionado) {
                switch (stockSeleccionado) {
                    case 'bajo':
                        if (stock > 5) mostrar = false;
                        break;
                    case 'normal':
                        if (stock <= 5) mostrar = false;
                        break;
                    case 'sin-stock':
                        if (stock !== 0) mostrar = false;
                        break;
                }
            }

            fila.style.display = mostrar ? '' : 'none';
        });
    }

    buscarInput.addEventListener('input', filtrarProductos);
    filtroCategoria.addEventListener('change', filtrarProductos);
    filtroStock.addEventListener('change', filtrarProductos);
});
</script>

<?php include '../includes/footer.php'; ?>
