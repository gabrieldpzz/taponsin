<?php
// admin/productos_pendientes.php
session_start();
if (!isset($_SESSION['firebase_uid']) || $_SESSION['rol'] !== 'admin') {
    header("Location: /index.php");
    exit;
}

require_once '../includes/db.php';
require_once '../includes/header.php';

// Obtener proveedores con productos
$stmt = $pdo->query("SELECT pr.id, pr.nombre, COUNT(CASE WHEN p.estado = 'pendiente' THEN 1 END) AS pendientes,
                                COUNT(CASE WHEN p.estado = 'activo' THEN 1 END) AS aprobados,
                                COUNT(CASE WHEN p.estado = 'rechazado' THEN 1 END) AS rechazados
                    FROM proveedores pr
                    LEFT JOIN productos p ON pr.id = p.proveedor_id
                    GROUP BY pr.id, pr.nombre");
$proveedores = $stmt->fetchAll();

// Calcular estadísticas generales
$total_pendientes = array_sum(array_column($proveedores, 'pendientes'));
$total_aprobados = array_sum(array_column($proveedores, 'aprobados'));
$total_rechazados = array_sum(array_column($proveedores, 'rechazados'));
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos Pendientes - Admin</title>
    <link rel="stylesheet" href="/assets/css/productos_pendientes.css?v=<?= time() ?>">
</head>
<body>
    <div class="dashboard-container">
        <div class="page-header">
            <h2>🕓 Revisión de productos por proveedor</h2>
            <div class="header-actions">
                <a href="dashboard.php" class="btn-secondary">
                    <span class="icon">🏠</span> Dashboard
                </a>
                <a href="productos_todos.php" class="btn-primary">
                    <span class="icon">📦</span> Todos los productos
                </a>
            </div>
        </div>

        <!-- Estadísticas generales -->
        <div class="stats-overview">
            <div class="stat-card stat-pending">
                <div class="stat-icon">🕓</div>
                <div class="stat-content">
                    <div class="stat-number"><?= $total_pendientes ?></div>
                    <div class="stat-label">Pendientes</div>
                </div>
            </div>
            <div class="stat-card stat-approved">
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                    <div class="stat-number"><?= $total_aprobados ?></div>
                    <div class="stat-label">Aprobados</div>
                </div>
            </div>
            <div class="stat-card stat-rejected">
                <div class="stat-icon">❌</div>
                <div class="stat-content">
                    <div class="stat-number"><?= $total_rechazados ?></div>
                    <div class="stat-label">Rechazados</div>
                </div>
            </div>
            <div class="stat-card stat-total">
                <div class="stat-icon">📊</div>
                <div class="stat-content">
                    <div class="stat-number"><?= count($proveedores) ?></div>
                    <div class="stat-label">Proveedores</div>
                </div>
            </div>
        </div>

        <?php if (count($proveedores) === 0): ?>
            <div class="empty-state">
                <div class="empty-icon">🏪</div>
                <h3>No hay proveedores registrados</h3>
                <p>Aún no hay proveedores con productos en el sistema.</p>
                <a href="ver_solicitudes.php" class="btn-primary">
                    <span class="icon">📋</span> Ver solicitudes
                </a>
            </div>
        <?php else: ?>
            <!-- Filtros -->
            <div class="filter-bar">
                <div class="search-container">
                    <input type="text" id="searchInput" placeholder="Buscar proveedor..." class="search-input">
                    <span class="search-icon">🔍</span>
                </div>
                <div class="filter-options">
                    <select id="statusFilter" class="filter-select">
                        <option value="todos">Todos</option>
                        <option value="con-pendientes">Con pendientes</option>
                        <option value="sin-pendientes">Sin pendientes</option>
                    </select>
                    <select id="sortFilter" class="filter-select">
                        <option value="pendientes">Más pendientes</option>
                        <option value="nombre">Nombre A-Z</option>
                    </select>
                </div>
            </div>

            <!-- Grid de proveedores -->
            <div class="proveedores-grid">
                <?php foreach ($proveedores as $prov): ?>
                    <div class="proveedor-card" data-pendientes="<?= $prov['pendientes'] ?>" data-nombre="<?= strtolower($prov['nombre']) ?>">
                        <div class="card-header">
                            <div class="provider-info">
                                <h3><?= htmlspecialchars($prov['nombre']) ?></h3>
                                <span class="provider-id">ID: <?= $prov['id'] ?></span>
                            </div>
                            <?php
                                $total = $prov['pendientes'] + $prov['aprobados'] + $prov['rechazados'];
                                if ($prov['pendientes'] > 0): ?>
                                <span class="priority-badge high">🔥 Urgente</span>
                            <?php elseif ($total > 0): ?>
                                <span class="priority-badge normal">✅ Al día</span>
                            <?php else: ?>
                                <span class="priority-badge low">📭 Sin productos</span>
                            <?php endif; ?>
                        </div>

                        <div class="card-content">
                            <div class="contact-info">
                                <div class="info-row">
                                    <span class="info-icon">🆔</span>
                                    <span class="info-text">Proveedor ID: <?= $prov['id'] ?></span>
                                </div>
                            </div>

                            <div class="products-stats">
                                <div class="stat-row">
                                    <div class="stat-item pending">
                                        <span class="stat-icon">🕓</span>
                                        <div class="stat-details">
                                            <span class="stat-number"><?= $prov['pendientes'] ?></span>
                                            <span class="stat-label">Pendientes</span>
                                        </div>
                                    </div>
                                    <div class="stat-item approved">
                                        <span class="stat-icon">✅</span>
                                        <div class="stat-details">
                                            <span class="stat-number"><?= $prov['aprobados'] ?></span>
                                            <span class="stat-label">Aprobados</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="stat-row">
                                    <div class="stat-item rejected">
                                        <span class="stat-icon">❌</span>
                                        <div class="stat-details">
                                            <span class="stat-number"><?= $prov['rechazados'] ?></span>
                                            <span class="stat-label">Rechazados</span>
                                        </div>
                                    </div>
                                    <div class="stat-item total">
                                        <span class="stat-icon">📦</span>
                                        <div class="stat-details">
                                            <span class="stat-number"><?= $prov['pendientes'] + $prov['aprobados'] + $prov['rechazados'] ?></span>
                                            <span class="stat-label">Total</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card-footer">
                            <a href="productos_detalles.php?proveedor_id=<?= $prov['id'] ?>" class="btn-primary">
                                <span class="icon">👁️</span> Ver productos
                            </a>
                            <?php if ($prov['pendientes'] > 0): ?>
                                <a href="productos_detalles.php?proveedor_id=<?= $prov['id'] ?>&estado=pendiente" class="btn-warning">
                                    <span class="icon">⚡</span> Revisar pendientes
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const statusFilter = document.getElementById('statusFilter');
            const sortFilter = document.getElementById('sortFilter');
            const cards = document.querySelectorAll('.proveedor-card');

            function filterAndSort() {
                const searchTerm = searchInput.value.toLowerCase();
                const statusValue = statusFilter.value;
                const sortValue = sortFilter.value;

                const visibleCards = [];
                cards.forEach(card => {
                    const providerName = card.dataset.nombre;
                    const pendientes = parseInt(card.dataset.pendientes);

                    const matchesSearch = providerName.includes(searchTerm);
                    let matchesStatus = true;

                    if (statusValue === 'con-pendientes') {
                        matchesStatus = pendientes > 0;
                    } else if (statusValue === 'sin-pendientes') {
                        matchesStatus = pendientes === 0;
                    }

                    if (matchesSearch && matchesStatus) {
                        card.style.display = 'flex';
                        visibleCards.push(card);
                    } else {
                        card.style.display = 'none';
                    }
                });

                if (sortValue === 'pendientes') {
                    visibleCards.sort((a, b) => parseInt(b.dataset.pendientes) - parseInt(a.dataset.pendientes));
                } else if (sortValue === 'nombre') {
                    visibleCards.sort((a, b) => a.dataset.nombre.localeCompare(b.dataset.nombre));
                }

                const container = document.querySelector('.proveedores-grid');
                visibleCards.forEach(card => container.appendChild(card));
            }

            searchInput.addEventListener('input', filterAndSort);
            statusFilter.addEventListener('change', filterAndSort);
            sortFilter.addEventListener('change', filterAndSort);
        });
    </script>
</body>
</html>
