<?php
require_once './includes/header.php'; // Solo si header.php existe en ./includes/
require_once __DIR__ . '/includes/db.php';
?>

<link rel="stylesheet" href="/assets/css/tracking.css">

<div class="tracking-container">
    <div class="page-header">
        <h2 class="page-title">🔍 Rastrear Envío</h2>
        <p class="page-subtitle">Ingresa tu código de pedido para consultar el estado actual de tu envío</p>
    </div>

    <div class="tracking-form-card">
        <div class="form-section">
            <div class="input-group">
                <label for="codigoInput" class="form-label">Código de pedido:</label>
                <input type="text" id="codigoInput" placeholder="Ej: pedido_xxxxxx" class="tracking-input">
            </div>
            <button onclick="consultarSeguimiento()" class="btn btn-primary btn-large">
                <span class="btn-icon">🔍</span>
                Consultar seguimiento
            </button>
        </div>
    </div>

    <div id="resultado" class="result-container"></div>
</div>

<script>
function formatoLatino(fechaStr) {
    if (!fechaStr) return 'No disponible';
    const fecha = new Date(fechaStr);
    const dia = String(fecha.getDate()).padStart(2, '0');
    const mes = String(fecha.getMonth() + 1).padStart(2, '0');
    const año = fecha.getFullYear();
    return `${dia}/${mes}/${año}`;
}

async function consultarSeguimiento() {
    const codigo = document.getElementById('codigoInput').value.trim();
    const resultado = document.getElementById('resultado');

    if (!codigo) {
        resultado.innerHTML = `
            <div class="result-card error">
                <div class="result-icon">⚠️</div>
                <div class="result-content">
                    <h3 class="result-title">Código requerido</h3>
                    <p class="result-message">Por favor, ingresa un código de pedido válido.</p>
                </div>
            </div>
        `;
        return;
    }

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
                    
                    <div class="tracking-actions">
                        <button onclick="consultarSeguimiento()" class="btn btn-secondary btn-small">
                            <span class="btn-icon">🔄</span>
                            Actualizar
                        </button>
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
}

function compartirSeguimiento(codigo) {
    const url = `${window.location.origin}${window.location.pathname}?codigo=${codigo}`;
    if (navigator.share) {
        navigator.share({
            title: 'Seguimiento de envío',
            text: `Rastrear envío con código: ${codigo}`,
            url: url
        });
    } else {
        navigator.clipboard.writeText(url).then(() => {
            alert('Enlace copiado al portapapeles');
        });
    }
}

// Auto-consultar si hay código en la URL
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const codigo = urlParams.get('codigo');
    if (codigo) {
        document.getElementById('codigoInput').value = codigo;
        consultarSeguimiento();
    }
});

// Permitir consulta con Enter
document.getElementById('codigoInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        consultarSeguimiento();
    }
});
</script>

<?php require_once './includes/footer.php'; ?>
