<?php
session_start();
if (!isset($_SESSION['firebase_uid']) || $_SESSION['rol'] !== 'admin') {
    header("Location: /index.php");
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once '../includes/db.php';
require_once '../includes/header.php';

$pedido_id = $_GET['id'] ?? null;
if (!$pedido_id) {
    echo "Pedido no especificado.";
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?");
$stmt->execute([$pedido_id]);
$pedido = $stmt->fetch();

if (!$pedido) {
    echo "Pedido no encontrado.";
    exit;
}

$stmt = $pdo->prepare("
    SELECT pd.*, pr.nombre AS nombre_producto, pr.imagen
    FROM pedido_detalle pd
    JOIN productos pr ON pd.producto_id = pr.id
    WHERE pd.pedido_id = ?
");
$stmt->execute([$pedido_id]);
$productos = $stmt->fetchAll();
?>

<link rel="stylesheet" href="/assets/css/detalle_pedido.css?v=2">

<div class="admin-container">
    <div class="page-header">
        <h2>Detalles del Pedido</h2>
        <div class="order-id">
            <span class="order-label">ID:</span>
            <code class="order-code"><?= htmlspecialchars($pedido['identificador']) ?></code>
        </div>
        <!-- Botón de generar etiqueta en el header -->
        <div class="header-actions">
            <button type="button" onclick="generarEtiqueta()" class="btn-etiqueta-header">
                <span class="btn-icon">🏷️</span>
                Generar Etiqueta de Envío
            </button>
        </div>
    </div>

    <div class="content-grid">
        <!-- Información del Pedido -->
        <div class="info-card">
            <div class="card-header">
                <h3>Información del Pedido</h3>
            </div>
            <div class="card-content">
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Cliente:</span>
                        <span class="info-value"><?= htmlspecialchars($pedido['email']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Fecha:</span>
                        <span class="info-value"><?= date('d/m/Y H:i', strtotime($pedido['fecha'])) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Estado de pago:</span>
                        <span class="status-badge status-<?= strtolower($pedido['estado']) ?>"><?= ucfirst($pedido['estado']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Total:</span>
                        <span class="info-value total-amount">$<?= number_format($pedido['monto'], 2) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Código de Rastreo -->
        <div class="tracking-card">
            <div class="card-header">
                <h3>Código de Rastreo</h3>
            </div>
            <div class="card-content">
                <div class="tracking-code-section">
                    <input type="text" id="codigoRastreo" value="<?= htmlspecialchars($pedido['identificador']) ?>" readonly class="tracking-input">
                    <button type="button" onclick="copiarCodigo()" class="btn-copy">
                        <span class="copy-icon">📋</span>
                        Copiar
                    </button>
                </div>
                <p id="mensajeCopiado" class="copy-message">¡Código copiado al portapapeles!</p>
            </div>
        </div>
    </div>

    <!-- Seguimiento de Envío -->
    <div class="shipping-card">
        <div class="card-header" style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h3>Seguimiento de Envío</h3>
                <p>Información actualizada del estado del envío</p>
            </div>
            <button id="btnEditarSeguimiento" type="button" class="btn-primary" style="min-width:120px;">
                <span class="btn-icon">✏️</span>
                Editar
            </button>
        </div>
        <div class="card-content">
            <div id="seguimientoContainer" class="tracking-info">
                <div class="loading-state">
                    <div class="loading-spinner"></div>
                    <p>Cargando información de seguimiento...</p>
                </div>
            </div>

            <form id="editarSeguimientoForm" class="tracking-form" style="display:none;">
                <input type="hidden" id="codigoPedido" value="<?= htmlspecialchars($pedido['identificador']) ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label for="estadoSeguimiento">Estado del envío</label>
                        <select id="estadoSeguimiento" class="form-select">
                            <option value="pendiente">Pendiente</option>
                            <option value="enviado">Enviado</option>
                            <option value="entregado">Entregado</option>
                            <option value="cancelado">Cancelado</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="ubicacionSeguimiento">Ubicación actual</label>
                        <input type="text" id="ubicacionSeguimiento" class="form-input" placeholder="Ej: Centro de distribución - Ciudad">
                    </div>

                    <div class="form-group">
                        <label for="empresaSeguimiento">Empresa de envío</label>
                        <select id="empresaSeguimiento" class="form-select">
                            <option value="Perolito Express">Perolito Express</option>
                            <option value="RapidGo">RapidGo</option>
                            <option value="FlashLogistics">FlashLogistics</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="fechaEnvio">Fecha de envío</label>
                        <input type="date" id="fechaEnvio" class="form-input">
                    </div>

                    <div class="form-group">
                        <label for="fechaEntrega">Fecha estimada de entrega</label>
                        <input type="date" id="fechaEntrega" class="form-input">
                    </div>
                </div>

                <div style="display: flex; gap: 16px; justify-content: flex-end;">
                    <button type="button" id="btnCancelarEdicion" class="btn-back" style="min-width:120px;">
                        <span class="back-icon">✖️</span>
                        Cancelar
                    </button>
                    <button type="button" onclick="actualizarSeguimiento()" class="btn-primary" style="min-width:120px;">
                        <span class="btn-icon">💾</span>
                        Guardar
                    </button>
                </div>
            </form>

            <div id="seguimientoMensaje" class="update-message"></div>
        </div>
    </div>

    <!-- Productos del Pedido -->
    <?php if ($productos): ?>
    <div class="products-card">
        <div class="card-header">
            <h3>Productos del Pedido</h3>
            <p><?= count($productos) ?> producto<?= count($productos) !== 1 ? 's' : '' ?> en este pedido</p>
        </div>
        <div class="card-content">
            <div class="products-table-container">
                <table class="products-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Variante</th>
                            <th>Cantidad</th>
                            <th>Precio unitario</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productos as $p): ?>
                        <tr>
                            <td>
                                <div class="product-info">
                                    <img src="<?= htmlspecialchars($p['imagen']) ?>" alt="<?= htmlspecialchars($p['nombre_producto']) ?>" class="product-image">
                                    <span class="product-name"><?= htmlspecialchars($p['nombre_producto']) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="product-variant"><?= htmlspecialchars($p['variante'] ?: '—') ?></span>
                            </td>
                            <td>
                                <span class="quantity-badge"><?= $p['cantidad'] ?></span>
                            </td>
                            <td>
                                <span class="price">$<?= number_format($p['precio_unitario'], 2) ?></span>
                            </td>
                            <td>
                                <span class="price subtotal">$<?= number_format($p['cantidad'] * $p['precio_unitario'], 2) ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="empty-products-card">
        <div class="empty-state">
            <div class="empty-icon">📦</div>
            <h4>No hay productos</h4>
            <p>No se encontraron productos para este pedido.</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Navegación -->
    <div class="navigation-section">
        <a href="pedidos.php" class="btn-back">
            <span class="back-icon">←</span>
            Volver a lista de pedidos
        </a>
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
        setTimeout(() => mensaje.style.display = 'none', 3000);
    });
}

function formatoInput(fecha) {
    return new Date(fecha).toISOString().split('T')[0];
}

document.addEventListener('DOMContentLoaded', obtenerSeguimiento);

async function obtenerSeguimiento() {
    const codigo = document.getElementById('codigoPedido').value;
    const container = document.getElementById('seguimientoContainer');

    try {
        const res = await fetch(`https://taponshop.tobuy.us:3000/tracking/${codigo}`);
        if (res.ok) {
            const data = await res.json();

            container.innerHTML = `
                <div class="tracking-status">
                    <div class="status-item">
                        <span class="status-label">Estado:</span>
                        <span class="status-badge status-${data.estado}">${data.estado}</span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Ubicación:</span>
                        <span class="status-value">${data.ubicacion}</span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Empresa:</span>
                        <span class="status-value">${data.empresa_envio}</span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Fecha envío:</span>
                        <span class="status-value">${data.fecha_envio.split('T')[0]}</span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Entrega estimada:</span>
                        <span class="status-value">${data.fecha_estimada_entrega.split('T')[0]}</span>
                    </div>
                </div>
            `;

            document.getElementById('estadoSeguimiento').value = data.estado;
            document.getElementById('ubicacionSeguimiento').value = data.ubicacion;
            document.getElementById('empresaSeguimiento').value = data.empresa_envio;
            document.getElementById('fechaEnvio').value = formatoInput(data.fecha_envio);
            document.getElementById('fechaEntrega').value = formatoInput(data.fecha_estimada_entrega);
            document.getElementById('editarSeguimientoForm').style.display = 'block';
        } else {
            container.innerHTML = `
                <div class="no-tracking">
                    <div class="no-tracking-icon">📍</div>
                    <p>No hay información de seguimiento disponible para este pedido.</p>
                </div>
            `;
        }
    } catch (error) {
        container.innerHTML = `
            <div class="error-tracking">
                <div class="error-icon">⚠️</div>
                <p>Error al consultar la información de seguimiento.</p>
            </div>
        `;
    }
}

async function actualizarSeguimiento() {
    const codigo = document.getElementById('codigoPedido').value;
    const estado = document.getElementById('estadoSeguimiento').value;
    const ubicacion = document.getElementById('ubicacionSeguimiento').value;
    const empresa = document.getElementById('empresaSeguimiento').value;
    const fecha_envio = document.getElementById('fechaEnvio').value;
    const fecha_estimada_entrega = document.getElementById('fechaEntrega').value;

    const payload = {
        codigo,
        estado,
        ubicacion,
        empresa_envio: empresa,
        fecha_envio,
        fecha_estimada_entrega
    };

    try {
        const res = await fetch('https://taponshop.tobuy.us:3000/tracking', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const data = await res.json();
        const mensaje = document.getElementById('seguimientoMensaje');

        if (res.ok) {
            mensaje.innerHTML = '<span class="success-icon">✅</span> Seguimiento actualizado correctamente.';
            mensaje.className = 'update-message success';
            obtenerSeguimiento();
        } else {
            mensaje.innerHTML = '<span class="error-icon">❌</span> Error: ' + (data.error || 'No se pudo actualizar.');
            mensaje.className = 'update-message error';
        }
        
        mensaje.style.display = 'block';
        setTimeout(() => mensaje.style.display = 'none', 5000);
    } catch (error) {
        const mensaje = document.getElementById('seguimientoMensaje');
        mensaje.innerHTML = '<span class="error-icon">❌</span> Error de red al contactar con la API.';
        mensaje.className = 'update-message error';
        mensaje.style.display = 'block';
        setTimeout(() => mensaje.style.display = 'none', 5000);
    }
}

document.getElementById('btnEditarSeguimiento').addEventListener('click', function() {
    document.getElementById('editarSeguimientoForm').style.display = 'block';
    this.style.display = 'none';
});
document.getElementById('btnCancelarEdicion').addEventListener('click', function() {
    document.getElementById('editarSeguimientoForm').style.display = 'none';
    document.getElementById('btnEditarSeguimiento').style.display = 'inline-flex';
});

function generarEtiqueta() {
    const pedidoId = <?= $pedido_id ?>;
    window.open(`generar-etiqueta.php?pedido_id=${pedidoId}`, '_blank');
}
</script>

<?php require_once '../includes/footer.php'; ?>
