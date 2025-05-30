<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/header.php';

$pedido_id = $_GET['id'] ?? null;
if (!$pedido_id) {
    echo "Pedido no especificado.";
    exit;
}

// Verificar que el pedido sea del usuario logueado
$uid = $_SESSION['firebase_uid'] ?? null;
$stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ? AND firebase_uid = ?");
$stmt->execute([$pedido_id, $uid]);
$pedido = $stmt->fetch();

if (!$pedido) {
    echo "Pedido no encontrado o no tienes permiso para verlo.";
    exit;
}

// Obtener productos con nombre, imagen y variantes
$stmt = $pdo->prepare("
    SELECT pd.*, pr.nombre AS nombre_producto, pr.imagen 
    FROM pedido_detalle pd
    JOIN productos pr ON pd.producto_id = pr.id
    WHERE pd.pedido_id = ?
");
$stmt->execute([$pedido_id]);
$productos = $stmt->fetchAll();
?>

<link rel="stylesheet" href="/assets/css/detalle-pedido.css">
<link rel="stylesheet" href="/assets/css/tracking.css">

<div class="order-detail-container">
    <div class="page-header">
        <div class="header-content">
            <h2 class="page-title">Detalles del Pedido</h2>
            <div class="order-id-display">
                <span class="order-id-label">Pedido</span>
                <span class="order-id-number">#<?= $pedido['id'] ?></span>
            </div>
        </div>
        <a href="historial.php" class="btn btn-secondary btn-back">
            <span class="btn-icon">←</span>
            Volver al historial
        </a>
    </div>

    <div class="order-content">
        <!-- Información general del pedido -->
        <div class="order-info-card">
            <h3 class="card-title">Información General</h3>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Fecha del pedido:</span>
                    <span class="info-value">
                        <?php
                        $fecha = new DateTime($pedido['fecha']);
                        echo $fecha->format('d/m/Y H:i');
                        ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Estado del pago:</span>
                    <span class="info-value">
                        <?php
                        $statusClass = '';
                        $statusIcon = '';
                        switch(strtolower($pedido['estado'])) {
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
                            <?= ucfirst($pedido['estado']) ?>
                        </span>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Total pagado:</span>
                    <span class="info-value total-amount">$<?= number_format($pedido['monto'], 2) ?></span>
                </div>
            </div>
        </div>

<!-- Código de rastreo -->
        <div class="tracking-card">
            <h3 class="card-title">Código de Rastreo</h3>
            <div class="tracking-section">
                <div class="tracking-input-group">
                    <input type="text" id="codigoRastreo" value="<?= htmlspecialchars($pedido['identificador']) ?>" readonly class="tracking-input">
                    <button onclick="copiarCodigo()" class="btn btn-info btn-copy">
                        <span class="btn-icon">📋</span>
                        Copiar
                    </button>
                </div>
                <p id="mensajeCopiado" class="copy-message">¡Copiado al portapapeles!</p>
            </div>
        </div>


       <!-- Estado del envío -->
        <div class="shipping-status-card">
            <h3 class="card-title">Estado del Envío</h3>
            <div id="estadoEnvio" class="result-container"></div>
        </div>

        <!-- Productos del pedido -->
        <?php if ($productos): ?>
        <div class="products-card">
            <h3 class="card-title">Productos del Pedido</h3>
            <div class="table-container">
                <table class="products-table">
                    <thead>
                        <tr>
                            <th>Imagen</th>
                            <th>Producto</th>
                            <th>Variante</th>
                            <th>Cantidad</th>
                            <th>Precio unitario</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productos as $p): ?>
                        <tr class="product-row">
                            <td class="product-image-cell">
                                <img src="<?= htmlspecialchars($p['imagen']) ?>" alt="<?= htmlspecialchars($p['nombre_producto']) ?>" class="product-image">
                            </td>
                            <td class="product-name">
                                <span class="name-text"><?= htmlspecialchars($p['nombre_producto']) ?></span>
                            </td>
                            <td class="product-variant">
                                <?php if (!empty($p['variante'])): ?>
                                    <div class="variant-list">
                                        <?php foreach (explode(',', $p['variante']) as $v): ?>
                                            <span class="variant-tag"><?= htmlspecialchars(trim($v)) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="no-variant">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="product-quantity">
                                <span class="quantity-badge"><?= $p['cantidad'] ?></span>
                            </td>
                            <td class="product-price">$<?= number_format($p['precio_unitario'], 2) ?></td>
                            <td class="product-subtotal">$<?= number_format($p['cantidad'] * $p['precio_unitario'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php else: ?>
        <div class="no-products-card">
            <div class="no-products-content">
                <span class="no-products-icon">📦</span>
                <p class="no-products-message">No se encontraron productos para este pedido.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function copiarCodigo() {
    const input = document.getElementById('codigoRastreo');
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).then(() => {
        const mensaje = document.getElementById('mensajeCopiado');
        mensaje.style.display = 'block';
        setTimeout(() => mensaje.style.display = 'none', 2000);
    });
}

function formatoLatino(fechaStr) {
    if (!fechaStr) return 'No disponible';
    const fecha = new Date(fechaStr);
    const dia = String(fecha.getDate()).padStart(2, '0');
    const mes = String(fecha.getMonth() + 1).padStart(2, '0');
    const año = fecha.getFullYear();
    return `${dia}/${mes}/${año}`;
}

document.addEventListener('DOMContentLoaded', async () => {
    const codigo = document.getElementById('codigoRastreo').value;
    const resultado = document.getElementById('estadoEnvio');

    // Mostrar estado de carga
    resultado.innerHTML = `
        <div class="result-card loading">
            <div class="result-icon loading-icon">🔄</div>
            <div class="result-content">
                <h3 class="result-title">Consultando seguimiento...</h3>
                <p class="result-message">Obteniendo información de tu envío</p>
            </div>
        </div>
    `;

    try {
        const res = await fetch(`https://taponshop.tobuy.us:3000/tracking/${codigo}`);
        if (res.ok) {
            const data = await res.json();
            const estado = (data.estado || '').toLowerCase().trim();

            // Determinar el estilo según el estado
            let statusClass = 'success';
            let statusIcon = '✅';
            let statusTitle = 'Envío encontrado';

            if (estado === 'pendiente') {
                statusClass = 'pending';
                statusIcon = '⏳';
                statusTitle = 'Pedido pendiente';
            } else if (estado === 'en_transito' || estado === 'enviado') {
                statusClass = 'shipping';
                statusIcon = '🚚';
                statusTitle = 'En tránsito';
            } else if (estado === 'entregado') {
                statusClass = 'delivered';
                statusIcon = '📦';
                statusTitle = 'Entregado';
            }

            resultado.innerHTML = `
                <div class="result-card ${statusClass}">
                    <div class="result-header">
                        <div class="result-icon">${statusIcon}</div>
                        <div class="result-header-text">
                            <h3 class="result-title">${statusTitle}</h3>
                            <p class="result-code">Código: ${codigo}</p>
                        </div>
                    </div>
                    
                    <div class="tracking-details">
                        <div class="detail-grid">
                            <div class="detail-item">
                                <span class="detail-label">Estado actual:</span>
                                <span class="detail-value status-value">${data.estado}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Ubicación:</span>
                                <span class="detail-value">${data.ubicacion}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Empresa de envío:</span>
                                <span class="detail-value">${data.empresa_envio}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Fecha de envío:</span>
                                <span class="detail-value">${formatoLatino(data.fecha_envio)}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Entrega estimada:</span>
                                <span class="detail-value estimated-date">${formatoLatino(data.fecha_estimada_entrega)}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        } else {
            resultado.innerHTML = `
                <div class="result-card not-found">
                    <div class="result-icon">📭</div>
                    <div class="result-content">
                        <h3 class="result-title">Envío no encontrado</h3>
                        <p class="result-message">No se encontró información de seguimiento para el código: <strong>${codigo}</strong></p>
                        <p class="result-suggestion">Verifica que el código sea correcto o contacta con atención al cliente.</p>
                    </div>
                </div>
            `;
        }
    } catch (error) {
        resultado.innerHTML = `
            <div class="result-card error">
                <div class="result-icon">❌</div>
                <div class="result-content">
                    <h3 class="result-title">Error de conexión</h3>
                    <p class="result-message">No se pudo conectar con el servicio de seguimiento.</p>
                    <p class="result-suggestion">Por favor, verifica tu conexión e intenta nuevamente.</p>
                </div>
            </div>
        `;
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
