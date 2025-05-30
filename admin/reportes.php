<?php
session_start();
if (!isset($_SESSION['firebase_uid']) || $_SESSION['rol'] !== 'admin') {
    header("Location: /index.php");
    exit;
}
require_once '../includes/db.php';
require_once '../includes/header.php';

// Filtro por periodo
$periodo = $_GET['periodo'] ?? '30dias';
switch ($periodo) {
    case '1dia':
        $desde = date('Y-m-d', strtotime('-1 day'));
        break;
    case '7dias':
        $desde = date('Y-m-d', strtotime('-7 days'));
        break;
    case 'ano':
        $desde = date('Y-m-d', strtotime('-1 year'));
        break;
    default:
        $desde = date('Y-m-d', strtotime('-30 days'));
}
$hasta = date('Y-m-d');

// Total de ingresos según filtro
$stmt = $pdo->prepare("SELECT SUM(monto) AS total_ventas FROM pedidos WHERE estado IN ('Pagado', 'EXITOSA') AND fecha >= ?");
$stmt->execute([$desde]);
$total = $stmt->fetch();

$stmt = $pdo->prepare("SELECT fecha, SUM(monto) AS total FROM pedidos WHERE estado IN ('Pagado', 'EXITOSA') AND fecha >= ? GROUP BY fecha ORDER BY fecha ASC");
$stmt->execute([$desde]);
$ventasPorDia = $stmt->fetchAll();

// Productos más vendidos
$productos = $pdo->query("
    SELECT pr.nombre, SUM(pd.cantidad) AS total_vendidos
    FROM pedido_detalle pd
    JOIN productos pr ON pd.producto_id = pr.id
    JOIN pedidos p ON pd.pedido_id = p.id
    WHERE p.estado IN ('Pagado', 'EXITOSA')
    GROUP BY pr.nombre
    ORDER BY total_vendidos DESC
    LIMIT 10
")->fetchAll();

// Promedio por pedido
$promedio = $pdo->query("SELECT AVG(monto) AS promedio FROM pedidos WHERE estado IN ('Pagado', 'EXITOSA')")->fetch();

// Ventas por categoría
$porCategoria = $pdo->query("
    SELECT c.nombre AS categoria, COUNT(*) AS pedidos, AVG(p.monto) AS promedio
    FROM pedidos p
    JOIN pedido_detalle pd ON p.id = pd.pedido_id
    JOIN productos pr ON pd.producto_id = pr.id
    JOIN categorias c ON pr.categoria_id = c.id
    WHERE p.estado IN ('Pagado', 'EXITOSA')
    GROUP BY c.nombre
    ORDER BY pedidos DESC
")->fetchAll();

// Calcular estadísticas adicionales
$totalPedidos = $pdo->prepare("SELECT COUNT(*) AS total FROM pedidos WHERE estado IN ('Pagado', 'EXITOSA') AND fecha >= ?");
$totalPedidos->execute([$desde]);
$countPedidos = $totalPedidos->fetch();

$crecimiento = 0;
if ($periodo !== '1dia') {
    $periodoAnterior = match($periodo) {
        '7dias' => date('Y-m-d', strtotime('-14 days')),
        '30dias' => date('Y-m-d', strtotime('-60 days')),
        'ano' => date('Y-m-d', strtotime('-2 years')),
        default => date('Y-m-d', strtotime('-60 days'))
    };
    
    $ventasAnterior = $pdo->prepare("SELECT SUM(monto) AS total FROM pedidos WHERE estado IN ('Pagado', 'EXITOSA') AND fecha >= ? AND fecha < ?");
    $ventasAnterior->execute([$periodoAnterior, $desde]);
    $totalAnterior = $ventasAnterior->fetch();
    
    if ($totalAnterior['total'] > 0) {
        $crecimiento = (($total['total_ventas'] - $totalAnterior['total']) / $totalAnterior['total']) * 100;
    }
}
?>

<link rel="stylesheet" href="/assets/css/reportes.css">

<div class="admin-container">
    <div class="page-header">
        <h2>Reportes de Ventas</h2>
        <p class="page-subtitle">Análisis completo del rendimiento de tu tienda</p>
        <div class="report-meta">
            <span class="report-date">📅 Generado: <?= date('d/m/Y H:i') ?></span>
            <span class="report-period">📊 Período: <?= date('d/m/Y', strtotime($desde)) ?> - <?= date('d/m/Y', strtotime($hasta)) ?></span>
        </div>
    </div>

    <!-- Filtro de período -->
    <div class="filters-card">
        <div class="filters-header">
            <h3>Filtrar Período</h3>
            <p>Selecciona el rango de tiempo para el análisis</p>
        </div>
        <form method="get" class="period-form">
            <div class="period-options">
                <label class="period-option <?= $periodo === '1dia' ? 'active' : '' ?>">
                    <input type="radio" name="periodo" value="1dia" <?= $periodo === '1dia' ? 'checked' : '' ?> onchange="this.form.submit()">
                    <span class="option-content">
                        <span class="option-icon">📅</span>
                        <span class="option-text">Último día</span>
                    </span>
                </label>
                
                <label class="period-option <?= $periodo === '7dias' ? 'active' : '' ?>">
                    <input type="radio" name="periodo" value="7dias" <?= $periodo === '7dias' ? 'checked' : '' ?> onchange="this.form.submit()">
                    <span class="option-content">
                        <span class="option-icon">📊</span>
                        <span class="option-text">Últimos 7 días</span>
                    </span>
                </label>
                
                <label class="period-option <?= $periodo === '30dias' ? 'active' : '' ?>">
                    <input type="radio" name="periodo" value="30dias" <?= $periodo === '30dias' ? 'checked' : '' ?> onchange="this.form.submit()">
                    <span class="option-content">
                        <span class="option-icon">📈</span>
                        <span class="option-text">Últimos 30 días</span>
                    </span>
                </label>
                
                <label class="period-option <?= $periodo === 'ano' ? 'active' : '' ?>">
                    <input type="radio" name="periodo" value="ano" <?= $periodo === 'ano' ? 'checked' : '' ?> onchange="this.form.submit()">
                    <span class="option-content">
                        <span class="option-icon">📆</span>
                        <span class="option-text">Último año</span>
                    </span>
                </label>
            </div>
        </form>
    </div>

    <!-- Métricas principales -->
    <div class="metrics-grid">
        <div class="metric-card primary">
            <div class="metric-icon">💰</div>
            <div class="metric-content">
                <span class="metric-value">$<?= number_format($total['total_ventas'] ?? 0, 2) ?></span>
                <span class="metric-label">Ingresos Totales</span>
                <?php if ($crecimiento != 0): ?>
                    <span class="metric-change <?= $crecimiento > 0 ? 'positive' : 'negative' ?>">
                        <?= $crecimiento > 0 ? '↗' : '↘' ?> <?= abs(round($crecimiento, 1)) ?>%
                    </span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="metric-card">
            <div class="metric-icon">🛒</div>
            <div class="metric-content">
                <span class="metric-value"><?= number_format($countPedidos['total'] ?? 0) ?></span>
                <span class="metric-label">Pedidos Completados</span>
            </div>
        </div>
        
        <div class="metric-card">
            <div class="metric-icon">📊</div>
            <div class="metric-content">
                <span class="metric-value">$<?= number_format($promedio['promedio'] ?? 0, 2) ?></span>
                <span class="metric-label">Promedio por Pedido</span>
            </div>
        </div>
        
        <div class="metric-card action">
            <button onclick="descargarPDF()" class="btn-download">
                <span class="download-icon">📄</span>
                <span class="download-text">Descargar PDF</span>
            </button>
        </div>
    </div>

    <!-- Gráficos -->
    <div class="charts-grid">
        <!-- Ventas por fecha -->
        <div class="chart-card full-width">
            <div class="chart-header">
                <h3>Evolución de Ventas</h3>
                <p>Tendencia de ingresos en el período seleccionado</p>
            </div>
            <div class="chart-container">
                <canvas id="ventasPorDia"></canvas>
            </div>
        </div>

        <!-- Productos más vendidos -->
        <div class="chart-card">
            <div class="chart-header">
                <h3>Top 10 Productos</h3>
                <p>Productos más vendidos por cantidad</p>
            </div>
            <div class="chart-container">
                <canvas id="productosVendidos"></canvas>
            </div>
        </div>

        <!-- Ventas por categoría -->
        <div class="chart-card">
            <div class="chart-header">
                <h3>Ventas por Categoría</h3>
                <p>Distribución de pedidos por categoría</p>
            </div>
            <div class="chart-container">
                <canvas id="ventasPorCategoria"></canvas>
            </div>
        </div>
    </div>

    <!-- Tabla de categorías -->
    <div class="table-card">
        <div class="card-header">
            <h3>Análisis Detallado por Categoría</h3>
            <p>Rendimiento completo de cada categoría de productos</p>
        </div>
        <div class="card-content">
            <?php if (empty($porCategoria)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📊</div>
                    <h4>No hay datos disponibles</h4>
                    <p>No se encontraron ventas en el período seleccionado</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="category-table">
                        <thead>
                            <tr>
                                <th>Categoría</th>
                                <th>Pedidos</th>
                                <th>Promedio por Pedido</th>
                                <th>Rendimiento</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($porCategoria as $index => $categoria): ?>
                                <tr>
                                    <td>
                                        <div class="category-info">
                                            <span class="category-rank">#<?= $index + 1 ?></span>
                                            <span class="category-name"><?= htmlspecialchars($categoria['categoria']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="orders-count"><?= $categoria['pedidos'] ?></span>
                                    </td>
                                    <td>
                                        <span class="average-amount">$<?= number_format($categoria['promedio'], 2) ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                        $performance = $categoria['pedidos'] * $categoria['promedio'];
                                        $maxPerformance = max(array_map(function($c) { return $c['pedidos'] * $c['promedio']; }, $porCategoria));
                                        $percentage = $maxPerformance > 0 ? ($performance / $maxPerformance) * 100 : 0;
                                        ?>
                                        <div class="performance-bar">
                                            <div class="performance-fill" style="width: <?= $percentage ?>%"></div>
                                            <span class="performance-text"><?= round($percentage) ?>%</span>
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

    <!-- Navegación -->
    <div class="navigation-section">
        <a href="index.php" class="btn-back">
            <span class="back-icon">←</span>
            Volver al Panel
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
<script>
const ventasPorDia = <?= json_encode($ventasPorDia) ?>;
const productos = <?= json_encode($productos) ?>;
const porCategoria = <?= json_encode($porCategoria) ?>;
const fechaReporte = '<?= date('d/m/Y H:i') ?>';
const periodo = 'del <?= date('d/m/Y', strtotime($desde)) ?> al <?= date('d/m/Y', strtotime($hasta)) ?>';
const totalIngresos = '<?= number_format($total['total_ventas'] ?? 0, 2) ?>';

let charts = {};

// Configuración de colores consistente
const colors = {
    primary: '#6C63FF',
    success: '#A8E6CF',
    info: '#AED9E0',
    warning: '#FFF3B0',
    error: '#FF8C94',
    secondary: '#B8CFCE'
};

// Gráfico de ventas por día
charts.ventas = new Chart(document.getElementById('ventasPorDia'), {
    type: 'line',
    data: {
        labels: ventasPorDia.map(v => {
            const date = new Date(v.fecha);
            return date.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit' });
        }),
        datasets: [{
            label: 'Ventas ($)',
            data: ventasPorDia.map(v => parseFloat(v.total)),
            borderColor: colors.primary,
            backgroundColor: colors.primary + '20',
            fill: true,
            tension: 0.4,
            pointBackgroundColor: colors.primary,
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
                borderColor: colors.primary,
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

// Gráfico de productos más vendidos
charts.productos = new Chart(document.getElementById('productosVendidos'), {
    type: 'bar',
    data: {
        labels: productos.map(p => p.nombre.length > 20 ? p.nombre.substring(0, 20) + '...' : p.nombre),
        datasets: [{
            label: 'Cantidad Vendida',
            data: productos.map(p => parseInt(p.total_vendidos)),
            backgroundColor: colors.info,
            borderColor: colors.info,
            borderWidth: 1,
            borderRadius: 4
        }]
    },
    options: {
        indexAxis: 'y',
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
                borderColor: colors.info,
                borderWidth: 1,
                cornerRadius: 8
            }
        },
        scales: {
            x: {
                beginAtZero: true,
                grid: {
                    color: '#e5e7eb'
                }
            },
            y: {
                grid: {
                    display: false
                }
            }
        }
    }
});

// Gráfico de ventas por categoría
charts.ventasPorCategoria = new Chart(document.getElementById('ventasPorCategoria'), {
    type: 'doughnut',
    data: {
        labels: porCategoria.map(c => c.categoria),
        datasets: [{
            data: porCategoria.map(c => parseInt(c.pedidos)),
            backgroundColor: [
                colors.primary,
                colors.success,
                colors.info,
                colors.warning,
                colors.error,
                colors.secondary,
                '#FF6B6B',
                '#4ECDC4',
                '#45B7D1',
                '#96CEB4'
            ],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 20,
                    usePointStyle: true,
                    font: {
                        size: 12
                    }
                }
            },
            tooltip: {
                backgroundColor: '#333446',
                titleColor: '#fff',
                bodyColor: '#fff',
                borderColor: colors.primary,
                borderWidth: 1,
                cornerRadius: 8,
                callbacks: {
                    label: function(context) {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                        return context.label + ': ' + context.parsed + ' pedidos (' + percentage + '%)';
                    }
                }
            }
        }
    }
});

function descargarPDF() {
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF('p', 'mm', 'a4');

    // Configurar fuente
    pdf.setFont('helvetica');
    
    // Título
    pdf.setFontSize(18);
    pdf.setTextColor(51, 68, 70); // #333446
    pdf.text('Reporte de Ventas', 15, 20);
    
    // Información del reporte
    pdf.setFontSize(10);
    pdf.setTextColor(127, 140, 170); // #7F8CAA
    pdf.text('Fecha de generación: ' + fechaReporte, 15, 30);
    pdf.text('Período analizado: ' + periodo, 15, 36);
    
    // Métricas principales
    pdf.setFontSize(12);
    pdf.setTextColor(51, 68, 70);
    pdf.text('Métricas Principales', 15, 50);
    
    pdf.setFontSize(10);
    pdf.text('Total de Ingresos: $' + totalIngresos, 15, 58);
    pdf.text('Promedio por Pedido: $<?= number_format($promedio['promedio'] ?? 0, 2) ?>', 15, 64);
    pdf.text('Pedidos Completados: <?= number_format($countPedidos['total'] ?? 0) ?>', 15, 70);

    // Tabla de categorías
    if (porCategoria.length > 0) {
        pdf.setFontSize(12);
        pdf.text('Análisis por Categoría', 15, 85);
        
        let y = 95;
        pdf.setFontSize(9);
        pdf.text('Categoría', 15, y);
        pdf.text('Pedidos', 80, y);
        pdf.text('Promedio', 120, y);
        
        y += 8;
        porCategoria.forEach((cat, index) => {
            if (y > 270) {
                pdf.addPage();
                y = 20;
            }
            pdf.text((index + 1) + '. ' + cat.categoria, 15, y);
            pdf.text(cat.pedidos.toString(), 80, y);
            pdf.text('$' + parseFloat(cat.promedio).toFixed(2), 120, y);
            y += 6;
        });
    }

    // Capturar gráficos
    const chartsToExport = ['ventasPorDia', 'productosVendidos', 'ventasPorCategoria'];
    let currentIndex = 0;

    function capturarYAgregar() {
        if (currentIndex >= chartsToExport.length) {
            pdf.save('reporte_ventas_' + new Date().toISOString().split('T')[0] + '.pdf');
            return;
        }

        const canvas = document.getElementById(chartsToExport[currentIndex]);
        if (!canvas) {
            currentIndex++;
            capturarYAgregar();
            return;
        }

        html2canvas(canvas, {
            backgroundColor: '#ffffff',
            scale: 2
        }).then(canvasImg => {
            pdf.addPage();
            const imgData = canvasImg.toDataURL('image/png');
            const imgWidth = 180;
            const imgHeight = canvasImg.height * imgWidth / canvasImg.width;
            
            // Título del gráfico
            const titles = ['Evolución de Ventas', 'Top 10 Productos', 'Ventas por Categoría'];
            pdf.setFontSize(14);
            pdf.setTextColor(51, 68, 70);
            pdf.text(titles[currentIndex], 15, 20);
            
            pdf.addImage(imgData, 'PNG', 15, 30, imgWidth, Math.min(imgHeight, 200));
            currentIndex++;
            capturarYAgregar();
        });
    }

    capturarYAgregar();
}

// Mostrar mensaje de carga al cambiar período
document.querySelectorAll('input[name="periodo"]').forEach(input => {
    input.addEventListener('change', function() {
        const loadingOverlay = document.createElement('div');
        loadingOverlay.className = 'loading-overlay';
        loadingOverlay.innerHTML = `
            <div class="loading-content">
                <div class="loading-spinner"></div>
                <p>Actualizando reportes...</p>
            </div>
        `;
        document.body.appendChild(loadingOverlay);
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
