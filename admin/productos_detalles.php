<?php
session_start();
if (!isset($_SESSION['firebase_uid']) || $_SESSION['rol'] !== 'admin') {
    header("Location: /index.php");
    exit;
}

require_once '../includes/db.php';
require_once '../includes/header.php';

$proveedor_id = $_GET['proveedor_id'] ?? null;
$estado_filtro = $_GET['estado'] ?? 'todos';

if (!$proveedor_id || !is_numeric($proveedor_id)) {
    echo "<h2>Proveedor no válido.</h2>";
    exit;
}

// Obtener información del proveedor
$stmt = $pdo->prepare("SELECT nombre, correo FROM proveedores WHERE id = ?");
$stmt->execute([$proveedor_id]);
$proveedor = $stmt->fetch();

if (!$proveedor) {
    echo "<h2>Proveedor no encontrado.</h2>";
    exit;
}

// Obtener productos con estadísticas
$stmt = $pdo->prepare("SELECT p.*, c.nombre AS categoria FROM productos p
                       JOIN categorias c ON p.categoria_id = c.id
                       WHERE p.proveedor_id = ?
                       ORDER BY 
                         CASE p.estado 
                           WHEN 'pendiente' THEN 1 
                           WHEN 'activo' THEN 2 
                           WHEN 'rechazado' THEN 3 
                         END");
$stmt->execute([$proveedor_id]);
$productos = $stmt->fetchAll();

// Calcular estadísticas
$stats = [
    'total' => count($productos),
    'pendientes' => count(array_filter($productos, fn($p) => $p['estado'] === 'pendiente')),
    'activos' => count(array_filter($productos, fn($p) => $p['estado'] === 'activo')),
    'rechazados' => count(array_filter($productos, fn($p) => $p['estado'] === 'rechazado'))
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos de <?= htmlspecialchars($proveedor['nombre']) ?> - Admin</title>
    <link rel="stylesheet" href="/assets/css/prod_detalles.css?v=<?= time() ?>">
</head>
<body>
    <div class="dashboard-container">
        <!-- Header con información del proveedor -->
        <div class="page-header">
            <div class="provider-header">
                <div class="provider-info">
                    <h2>🛍️ Productos de <?= htmlspecialchars($proveedor['nombre']) ?></h2>
                </div>
                <div class="header-actions">
                    <a href="productos_pendientes.php" class="btn-secondary">
                        <span class="icon">⬅️</span> Volver al panel
                    </a>
                </div>
            </div>
        </div>

        <!-- Estadísticas del proveedor -->
        <div class="stats-overview">
            <div class="stat-card stat-total">
                <div class="stat-icon">📦</div>
                <div class="stat-content">
                    <div class="stat-number"><?= $stats['total'] ?></div>
                    <div class="stat-label">Total productos</div>
                </div>
            </div>
            <div class="stat-card stat-pending">
                <div class="stat-icon">🕓</div>
                <div class="stat-content">
                    <div class="stat-number"><?= $stats['pendientes'] ?></div>
                    <div class="stat-label">Pendientes</div>
                </div>
            </div>
            <div class="stat-card stat-approved">
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                    <div class="stat-number"><?= $stats['activos'] ?></div>
                    <div class="stat-label">Aprobados</div>
                </div>
            </div>
            <div class="stat-card stat-rejected">
                <div class="stat-icon">❌</div>
                <div class="stat-content">
                    <div class="stat-number"><?= $stats['rechazados'] ?></div>
                    <div class="stat-label">Rechazados</div>
                </div>
            </div>
        </div>

        <?php if (count($productos) === 0): ?>
            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <h3>No hay productos registrados</h3>
                <p>Este proveedor aún no ha subido productos al sistema.</p>
                <a href="productos_pendientes.php" class="btn-primary">
                    <span class="icon">⬅️</span> Volver al panel
                </a>
            </div>
        <?php else: ?>
            <!-- Filtros y acciones -->
            <div class="filter-bar">
                <div class="search-container">
                    <input type="text" id="searchInput" placeholder="Buscar producto..." class="search-input">
                    <span class="search-icon">🔍</span>
                </div>
                <div class="filter-options">
                    <select id="statusFilter" class="filter-select">
                        <option value="todos" <?= $estado_filtro === 'todos' ? 'selected' : '' ?>>Todos los estados</option>
                        <option value="pendiente" <?= $estado_filtro === 'pendiente' ? 'selected' : '' ?>>Pendientes</option>
                        <option value="activo" <?= $estado_filtro === 'activo' ? 'selected' : '' ?>>Aprobados</option>
                        <option value="rechazado" <?= $estado_filtro === 'rechazado' ? 'selected' : '' ?>>Rechazados</option>
                    </select>
                    <select id="categoryFilter" class="filter-select">
                        <option value="todas">Todas las categorías</option>
                        <?php
                        $categorias = array_unique(array_column($productos, 'categoria'));
                        foreach ($categorias as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($stats['pendientes'] > 0): ?>
                    <div class="bulk-actions">
                        <button id="approveAllBtn" class="btn-success">
                            <span class="icon">✅</span> Aprobar todos los pendientes
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Grid de productos -->
            <div class="productos-grid">
                <?php foreach ($productos as $p): ?>
                    <div class="producto-card" data-estado="<?= $p['estado'] ?>" data-categoria="<?= htmlspecialchars($p['categoria']) ?>">
                        <div class="card-image">
                            <img src="<?= htmlspecialchars($p['imagen']) ?>" alt="<?= htmlspecialchars($p['nombre']) ?>" loading="lazy">
                            <div class="image-overlay">
                                <button class="view-image-btn" onclick="viewImage('<?= htmlspecialchars($p['imagen']) ?>', '<?= htmlspecialchars($p['nombre']) ?>')">
                                    <span class="icon">🔍</span>
                                </button>
                            </div>
                        </div>

                        <div class="card-content">
                            <div class="product-header">
                                <h3><?= htmlspecialchars($p['nombre']) ?></h3>
                                <?php if ($p['estado'] === 'pendiente'): ?>
                                    <span class="status-badge status-pending">🕓 Pendiente</span>
                                <?php elseif ($p['estado'] === 'activo'): ?>
                                    <span class="status-badge status-approved">✅ Aprobado</span>
                                <?php else: ?>
                                    <span class="status-badge status-rejected">❌ Rechazado</span>
                                <?php endif; ?>
                            </div>

                            <div class="product-info">
                                <div class="info-row">
                                    <span class="info-label">🏷️ Categoría:</span>
                                    <span class="info-value"><?= htmlspecialchars($p['categoria']) ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">💰 Precio:</span>
                                    <span class="info-value price">$<?= number_format($p['precio'], 2) ?></span>
                                </div>
                                <?php if ($p['descripcion']): ?>
                                <div class="info-row description">
                                    <span class="info-label">📝 Descripción:</span>
                                    <span class="info-value"><?= htmlspecialchars(substr($p['descripcion'], 0, 100)) ?><?= strlen($p['descripcion']) > 100 ? '...' : '' ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card-footer">
                            <?php if ($p['estado'] === 'pendiente'): ?>
                                <div class="action-buttons">
                                    <button onclick="approveProduct(<?= $p['id'] ?>)" class="btn-success">
                                        <span class="icon">✅</span> Aprobar
                                    </button>
                                    <button onclick="rejectProduct(<?= $p['id'] ?>)" class="btn-danger">
                                        <span class="icon">❌</span> Rechazar
                                    </button>
                                </div>
                            <?php elseif ($p['estado'] === 'activo'): ?>
                                <div class="action-buttons">
                                    <button onclick="toggleProductStatus(<?= $p['id'] ?>, 'inactivo')" class="btn-warning">
                                        <span class="icon">⏸️</span> Desactivar
                                    </button>
                                </div>
                            <?php elseif ($p['estado'] === 'rechazado'): ?>
                                <div class="action-buttons">
                                    <button onclick="approveProduct(<?= $p['id'] ?>)" class="btn-success">
                                        <span class="icon">✅</span> Aprobar
                                    </button>
                                </div>
                            <?php endif; ?>
                            <a href="editar_producto.php?id=<?= $p['id'] ?>" class="btn-secondary">
                                <span class="icon">👁️</span> Ver detalles o Editar
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal para ver imagen -->
    <div id="imageModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeImageModal()">&times;</span>
            <img id="modalImage" src="/placeholder.svg" alt="">
            <div id="modalTitle"></div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const statusFilter = document.getElementById('statusFilter');
            const categoryFilter = document.getElementById('categoryFilter');
            const cards = document.querySelectorAll('.producto-card');
            
            function filterProducts() {
                const searchTerm = searchInput.value.toLowerCase();
                const statusValue = statusFilter.value;
                const categoryValue = categoryFilter.value;
                
                cards.forEach(card => {
                    const productName = card.querySelector('h3').textContent.toLowerCase();
                    const productStatus = card.dataset.estado;
                    const productCategory = card.dataset.categoria;
                    
                    const matchesSearch = productName.includes(searchTerm);
                    const matchesStatus = statusValue === 'todos' || productStatus === statusValue;
                    const matchesCategory = categoryValue === 'todas' || productCategory === categoryValue;
                    
                    if (matchesSearch && matchesStatus && matchesCategory) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                });
            }
            
            searchInput.addEventListener('input', filterProducts);
            statusFilter.addEventListener('change', filterProducts);
            categoryFilter.addEventListener('change', filterProducts);
            
            // Aplicar filtro inicial si viene de URL
            filterProducts();
        });

        function viewImage(src, title) {
            document.getElementById('modalImage').src = src;
            document.getElementById('modalTitle').textContent = title;
            document.getElementById('imageModal').style.display = 'block';
        }

        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
        }

        function approveProduct(productId) {
            if (confirm('¿Estás seguro de que quieres aprobar este producto?')) {
                window.location.href = `aprobar_producto.php?id=${productId}&redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>`;
            }
        }

        function rejectProduct(productId) {
            const reason = prompt('Motivo del rechazo (opcional):');
            if (reason !== null) {
                window.location.href = `rechazar_producto.php?id=${productId}&reason=${encodeURIComponent(reason)}&redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>`;
            }
        }

        function toggleProductStatus(productId, newStatus) {
            const action = newStatus === 'inactivo' ? 'desactivar' : 'activar';
            if (confirm(`¿Estás seguro de que quieres ${action} este producto?`)) {
                window.location.href = `cambiar_estado_producto.php?id=${productId}&estado=${newStatus}&redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>`;
            }
        }

        document.getElementById('approveAllBtn')?.addEventListener('click', function() {
            if (confirm('¿Estás seguro de que quieres aprobar TODOS los productos pendientes de este proveedor?')) {
                window.location.href = `aprobar_todos_productos.php?proveedor_id=<?= $proveedor_id ?>&redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>`;
            }
        });

        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            const modal = document.getElementById('imageModal');
            if (event.target === modal) {
                closeImageModal();
            }
        }
    </script>
</body>
</html>