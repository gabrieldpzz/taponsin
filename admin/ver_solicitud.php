<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/email.php';
require_once '../includes/header.php';

if (!isset($_SESSION['firebase_uid']) || $_SESSION['rol'] !== 'admin') {
    header("Location: /index.php");
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Detalle Solicitud</title>
        <link rel="stylesheet" href="/assets/css/ver_solicitudes.css">
    </head>
    <body>
        <div class="container">
            <div class="error-card">
                <div class="error-icon">⚠️</div>
                <h2>Error</h2>
                <p>ID de solicitud no proporcionado.</p>
                <a href="solicitudes_proveedores.php" class="btn btn-info">Volver a Solicitudes</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$stmt = $pdo->prepare("
    SELECT sp.*, u.email 
    FROM solicitudes_proveedor sp
    LEFT JOIN usuarios u ON sp.firebase_uid = u.firebase_uid
    WHERE sp.id = ?
");
$stmt->execute([$id]);
$solicitud = $stmt->fetch();

if (!$solicitud) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Detalle Solicitud</title>
        <link rel="stylesheet" href="/assets/css/ver_solicitudes.css">
    </head>
    <body>
        <div class="container">
            <div class="error-card">
                <div class="error-icon">❌</div>
                <h2>Solicitud No Encontrada</h2>
                <p>La solicitud que buscas no existe o ha sido eliminada.</p>
                <a href="solicitudes_proveedores.php" class="btn btn-info">Volver a Solicitudes</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'];

    if ($accion === 'aceptar') {
        $stmt = $pdo->prepare("INSERT INTO proveedores (nombre, correo, firebase_uid, fecha_registro, comision_porcentaje) VALUES (?, ?, ?, NOW(), 10)");
        $stmt->execute([$solicitud['nombre_proveedor'], $solicitud['email'], $solicitud['firebase_uid']]);

        $stmt = $pdo->prepare("UPDATE solicitudes_proveedor SET estado = 'aceptado' WHERE id = ?");
        $stmt->execute([$id]);

        $stmt = $pdo->prepare("UPDATE usuarios SET rol = 'proveedor' WHERE firebase_uid = ?");
        $stmt->execute([$solicitud['firebase_uid']]);

        // 📩 Enviar correo al proveedor
        $template = file_get_contents('../includes/plantillas/plantilla_aprobacion.html');
        $mensaje  = str_replace('{NOMBRE}', htmlspecialchars($solicitud['nombre_proveedor']), $template);
        enviarCorreo($solicitud['email'], '🎉 ¡Tu cuenta ha sido aprobada!', $mensaje);

        header("Location: solicitudes_proveedores.php");
        exit;

    } elseif ($accion === 'rechazar') {
        $stmt = $pdo->prepare("UPDATE solicitudes_proveedor SET estado = 'rechazado' WHERE id = ?");
        $stmt->execute([$id]);

        // 📩 Enviar correo de rechazo al proveedor
        $template = file_get_contents('../includes/plantillas/plantilla_rechazo.html');
        $mensaje  = str_replace('{NOMBRE}', htmlspecialchars($solicitud['nombre_proveedor']), $template);
        enviarCorreo($solicitud['email'], '❌ Solicitud de proveedor rechazada', $mensaje);

        header("Location: solicitudes_proveedores.php");
        exit;
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle Solicitud</title>
    <link rel="stylesheet" href="/assets/css/ver_solicitudes.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-title">
                    <h1>📄 Detalle de Solicitud de Proveedor</h1>
                    <p class="header-subtitle">Revisa la información y documentación del solicitante</p>
                </div>
                <div class="header-actions">
                    <a href="solicitudes_proveedores.php" class="btn btn-secondary">
                        <span class="btn-icon">🔙</span>
                        Volver
                    </a>
                </div>
            </div>
        </div>

        <!-- Estado de la solicitud -->
        <div class="status-section">
            <div class="status-card <?= strtolower($solicitud['estado']) ?>">
                <div class="status-icon">
                    <?php
                    switch($solicitud['estado']) {
                        case 'espera': echo '⏳'; break;
                        case 'aceptado': echo '✅'; break;
                        case 'rechazado': echo '❌'; break;
                        default: echo '📋'; break;
                    }
                    ?>
                </div>
                <div class="status-content">
                    <span class="status-label">Estado actual:</span>
                    <span class="status-value"><?= ucfirst(htmlspecialchars($solicitud['estado'])) ?></span>
                </div>
            </div>
        </div>

        <!-- Información del solicitante -->
        <div class="info-section">
            <h2 class="section-title">👤 Información del Solicitante</h2>
            <div class="info-card">
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">📛 Nombre:</span>
                        <span class="info-value"><?= htmlspecialchars($solicitud['nombre_proveedor']) ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">📧 Correo:</span>
                        <span class="info-value"><?= htmlspecialchars($solicitud['email']) ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">📞 Teléfono:</span>
                        <span class="info-value"><?= htmlspecialchars($solicitud['telefono']) ?></span>
                    </div>
                    
                    <div class="info-item full-width">
                        <span class="info-label">📦 Descripción del negocio:</span>
                        <div class="info-description">
                            <?= nl2br(htmlspecialchars($solicitud['descripcion'])) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Documentación -->
        <div class="documents-section">
            <h2 class="section-title">📋 Documentación Proporcionada</h2>
            
            <div class="documents-grid">
                <!-- Documento de identidad -->
                <div class="document-card">
                    <div class="document-header">
                        <h3>🪪 Documento de Identidad</h3>
                    </div>
                    <div class="document-content">
                        <?php if ($solicitud['documento_url']): ?>
                            <div class="document-preview">
                                <img src="/proveedor/documentos_proveedor/<?= urlencode($solicitud['documento_url']) ?>" 
                                     alt="Documento de identidad"
                                     onclick="openImageModal(this.src, 'Documento de Identidad')">
                                <div class="preview-overlay">
                                    <span class="preview-text">👁️ Click para ampliar</span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="document-missing">
                                <span class="missing-icon">❌</span>
                                <span class="missing-text">No se proporcionó documento</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Foto del rostro -->
                <div class="document-card">
                    <div class="document-header">
                        <h3>📷 Foto del Rostro</h3>
                    </div>
                    <div class="document-content">
                        <?php if (!empty($solicitud['foto_cara_url'])): ?>
                            <div class="document-preview">
                                <img src="/proveedor/documentos_proveedor/<?= htmlspecialchars($solicitud['foto_cara_url']) ?>" 
                                     alt="Foto del rostro"
                                     onclick="openImageModal(this.src, 'Foto del Rostro')">
                                <div class="preview-overlay">
                                    <span class="preview-text">👁️ Click para ampliar</span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="document-missing">
                                <span class="missing-icon">❌</span>
                                <span class="missing-text">No se proporcionó foto</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Acciones -->
        <div class="actions-section">
            <div class="actions-card">
                <?php if ($solicitud['estado'] === 'espera'): ?>
                    <h3 class="actions-title">⚡ Acciones Disponibles</h3>
                    <p class="actions-description">Revisa cuidadosamente la información y documentación antes de tomar una decisión.</p>
                    
                    <form method="post" class="actions-form">
                        <div class="actions-buttons">
                            <button type="submit" name="accion" value="aceptar" class="btn btn-success btn-large"
                                    onclick="return confirm('¿Estás seguro de aceptar esta solicitud? El usuario será registrado como proveedor.')">
                                <span class="btn-icon">✅</span>
                                Aceptar Solicitud
                            </button>
                            
                            <button type="submit" name="accion" value="rechazar" class="btn btn-danger btn-large"
                                    onclick="return confirm('¿Estás seguro de rechazar esta solicitud? Esta acción no se puede deshacer.')">
                                <span class="btn-icon">❌</span>
                                Rechazar Solicitud
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="processed-notice">
                        <div class="notice-icon">⚠️</div>
                        <div class="notice-content">
                            <h3>Solicitud Ya Procesada</h3>
                            <p>Esta solicitud ya fue <?= $solicitud['estado'] === 'aceptado' ? 'aceptada' : 'rechazada' ?> anteriormente.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal para ampliar imágenes -->
    <div id="imageModal" class="modal" onclick="closeImageModal()">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <img id="modalImage" src="/placeholder.svg" alt="">
            <div class="modal-caption" id="modalCaption"></div>
        </div>
    </div>

    <script>
        function openImageModal(src, caption) {
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            const modalCaption = document.getElementById('modalCaption');
            
            modal.style.display = 'block';
            modalImg.src = src;
            modalCaption.textContent = caption;
        }

        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
        }

        // Cerrar modal con tecla Escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeImageModal();
            }
        });
    </script>
</body>
</html>