<?php
session_start();
if (!isset($_SESSION['firebase_uid']) || $_SESSION['rol'] !== 'admin') {
    header("Location: /index.php");
    exit;
}


require_once '../includes/db.php';
require_once '../vendor/autoload.php'; // Para Composer

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelMedium;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;

$pedido_id = $_GET['pedido_id'] ?? null;
if (!$pedido_id) {
    die("ID de pedido no proporcionado.");
}

// Obtener información del pedido
$stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?");
$stmt->execute([$pedido_id]);
$pedido = $stmt->fetch();

if (!$pedido) {
    die("Pedido no encontrado.");
}

// Obtener productos del pedido para el conteo
$stmt = $pdo->prepare("SELECT COUNT(*) as total_productos FROM pedido_detalle WHERE pedido_id = ?");
$stmt->execute([$pedido_id]);
$total_productos = $stmt->fetchColumn();

// Obtener información de seguimiento
$stmt = $pdo->prepare("SELECT * FROM seguimientos WHERE codigo = ?");
$stmt->execute([$pedido['identificador']]);
$seguimiento = $stmt->fetch();

// Procesar dirección de entrega
$direccion_entrega = '';
$info_entrega = '';

if ($pedido['tipo_entrega'] === 'domicilio' && !empty($pedido['comentarios'])) {
    $campos = explode(';', $pedido['comentarios']);
    $direccion_parts = [];
    
    foreach ($campos as $campo) {
        $campo = trim($campo);
        if ($campo !== '') {
            if (stripos($campo, 'Depto ID:') !== false) {
                $depto_id = (int) filter_var($campo, FILTER_SANITIZE_NUMBER_INT);
                $query = $pdo->prepare("SELECT nombre FROM departamentos WHERE id = ?");
                $query->execute([$depto_id]);
                $depto_nombre = $query->fetchColumn();
                $direccion_parts[] = "Departamento: " . ($depto_nombre ?: $depto_id);
            } elseif (stripos($campo, 'Municipio ID:') !== false) {
                $mun_id = (int) filter_var($campo, FILTER_SANITIZE_NUMBER_INT);
                $query = $pdo->prepare("SELECT nombre FROM municipios WHERE id = ?");
                $query->execute([$mun_id]);
                $mun_nombre = $query->fetchColumn();
                $direccion_parts[] = "Municipio: " . ($mun_nombre ?: $mun_id);
            } else {
                $direccion_parts[] = $campo;
            }
        }
    }
    $direccion_entrega = implode('<br>', $direccion_parts);
    $info_entrega = 'ENTREGA A DOMICILIO';
    
} elseif ($pedido['tipo_entrega'] === 'sucursal' && is_numeric($pedido['sucursal_id'])) {
    $query = $pdo->prepare("SELECT nombre, direccion FROM sucursales WHERE id = ?");
    $query->execute([$pedido['sucursal_id']]);
    $sucursal = $query->fetch();
    
    if ($sucursal) {
        $direccion_entrega = "<strong>" . htmlspecialchars($sucursal['nombre']) . "</strong><br>" . htmlspecialchars($sucursal['direccion']);
        $info_entrega = 'RETIRO EN SUCURSAL';
    }
}

// Información de la empresa
$empresa_envio = $seguimiento ? $seguimiento['empresa_envio'] : 'Perolito Express';
$fecha_envio = $seguimiento ? $seguimiento['fecha_envio'] : date('Y-m-d');
$fecha_entrega = $seguimiento ? $seguimiento['fecha_estimada_entrega'] : date('Y-m-d', strtotime('+3 days'));

// URL para el QR (cambiar localhost por tu URL de ngrok cuando la tengas)
$tracking_url = "http://taponshop.tobuy.us/tracking.php?codigo=" . urlencode($pedido['identificador']);

// Generar QR Code con la librería de Composer
$qr_data_uri = '';
try {
    $result = Builder::create()
        ->writer(new PngWriter())
        ->data($tracking_url)
        ->encoding(new Encoding('UTF-8'))
        ->errorCorrectionLevel(new ErrorCorrectionLevelMedium())
        ->size(120)  // Cambiar de 80 a 120
        ->margin(2)
        ->roundBlockSizeMode(new RoundBlockSizeModeMargin())
        ->build();
    
    $qr_data_uri = $result->getDataUri();
} catch (Exception $e) {
    $qr_data_uri = '';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Etiqueta de Envío - <?= htmlspecialchars($pedido['identificador']) ?></title>
    <style>
        @page {
            size: A4;
            margin: 0.4in;
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background-color: white;
            color: #333;
            font-size: 11px;
            line-height: 1.2;
        }
        
        .etiqueta-container {
            width: 100%;
            max-width: 7.5in; /* Ancho fijo para A4 */
            background: white;
            border: 2px solid #333;
            border-radius: 6px;
            overflow: hidden;
            margin: 0 auto;
            page-break-inside: avoid;
        }
        
        .header-etiqueta {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 12px;
            text-align: center;
        }
        
        .header-etiqueta h1 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .header-etiqueta .empresa {
            margin: 2px 0 0 0;
            font-size: 11px;
            opacity: 0.9;
        }
        
        .contenido-etiqueta {
            padding: 12px;
        }
        
        .tipo-entrega {
            background: #e6fffa;
            color: #234e52;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
            margin-bottom: 8px;
            border: 1px solid #81e6d9;
        }
        
        .seccion-principal {
            display: flex;
            gap: 12px;
            margin-bottom: 12px;
        }
        
        .remitente, .destinatario {
            flex: 1;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 8px;
        }
        
        .remitente {
            background: #f8fafc;
        }
        
        .destinatario {
            background: #fff5f5;
            border-color: #fed7d7;
        }
        
        .titulo-seccion {
            font-weight: 700;
            font-size: 10px;
            text-transform: uppercase;
            margin-bottom: 6px;
            color: #4a5568;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 2px;
        }
        
        .info-contacto {
            font-size: 10px;
            line-height: 1.3;
        }
        
        .codigo-seguimiento {
            background: #1a202c;
            color: white;
            padding: 8px;
            text-align: center;
            margin: 12px 0;
            border-radius: 4px;
        }
        
        .codigo-seguimiento .codigo {
            font-size: 14px;
            font-weight: 700;
            font-family: 'Courier New', monospace;
            letter-spacing: 1px;
            word-break: break-all;
        }
        
        .codigo-seguimiento .label {
            font-size: 9px;
            text-transform: uppercase;
            margin-bottom: 3px;
            opacity: 0.8;
        }
        
        .qr-section {
            background: #f0f8ff;
            border: 1px solid #4299e1;
            border-radius: 6px;
            padding: 12px;
            margin: 12px 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .qr-code {
            background: white;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
            flex-shrink: 0;
        }
        
        .qr-code img {
            display: block;
            width: 110px;
            height: 110px;
        }
        
        .qr-info {
            flex: 1;
        }
        
        .qr-info .qr-title {
            font-weight: 600;
            color: #2b6cb0;
            margin-bottom: 4px;
            font-size: 11px;
        }
        
        .qr-info .qr-instructions {
            font-size: 9px;
            color: #4a5568;
            line-height: 1.2;
        }
        
        .info-envio {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin: 12px 0;
        }
        
        .info-item {
            background: #f7fafc;
            padding: 6px;
            border-radius: 3px;
            border-left: 2px solid #667eea;
        }
        
        .info-item .label {
            font-size: 8px;
            text-transform: uppercase;
            color: #718096;
            margin-bottom: 1px;
        }
        
        .info-item .valor {
            font-weight: 600;
            color: #2d3748;
            font-size: 10px;
        }
        
        .package-summary {
            background: #f0fff4;
            border: 1px solid #c6f6d5;
            border-radius: 4px;
            padding: 8px;
            margin: 12px 0;
        }
        
        .package-summary h3 {
            margin: 0 0 6px 0;
            color: #22543d;
            font-size: 11px;
        }
        
        .package-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 6px;
            font-size: 10px;
        }
        
        .package-item {
            background: white;
            padding: 4px 6px;
            border-radius: 3px;
            border: 1px solid #e2e8f0;
        }
        
        .package-item .label {
            font-size: 8px;
            color: #718096;
            text-transform: uppercase;
            margin-bottom: 1px;
        }
        
        .package-item .value {
            font-weight: 600;
            color: #2d3748;
        }
        
        .instrucciones {
            background: #fffaf0;
            border: 1px solid #fbd38d;
            border-radius: 4px;
            padding: 8px;
            margin-top: 12px;
        }
        
        .instrucciones h4 {
            margin: 0 0 6px 0;
            color: #c05621;
            font-size: 10px;
        }
        
        .instrucciones ul {
            margin: 0;
            padding-left: 12px;
            font-size: 8px;
            color: #744210;
        }
        
        .instrucciones li {
            margin-bottom: 1px;
        }
        
        .footer-etiqueta {
            background: #f7fafc;
            padding: 6px 12px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 8px;
            color: #718096;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .etiqueta-container {
                border: 2px solid #000;
                box-shadow: none;
                page-break-inside: avoid;
                max-width: none;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="etiqueta-container">
        <div class="header-etiqueta">
            <h1>Etiqueta de Envío</h1>
            <div class="empresa"><?= htmlspecialchars($empresa_envio) ?></div>
        </div>
        
        <div class="contenido-etiqueta">
            <div class="tipo-entrega"><?= $info_entrega ?></div>
            
            <div class="seccion-principal">
                <div class="remitente">
                    <div class="titulo-seccion">📦 Remitente</div>
                    <div class="info-contacto">
                        <strong>TaponShop</strong><br>
                        Av. Principal #123<br>
                        San Salvador, El Salvador<br>
                        📞 +503 1-800-Taponsito<br>
                        📧 ventas@taponshop.com
                    </div>
                </div>
                
                <div class="destinatario">
                    <div class="titulo-seccion">🏠 Destinatario</div>
                    <div class="info-contacto">
                        <strong><?= htmlspecialchars($pedido['email']) ?></strong><br>
                        <?= $direccion_entrega ?>
                    </div>
                </div>
            </div>
            
            <div class="codigo-seguimiento">
                <div class="label">Código de Seguimiento</div>
                <div class="codigo"><?= htmlspecialchars($pedido['identificador']) ?></div>
            </div>
            
            <!-- Sección QR Code -->
            <div class="qr-section">
                <div class="qr-code">
                    <?php if ($qr_data_uri): ?>
                        <img src="<?= $qr_data_uri ?>" alt="QR Code" />
                    <?php else: ?>
                        <div style="width: 110px; height: 110px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #666;">
                            Error QR
                        </div>
                    <?php endif; ?>
                </div>
                <div class="qr-info">
                    <div class="qr-title">📱 Escanea para rastrear</div>
                    <div class="qr-instructions">
                        • Escanea con tu teléfono<br>
                        • Seguimiento en tiempo real<br>
                        • Actualizaciones automáticas
                    </div>
                </div>
            </div>
            
            <div class="info-envio">
                <div class="info-item">
                    <div class="label">Fecha Envío</div>
                    <div class="valor"><?= date('d/m/Y', strtotime($fecha_envio)) ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Entrega Est.</div>
                    <div class="valor"><?= date('d/m/Y', strtotime($fecha_entrega)) ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Peso Est.</div>
                    <div class="valor"><?= $total_productos ?> kg</div>
                </div>
                <div class="info-item">
                    <div class="label">Total</div>
                    <div class="valor">$<?= number_format($pedido['monto'], 2) ?></div>
                </div>
            </div>
            
            <div class="package-summary">
                <h3>📋 Resumen del Paquete</h3>
                <div class="package-info">
                    <div class="package-item">
                        <div class="label">Productos</div>
                        <div class="value"><?= $total_productos ?> artículos</div>
                    </div>
                    <div class="package-item">
                        <div class="label">Estado Pago</div>
                        <div class="value"><?= ucfirst($pedido['estado']) ?></div>
                    </div>
                </div>
            </div>
            
            <div class="instrucciones">
                <h4>⚠️ Instrucciones</h4>
                <ul>
                    <li>Manipular con cuidado</li>
                    <li>Mantener en posición vertical</li>
                    <li>Proteger de la humedad</li>
                    <li>Entregar solo al destinatario</li>
                    <li>Verificar código antes de entrega</li>
                </ul>
            </div>
        </div>
        
        <div class="footer-etiqueta">
            <strong>Generado:</strong> <?= date('d/m/Y H:i') ?> | 
            <strong>Pedido:</strong> #<?= $pedido['id'] ?> | 
            <strong>Estado:</strong> <?= ucfirst($pedido['estado']) ?>
        </div>
    </div>
    
    <script>
        // Auto-imprimir al cargar la página
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        }
    </script>
</body>
</html>
