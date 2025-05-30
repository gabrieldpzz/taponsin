<?php
session_start();
if (!isset($_SESSION['firebase_uid'])) {
    header("Location: /index.php");
    exit;
}

require_once '../includes/header.php';
require_once '../includes/db.php';
$uid = $_SESSION['firebase_uid'];

// Obtener proveedor_id y porcentaje de comisión
$stmt = $pdo->prepare("SELECT id, comision_porcentaje, nombre FROM proveedores WHERE firebase_uid = ?");
$stmt->execute([$uid]);
$proveedor = $stmt->fetch();
$nombre_proveedor = $proveedor['nombre'];


if (!$proveedor) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Dashboard del Proveedor</title>
        <link rel="stylesheet" href="/assets/css/dashboard.css">
    </head>
    <body>
        <div class="container">
            <div class="error-card">
                <div class="error-icon">⚠️</div>
                <h2>Acceso Denegado</h2>
                <p>No estás registrado como proveedor.</p>
                <a href="/index.php" class="btn btn-info">Volver al Inicio</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$proveedor_id = $proveedor['id'];
$comision = $proveedor['comision_porcentaje'];

// Calcular métricas directamente
$stmt = $pdo->prepare("
    SELECT 
        SUM(pd.cantidad) AS total_vendidos,
        SUM(pd.precio_unitario * pd.cantidad) AS total_venta,
        SUM(ROUND((pd.precio_unitario * pd.cantidad) * (1 - ? / 100), 2)) AS total_ganado,
        SUM(ROUND((pd.precio_unitario * pd.cantidad) * (? / 100), 2)) AS comision_tienda
    FROM pedido_detalle pd
    JOIN productos p ON pd.producto_id = p.id
    WHERE p.proveedor_id = ?
");
$stmt->execute([$comision, $comision, $proveedor_id]);
$resumen = $stmt->fetch();

// Contar productos por estado
$estadoStmt = $pdo->prepare("SELECT estado, COUNT(*) AS total FROM productos WHERE proveedor_id = ? GROUP BY estado");
$estadoStmt->execute([$proveedor_id]);
$estados = $estadoStmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Top 5 productos más vendidos
$stmt = $pdo->prepare("
    SELECT p.nombre, SUM(pd.cantidad) AS total_vendidos
    FROM pedido_detalle pd
    JOIN productos p ON pd.producto_id = p.id
    WHERE p.proveedor_id = ?
    GROUP BY p.id
    ORDER BY total_vendidos DESC
    LIMIT 5
");
$stmt->execute([$proveedor_id]);
$top_productos = $stmt->fetchAll();

// Agregar filtro de fecha para métricas y PDF
$filtro = $_GET['filtro'] ?? 'hoy';
$condicion_fecha = '';
$fecha_inicio = '';
$fecha_fin = date('d/m/Y');

switch ($filtro) {
    case 'hoy':
        $condicion_fecha = 'AND DATE(pedidos.fecha) = CURDATE()';
        $fecha_inicio = date('d/m/Y');
        break;
    case 'semana':
        $condicion_fecha = 'AND YEARWEEK(pedidos.fecha, 1) = YEARWEEK(CURDATE(), 1)';
        $fecha_inicio = date('d/m/Y', strtotime('monday this week'));
        break;
    case 'mes':
        $condicion_fecha = 'AND MONTH(pedidos.fecha) = MONTH(CURDATE()) AND YEAR(pedidos.fecha) = YEAR(CURDATE())';
        $fecha_inicio = date('01/m/Y');
        break;
    case 'anio':
        $condicion_fecha = 'AND YEAR(pedidos.fecha) = YEAR(CURDATE())';
        $fecha_inicio = date('d/m/Y', strtotime('-1 year'));
        break;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard del Proveedor</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-title">
                    <h1>📊 Panel del Proveedor, <?= htmlspecialchars($nombre_proveedor) ?>.</h1>
                    <p class="header-subtitle">Resumen de tu actividad y ventas</p>
                </div>
                <div class="header-actions">
                    <button onclick="descargarPDF()" class="btn btn-secondary">
                        <span class="btn-icon">📥</span>
                        Descargar PDF
                    </button>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters-card">
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label for="filtro">📅 Filtrar por período:</label>
                    <select name="filtro" id="filtro" onchange="this.form.submit()">
                        <option value="hoy" <?= $filtro === 'hoy' ? 'selected' : '' ?>>Hoy</option>
                        <option value="semana" <?= $filtro === 'semana' ? 'selected' : '' ?>>Esta semana</option>
                        <option value="mes" <?= $filtro === 'mes' ? 'selected' : '' ?>>Este mes</option>
                        <option value="anio" <?= $filtro === 'anio' ? 'selected' : '' ?>>Este año</option>
                    </select>
                </div>
            </form>
        </div>

        <!-- Métricas principales -->
        <div class="metrics-section">
            <h2 class="section-title">💰 Resumen Financiero</h2>
            <div class="metrics-grid">
                <div class="metric-card primary">
                    <div class="metric-icon">🛒</div>
                    <div class="metric-content">
                        <span class="metric-value"><?= $resumen['total_vendidos'] ?? 0 ?></span>
                        <span class="metric-label">Productos Vendidos</span>
                    </div>
                </div>
                
                <div class="metric-card success">
                    <div class="metric-icon">💵</div>
                    <div class="metric-content">
                        <span class="metric-value">$<?= number_format($resumen['total_ganado'] ?? 0, 2) ?></span>
                        <span class="metric-label">Total Ganado</span>
                    </div>
                </div>
                
                <div class="metric-card info">
                    <div class="metric-icon">🏬</div>
                    <div class="metric-content">
                        <span class="metric-value">$<?= number_format($resumen['comision_tienda'] ?? 0, 2) ?></span>
                        <span class="metric-label">Comisión Tienda</span>
                    </div>
                </div>
                
                <div class="metric-card warning">
                    <div class="metric-icon">📈</div>
                    <div class="metric-content">
                        <span class="metric-value">$<?= number_format($resumen['total_venta'] ?? 0, 2) ?></span>
                        <span class="metric-label">Total Ventas</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estado de productos -->
        <div class="products-status-section">
            <div class="section-header">
                <h2 class="section-title">📦 Estado de tus productos</h2>
                <a href="mis_productos.php" class="btn btn-primary">
                    <span class="btn-icon">👁️</span>
                    Ver Productos
                </a>
            </div>
            
            <div class="status-grid">
                <div class="status-card pending">
                    <div class="status-icon">⏳</div>
                    <div class="status-content">
                        <span class="status-number"><?= $estados['pendiente'] ?? 0 ?></span>
                        <span class="status-label">Pendientes</span>
                    </div>
                </div>
                
                <div class="status-card approved">
                    <div class="status-icon">✅</div>
                    <div class="status-content">
                        <span class="status-number"><?= $estados['activo'] ?? 0 ?></span>
                        <span class="status-label">Aprobados</span>
                    </div>
                </div>
                
                <div class="status-card rejected">
                    <div class="status-icon">❌</div>
                    <div class="status-content">
                        <span class="status-number"><?= $estados['rechazado'] ?? 0 ?></span>
                        <span class="status-label">Rechazados</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="charts-section">
            <div class="charts-grid">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3>🔝 Top 5 Productos Más Vendidos</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="graficoTop"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-header">
                        <h3>📈 Distribución de Ganancias</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="graficoPie"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top productos tabla -->
        <div class="top-products-section">
            <div class="section-header">
                <h2 class="section-title">🏆 Productos Más Vendidos</h2>
                <a href="mis_pedidos.php" class="btn btn-info">
                    <span class="btn-icon">📦</span>
                    Ver Pedidos
                </a>
            </div>
            
            <div class="table-card">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Unidades Vendidas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_productos)): ?>
                                <tr>
                                    <td colspan="2" class="empty-row">No hay productos vendidos aún</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($top_productos as $producto): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($producto['nombre']) ?></td>
                                        <td><span class="quantity-badge"><?= $producto['total_vendidos'] ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Detalle completo de ventas -->
        <div class="sales-detail-section">
            <h2 class="section-title">🧾 Detalle Completo de Ventas</h2>
            
            <div class="table-card">
                <div class="table-container">
                    <table class="data-table detailed">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Precio Unitario</th>
                                <th>Cantidad</th>
                                <th>Total Venta</th>
                                <th>Comisión %</th>
                                <th>Ganancia Proveedor</th>
                                <th>Comisión Tienda</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $detalleStmt = $pdo->prepare("
                                SELECT 
                                    p.nombre,
                                    pd.precio_unitario,
                                    pd.cantidad,
                                    (pd.precio_unitario * pd.cantidad) AS total_venta,
                                    v.comision_porcentaje,
                                    ROUND((pd.precio_unitario * pd.cantidad) * (1 - v.comision_porcentaje / 100), 2) AS ganancia_calculada_correcta,
                                    ROUND((pd.precio_unitario * pd.cantidad) * (v.comision_porcentaje / 100), 2) AS comision_tienda
                                FROM pedido_detalle pd
                                JOIN productos p ON pd.producto_id = p.id
                                JOIN proveedores v ON p.proveedor_id = v.id
                                WHERE p.proveedor_id = ?
                                ORDER BY pd.id DESC
                            ");
                            $detalleStmt->execute([$proveedor_id]);
                            $ventas = $detalleStmt->fetchAll();

                            $total_ventas = 0;
                            $total_ganancia = 0;
                            $total_comision = 0;

                            if (empty($ventas)): ?>
                                <tr>
                                    <td colspan="7" class="empty-row">No hay ventas registradas</td>
                                </tr>
                            <?php else:
                                foreach ($ventas as $venta):
                                    $total_ventas += $venta['total_venta'];
                                    $total_ganancia += $venta['ganancia_calculada_correcta'];
                                    $total_comision += $venta['comision_tienda'];
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($venta['nombre']) ?></td>
                                        <td class="price">$<?= number_format($venta['precio_unitario'], 2) ?></td>
                                        <td><span class="quantity-badge"><?= $venta['cantidad'] ?></span></td>
                                        <td class="price">$<?= number_format($venta['total_venta'], 2) ?></td>
                                        <td><span class="percentage-badge"><?= $venta['comision_porcentaje'] ?>%</span></td>
                                        <td class="price success">$<?= number_format($venta['ganancia_calculada_correcta'], 2) ?></td>
                                        <td class="price info">$<?= number_format($venta['comision_tienda'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="total-row">
                                    <td colspan="3"><strong>TOTAL:</strong></td>
                                    <td class="price"><strong>$<?= number_format($total_ventas, 2) ?></strong></td>
                                    <td>-</td>
                                    <td class="price success"><strong>$<?= number_format($total_ganancia, 2) ?></strong></td>
                                    <td class="price info"><strong>$<?= number_format($total_comision, 2) ?></strong></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        const productos = <?= json_encode(array_column($top_productos, 'nombre')) ?>;
        const cantidades = <?= json_encode(array_column($top_productos, 'total_vendidos')) ?>;
        const filtro = "<?= ucfirst($filtro) ?>";
        const fechaInicio = "<?= $fecha_inicio ?>";
        const fechaFin = "<?= $fecha_fin ?>";

        function descargarPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();

            doc.text("📄 Reporte del Panel del Proveedor", 10, 10);
            doc.text("Filtro aplicado: " + filtro, 10, 20);
            doc.text("Periodo: " + fechaInicio + " - " + fechaFin, 10, 30);
            doc.text("Productos vendidos: <?= $resumen['total_vendidos'] ?? 0 ?>", 10, 40);
            doc.text("Total ganado: $<?= number_format($resumen['total_ganado'] ?? 0, 2) ?>", 10, 50);
            doc.text("Comisión tienda: $<?= number_format($resumen['comision_tienda'] ?? 0, 2) ?>", 10, 60);

            const canvas1 = document.getElementById("graficoTop");
            const canvas2 = document.getElementById("graficoPie");

            if (canvas1 && canvas2) {
                const img1 = canvas1.toDataURL("image/png");
                const img2 = canvas2.toDataURL("image/png");

                const imgProps1 = doc.getImageProperties(img1);
                const pdfWidth = doc.internal.pageSize.getWidth() - 20;
                const imgHeight1 = (imgProps1.height * pdfWidth) / imgProps1.width;
                doc.addImage(img1, "PNG", 10, 70, pdfWidth, imgHeight1);

                doc.addPage();
                const imgProps2 = doc.getImageProperties(img2);
                const imgHeight2 = (imgProps2.height * pdfWidth) / imgProps2.width;
                doc.addImage(img2, "PNG", 10, 20, pdfWidth, imgHeight2);
            }

            doc.save("dashboard_proveedor.pdf");
        }

        window.onload = () => {
            // Configuración de colores consistente con la paleta
            const colors = {
                primary: '#6C63FF',
                success: '#A8E6CF',
                info: '#AED9E0',
                warning: '#FFF3B0',
                secondary: '#7F8CAA'
            };

            new Chart(document.getElementById('graficoTop'), {
                type: 'bar',
                data: {
                    labels: productos,
                    datasets: [{
                        label: 'Unidades vendidas',
                        data: cantidades,
                        backgroundColor: colors.primary,
                        borderColor: colors.primary,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            new Chart(document.getElementById('graficoPie'), {
                type: 'doughnut',
                data: {
                    labels: ['Total ganado', 'Comisión tienda'],
                    datasets: [{
                        data: [<?= $resumen['total_ganado'] ?? 0 ?>, <?= $resumen['comision_tienda'] ?? 0 ?>],
                        backgroundColor: [colors.success, colors.info],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
    </script>
    
</body>



</html>

