<?php
session_start();
if (!isset($_SESSION['firebase_uid']) || $_SESSION['rol'] !== 'admin') {
    header("Location: /index.php");
    exit;
}
require_once '../includes/db.php';
require_once '../includes/header.php';

// Obtener estadísticas del dashboard
$stats = [];

// Total de productos
$stmt = $pdo->query("SELECT COUNT(*) as total FROM productos");
$stats['productos'] = $stmt->fetch()['total'];

// Total de pedidos
$stmt = $pdo->query("SELECT COUNT(*) as total FROM pedidos");
$stats['pedidos'] = $stmt->fetch()['total'];

// Total de usuarios
$stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios");
$stats['usuarios'] = $stmt->fetch()['total'];

// Ingresos del mes actual
$stmt = $pdo->query("SELECT SUM(monto) as total FROM pedidos WHERE estado IN ('Pagado', 'EXITOSA') AND MONTH(fecha) = MONTH(CURRENT_DATE()) AND YEAR(fecha) = YEAR(CURRENT_DATE())");
$stats['ingresos_mes'] = $stmt->fetch()['total'] ?? 0;

// Pedidos recientes
$stmt = $pdo->query("SELECT * FROM pedidos ORDER BY fecha DESC LIMIT 5");
$pedidos_recientes = $stmt->fetchAll();

// Productos con stock bajo
$stmt = $pdo->query("SELECT * FROM productos WHERE stock <= 5 ORDER BY stock ASC LIMIT 5");
$productos_stock_bajo = $stmt->fetchAll();

// Usuarios recientes
$stmt = $pdo->query("SELECT * FROM usuarios ORDER BY id DESC LIMIT 5");
$usuarios_recientes = $stmt->fetchAll();

// Ventas de los últimos 7 días
$stmt = $pdo->query("
    SELECT DATE(fecha) as fecha, COUNT(*) as pedidos, SUM(monto) as total 
    FROM pedidos 
    WHERE estado IN ('Pagado', 'EXITOSA') 
    AND fecha >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)
    GROUP BY DATE(fecha) 
    ORDER BY fecha ASC
");
$ventas_semana = $stmt->fetchAll();
?>

<link rel="stylesheet" href="/assets/css/admin_dashboard.css">

<div class="admin-container">
    <div class="dashboard-header">
        <div class="header-content">
            <h1 class="dashboard-title">
                <span class="title-icon">🏢</span>
                Panel de Administración
            </h1>
            <p class="dashboard-subtitle">Gestiona tu tienda desde un solo lugar</p>
        </div>
        <div class="header-actions">
            <div class="admin-info">
                <span class="admin-badge">
                    <span class="badge-icon">👑</span>
                    Administrador
                </span>
                <span class="last-login">
                    Último acceso: <?= date('d/m/Y H:i') ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Estadísticas principales -->
    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="stat-icon">💰</div>
            <div class="stat-content">
                <span class="stat-value">$<?= number_format($stats['ingresos_mes'], 2) ?></span>
                <span class="stat-label">Ingresos del Mes</span>
                <span class="stat-trend positive">↗ +12.5%</span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">🛒</div>
            <div class="stat-content">
                <span class="stat-value"><?= number_format($stats['pedidos']) ?></span>
                <span class="stat-label">Total Pedidos</span>
                <span class="stat-trend positive">↗ +8.3%</span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">📦</div>
            <div class="stat-content">
                <span class="stat-value"><?= number_format($stats['productos']) ?></span>
                <span class="stat-label">Productos</span>
                <span class="stat-trend neutral">→ 0%</span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-content">
                <span class="stat-value"><?= number_format($stats['usuarios']) ?></span>
                <span class="stat-label">Usuarios</span>
                <span class="stat-trend positive">↗ +15.2%</span>
            </div>
        </div>
    </div>

    <!-- Menú de navegación principal -->
    <div class="admin-menu-grid">
        <a href="productos.php" class="menu-card productos">
            <div class="menu-icon">📦</div>
            <div class="menu-content">
                <h3 class="menu-title">Gestionar Productos</h3>
                <p class="menu-description">Añadir, editar y organizar el inventario</p>
                <span class="menu-badge"><?= $stats['productos'] ?> productos</span>
            </div>
            <div class="menu-arrow">→</div>
        </a>

        <a href="pedidos.php" class="menu-card pedidos">
            <div class="menu-icon">🛒</div>
            <div class="menu-content">
                <h3 class="menu-title">Ver Pedidos</h3>
                <p class="menu-description">Gestionar y procesar pedidos</p>
                <span class="menu-badge"><?= $stats['pedidos'] ?> pedidos</span>
            </div>
            <div class="menu-arrow">→</div>
        </a>

        <a href="cupones.php" class="menu-card cupones">
            <div class="menu-icon">🎫</div>
            <div class="menu-content">
                <h3 class="menu-title">Cupones</h3>
                <p class="menu-description">Crear y administrar descuentos</p>
                <span class="menu-badge">Promociones</span>
            </div>
            <div class="menu-arrow">→</div>
        </a>

        <a href="usuarios.php" class="menu-card usuarios">
            <div class="menu-icon">👥</div>
            <div class="menu-content">
                <h3 class="menu-title">Usuarios</h3>
                <p class="menu-description">Administrar clientes y roles</p>
                <span class="menu-badge"><?= $stats['usuarios'] ?> usuarios</span>
            </div>
            <div class="menu-arrow">→</div>
        </a>

        <a href="/includes/ver_sugerencias.php" class="menu-card reportes">
            <div class="menu-icon">📩</div>
            <div class="menu-content">
                <h3 class="menu-title">Sugerecias</h3>
                <p class="menu-description">Revisar sugerencias de Usuarios</p>
                <span class="menu-badge">Feedback</span>    
            </div>
            <div class="menu-arrow">→</div>
        </a>

        <a href="reportes.php" class="menu-card reportes">
            <div class="menu-icon">📊</div>
            <div class="menu-content">
                <h3 class="menu-title">Reportes</h3>
                <p class="menu-description">Análisis y estadísticas de ventas</p>
                <span class="menu-badge">Analytics</span>
            </div>
            <div class="menu-arrow">→</div>
        </a>

        <a href="solicitudes_proveedores.php" class="menu-card reportes">
            <div class="menu-icon">🤜🤛</div>
            <div class="menu-content">
                <h3 class="menu-title">Proveedores</h3>
                <p class="menu-description">Revisar solicitudes de proveedores nuevos</p>
                <span class="menu-badge">Proveedores</span>
            </div>
            <div class="menu-arrow">→</div>
        </a>

        <a href="productos_pendientes.php" class="menu-card reportes">
            <div class="menu-icon">⏳</div>
            <div class="menu-content">
                <h3 class="menu-title">Productos Pendientes</h3>
                <p class="menu-description">Solicitudes de productos nuevos</p>
                <span class="menu-badge">Prod.Proveedores</span>
            </div>
            <div class="menu-arrow">→</div>
        </a>

    </div>

    <!-- Gráfico de ventas -->
    <div class="dashboard-widgets">
        <div class="widget chart-widget">
            <div class="widget-header">
                <h3 class="widget-title">
                    <span class="widget-icon">📈</span>
                    Ventas de los Últimos 7 Días
                </h3>
                <div class="widget-actions">
                    <button class="btn-widget" onclick="refreshChart()">🔄</button>
                </div>
            </div>
            <div class="widget-content">
                <canvas id="ventasChart"></canvas>
            </div>
        </div>

        <!-- Actividad reciente -->
        <div class="widget activity-widget">
            <div class="widget-header">
                <h3 class="widget-title">
                    <span class="widget-icon">🕒</span>
                    Actividad Reciente
                </h3>
                <a href="pedidos.php" class="btn-widget-link">Ver todos</a>
            </div>
            <div class="widget-content">
                <?php if (empty($pedidos_recientes)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📋</div>
                        <p>No hay pedidos recientes</p>
                    </div>
                <?php else: ?>
                    <div class="activity-list">
                        <?php foreach ($pedidos_recientes as $pedido): ?>
                            <div class="activity-item">
                                <div class="activity-icon">🛒</div>
                                <div class="activity-content">
                                    <p class="activity-text">
                                        Nuevo pedido <strong>#<?= $pedido['id'] ?></strong>
                                    </p>
                                    <span class="activity-meta">
                                        <?= htmlspecialchars($pedido['email']) ?> • 
                                        $<?= number_format($pedido['monto'], 2) ?>
                                    </span>
                                </div>
                                <span class="activity-time">
                                    <?= date('H:i', strtotime($pedido['fecha'])) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Alertas y notificaciones -->
    <div class="alerts-section">
        <?php if (!empty($productos_stock_bajo)): ?>
            <div class="alert-card warning">
                <div class="alert-icon">⚠️</div>
                <div class="alert-content">
                    <h4 class="alert-title">Stock Bajo</h4>
                    <p class="alert-message">
                        <?= count($productos_stock_bajo) ?> producto<?= count($productos_stock_bajo) !== 1 ? 's' : '' ?> 
                        con stock bajo. Revisa el inventario.
                    </p>
                    <div class="alert-actions">
                        <a href="productos.php" class="btn-alert">Ver Productos</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="alert-card info">
            <div class="alert-icon">💡</div>
            <div class="alert-content">
                <h4 class="alert-title">Consejo del Día</h4>
                <p class="alert-message">
                    Revisa regularmente los reportes de ventas para identificar tendencias y oportunidades de crecimiento.
                </p>
            </div>
        </div>
    </div>

    <!-- Accesos rápidos -->
    <div class="quick-actions">
        <h3 class="section-title">
            <span class="section-icon">⚡</span>
            Acciones Rápidas
        </h3>
        <div class="quick-actions-grid">
            <button onclick="window.location.href='productos.php'" class="quick-action">
                <span class="action-icon">➕</span>
                <span class="action-text">Nuevo Producto</span>
            </button>
            <button onclick="window.location.href='cupones.php'" class="quick-action">
                <span class="action-icon">🎫</span>
                <span class="action-text">Crear Cupón</span>
            </button>
            <button onclick="window.location.href='reportes.php'" class="quick-action">
                <span class="action-icon">📊</span>
                <span class="action-text">Ver Reportes</span>
            </button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Datos para el gráfico
const ventasData = <?= json_encode($ventas_semana) ?>;

// Configurar gráfico de ventas
const ctx = document.getElementById('ventasChart').getContext('2d');
const ventasChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: ventasData.map(v => {
            const date = new Date(v.fecha);
            return date.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit' });
        }),
        datasets: [{
            label: 'Ventas ($)',
            data: ventasData.map(v => parseFloat(v.total || 0)),
            borderColor: '#6C63FF',
            backgroundColor: '#6C63FF20',
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#6C63FF',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: '#333446',
                titleColor: '#fff',
                bodyColor: '#fff',
                borderColor: '#6C63FF',
                borderWidth: 1,
                cornerRadius: 8,
                callbacks: {
                    label: function(context) {
                        return 'Ventas: $' + context.parsed.y.toLocaleString('es-ES', {minimumFractionDigits: 2});
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: '#e5e7eb'
                },
                ticks: {
                    callback: function(value) {
                        return '$' + value.toLocaleString('es-ES');
                    }
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    }
});

// Funciones de interacción
function refreshChart() {
    // Simular actualización
    const button = event.target;
    button.style.transform = 'rotate(360deg)';
    setTimeout(() => {
        button.style.transform = 'rotate(0deg)';
        showNotification('Gráfico actualizado', 'success');
    }, 500);
}

function exportData() {
    showNotification('Exportando datos...', 'info');
    // Aquí iría la lógica de exportación
    setTimeout(() => {
        showNotification('Datos exportados correctamente', 'success');
    }, 2000);
}

function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 20px;
        border-radius: 6px;
        color: white;
        font-weight: 500;
        z-index: 1000;
        animation: slideIn 0.3s ease;
    `;
    
    if (type === 'success') {
        notification.style.backgroundColor = '#A8E6CF';
        notification.style.color = '#166534';
    } else if (type === 'info') {
        notification.style.backgroundColor = '#AED9E0';
        notification.style.color = '#0369A1';
    }
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Animaciones de entrada
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.stat-card, .menu-card, .widget');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});
</script>

<?php include '../includes/footer.php'; ?>
