<?php
require '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

session_start();
if (!isset($_SESSION['firebase_email'])) {
    die("Acceso denegado.");
}

require_once '../includes/db.php';

$pedido_id = $_GET['pedido_id'] ?? null;
if (!$pedido_id) {
    die("ID de pedido no proporcionado.");
}

// Obtener el pedido
$stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ? AND firebase_uid = ?");
$stmt->execute([$pedido_id, $_SESSION['firebase_uid']]);
$pedido = $stmt->fetch();

if (!$pedido) {
    die("Pedido no encontrado.");
}

// Obtener detalles del pedido con imagen
$detalles = $pdo->prepare("
    SELECT d.*, p.nombre, p.imagen
    FROM pedido_detalle d
    JOIN productos p ON d.producto_id = p.id
    WHERE d.pedido_id = ?
");
$detalles->execute([$pedido_id]);
$productos = $detalles->fetchAll();

// Extraer dirección desde comentarios o sucursal
$direccion = '-';
if ($pedido['tipo_entrega'] === 'domicilio' && !empty($pedido['comentarios'])) {
    $campos = explode(';', $pedido['comentarios']);
    $direccion_items = [];
    foreach ($campos as $campo) {
        $campo = trim($campo);
        if ($campo !== '') {
            if (stripos($campo, 'Depto ID:') !== false) {
                $depto_id = (int) filter_var($campo, FILTER_SANITIZE_NUMBER_INT);
                $query = $pdo->prepare("SELECT nombre FROM departamentos WHERE id = ?");
                $query->execute([$depto_id]);
                $depto_nombre = $query->fetchColumn();
                $campo = "Depto: " . ($depto_nombre ?: $depto_id);
            } elseif (stripos($campo, 'Municipio ID:') !== false) {
                $mun_id = (int) filter_var($campo, FILTER_SANITIZE_NUMBER_INT);
                $query = $pdo->prepare("SELECT nombre FROM municipios WHERE id = ?");
                $query->execute([$mun_id]);
                $mun_nombre = $query->fetchColumn();
                $campo = "Mun: " . ($mun_nombre ?: $mun_id);
            }
            $direccion_items[] = htmlspecialchars($campo);
        }
    }
    $direccion = implode(', ', $direccion_items);
} elseif ($pedido['tipo_entrega'] === 'sucursal' && is_numeric($pedido['sucursal_id'])) {
    $query = $pdo->prepare("SELECT nombre, direccion FROM sucursales WHERE id = ?");
    $query->execute([$pedido['sucursal_id']]);
    $sucursal = $query->fetch();
    if ($sucursal) {
        $direccion = "Sucursal: " . htmlspecialchars($sucursal['nombre']) . " - " . htmlspecialchars($sucursal['direccion']);
    } else {
        $direccion = "Sucursal no encontrada (ID: {$pedido['sucursal_id']})";
    }
}

// Generar HTML compacto
$html = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 15px;
            background-color: white;
            color: #2d3748;
            line-height: 1.4;
            font-size: 12px;
        }
        .container {
            max-width: 100%;
            margin: 0 auto;
            background: white;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            text-align: center;
            margin-bottom: 15px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
        }
        .pedido-id {
            background: #667eea;
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-weight: 700;
            font-size: 14px;
            display: inline-block;
            margin-bottom: 10px;
        }
        .invoice-info {
            display: table;
            width: 100%;
            margin-bottom: 15px;
            background: #f8fafc;
            padding: 12px;
            border-radius: 6px;
            border-left: 3px solid #667eea;
        }
        .invoice-left, .invoice-right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 10px;
        }
        .info-item {
            margin-bottom: 6px;
            font-size: 11px;
        }
        .info-label {
            font-weight: 600;
            color: #4a5568;
            display: inline-block;
            width: 80px;
        }
        .info-value {
            color: #2d3748;
        }
        .products-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 11px;
        }
        .products-table th {
            background: #4a5568;
            color: white;
            padding: 8px 6px;
            text-align: left;
            font-weight: 600;
            font-size: 10px;
        }
        .products-table td {
            padding: 8px 6px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        .products-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .product-image {
            text-align: center;
            width: 50px;
        }
        .product-name {
            font-weight: 600;
            color: #2d3748;
            font-size: 11px;
        }
        .product-variant {
            color: #718096;
            font-size: 10px;
        }
        .price {
            font-weight: 600;
            color: #38a169;
            text-align: right;
        }
        .quantity {
            text-align: center;
            font-weight: 600;
        }
        .total-section {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
            padding: 12px;
            border-radius: 6px;
            text-align: center;
            margin: 10px 0;
        }
        .total-amount {
            font-size: 20px;
            font-weight: 700;
            margin: 5px 0;
        }
        .total-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .footer {
            text-align: center;
            padding: 10px;
            color: #718096;
            font-size: 11px;
        }
        .divider {
            height: 1px;
            background: #e2e8f0;
            margin: 10px 0;
            border: none;
        }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Factura de Compra</h1>
        </div>
        
        <div class='pedido-id'>Pedido #{$pedido['id']}</div>
        
        <div class='invoice-info'>
            <div class='invoice-left'>
                <div class='info-item'>
                    <span class='info-label'>ID:</span>
                    <span class='info-value'>{$pedido['identificador']}</span>
                </div>
                <div class='info-item'>
                    <span class='info-label'>Fecha:</span>
                    <span class='info-value'>{$pedido['fecha']}</span>
                </div>
                <div class='info-item'>
                    <span class='info-label'>Email:</span>
                    <span class='info-value'>{$pedido['email']}</span>
                </div>
            </div>
            <div class='invoice-right'>
                <div class='info-item'>
                    <span class='info-label'>Pago:</span>
                    <span class='info-value'>{$pedido['forma_pago']}</span>
                </div>
                <div class='info-item'>
                    <span class='info-label'>Entrega:</span>
                    <span class='info-value'>{$pedido['tipo_entrega']}</span>
                </div>
                <div class='info-item'>
                    <span class='info-label'>Dirección:</span>
                    <span class='info-value'>{$direccion}</span>
                </div>
            </div>
        </div>
        
        <hr class='divider'>
        
        <table class='products-table'>
            <thead>
                <tr>
                    <th style='width: 50px;'>Imagen</th>
                    <th>Producto</th>
                    <th style='width: 80px;'>Variante</th>
                    <th style='width: 70px;'>Precio</th>
                    <th style='width: 40px;'>Cant.</th>
                    <th style='width: 70px;'>Total</th>
                </tr>
            </thead>
            <tbody>";

foreach ($productos as $prod) {
    $subtotal = $prod['precio_unitario'] * $prod['cantidad'];
    $imagen_url = $prod['imagen'];
    $imagen_html = '<div style="color: #a0aec0; font-size: 10px;">-</div>';

    // Validar extensión
    $ext = strtolower(pathinfo(parse_url($imagen_url, PHP_URL_PATH), PATHINFO_EXTENSION));
    $formatos_validos = ['jpg', 'jpeg', 'png', 'gif'];

    if (in_array($ext, $formatos_validos) && filter_var($imagen_url, FILTER_VALIDATE_URL)) {
        $img_data = @file_get_contents($imagen_url);
        if ($img_data !== false) {
            $mime = match($ext) {
                'png' => 'image/png',
                'gif' => 'image/gif',
                default => 'image/jpeg'
            };
            $base64 = base64_encode($img_data);
            $imagen_html = "<img src='data:{$mime};base64,{$base64}' style='width: 40px; height: 40px; object-fit: cover; border-radius: 4px;'>";
        }
    }

    $html .= "<tr>
        <td class='product-image'>{$imagen_html}</td>
        <td>
            <div class='product-name'>" . htmlspecialchars($prod['nombre']) . "</div>
        </td>
        <td>
            <div class='product-variant'>" . htmlspecialchars($prod['variante'] ?? '-') . "</div>
        </td>
        <td class='price'>$" . number_format($prod['precio_unitario'], 2) . "</td>
        <td class='quantity'>{$prod['cantidad']}</td>
        <td class='price'>$" . number_format($subtotal, 2) . "</td>
    </tr>";
}

// Calcular subtotal de todos los productos
$subtotal_general = 0;
foreach ($productos as $prod) {
    $subtotal_general += $prod['precio_unitario'] * $prod['cantidad'];
}

$html .= "<tr style='border-top: 2px solid #4a5568; background-color: #f1f5f9;'>
    <td colspan='5' style='text-align: right; font-weight: 700; color: #2d3748; padding: 10px 6px;'>SUBTOTAL:</td>
    <td class='price' style='font-weight: 700; font-size: 12px; background-color: #e2e8f0; padding: 10px 6px;'>$" . number_format($subtotal_general, 2) . "</td>
</tr>";

$html .= "</tbody>
        </table>
        
        <div class='total-section'>
            <div class='total-label'>Total Pagado</div>
            <div class='total-amount'>$" . number_format($pedido['monto'], 2) . "</div>
        </div>
        
        <div class='footer'>
            <strong>¡Gracias por tu compra!</strong>
        </div>
    </div>
</body>
</html>";

// Configurar Dompdf
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("factura_{$pedido_id}.pdf", ["Attachment" => false]);
?>
