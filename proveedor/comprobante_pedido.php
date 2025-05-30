<?php
require '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

session_start();
if (!isset($_SESSION['firebase_uid'])) {
    die("Acceso denegado.");
}

require_once '../includes/db.php';

$pedido_id = $_GET['pedido_id'] ?? null;
if (!$pedido_id) {
    die("ID de pedido no proporcionado.");
}

// Obtener proveedor
$uid = $_SESSION['firebase_uid'];
$stmt = $pdo->prepare("SELECT id FROM proveedores WHERE firebase_uid = ?");
$stmt->execute([$uid]);
$proveedor = $stmt->fetch();

if (!$proveedor) {
    die("Proveedor no encontrado.");
}
$proveedor_id = $proveedor['id'];

// Obtener el pedido
$stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?");
$stmt->execute([$pedido_id]);
$pedido = $stmt->fetch();
if (!$pedido) {
    die("Pedido no encontrado.");
}

// Obtener detalles del pedido SOLO del proveedor
$detalles = $pdo->prepare("SELECT d.*, p.nombre, p.imagen FROM pedido_detalle d JOIN productos p ON d.producto_id = p.id WHERE d.pedido_id = ? AND p.proveedor_id = ?");
$detalles->execute([$pedido_id, $proveedor_id]);
$productos = $detalles->fetchAll();

if (!$productos) {
    die("Este pedido no contiene productos tuyos.");
}

// Dirección (simplificado)
$direccion = $pedido['tipo_entrega'] === 'domicilio' ? 'Entrega a domicilio' : 'Retiro en sucursal';

// Generar HTML
$html = "<html><head><meta charset='utf-8'><style>body{font-family:sans-serif;padding:20px;}table{width:100%;border-collapse:collapse;margin-top:10px;}th,td{border:1px solid #ccc;padding:8px;text-align:left;}th{background:#eee;}h1{background:#333;color:#fff;padding:10px;font-size:20px;} .right{text-align:right;}</style></head><body>";
$html .= "<h1>Resumen del Pedido #{$pedido['id']} - Proveedor</h1>";
$html .= "<p><strong>Cliente:</strong> {$pedido['email']}<br>
<strong>Fecha:</strong> {$pedido['fecha']}<br>
<strong>Forma de pago:</strong> {$pedido['forma_pago']}<br>
<strong>Entrega:</strong> {$direccion}</p>";

$html .= "<table><thead><tr><th>Producto</th><th>Variante</th><th>Cantidad</th><th>Precio</th><th>Total</th></tr></thead><tbody>";
$total_ganancia = 0;
$total_venta = 0; // Inicializar la variable
foreach ($productos as $prod) {
    $subtotal = $prod['cantidad'] * $prod['precio_unitario'];
    $total_ganancia += $prod['ganancia_proveedor'];
    $total_venta += $subtotal; // Sumar el subtotal al total de la venta
    $html .= "<tr>
        <td>" . htmlspecialchars($prod['nombre']) . "</td>
        <td>" . htmlspecialchars($prod['variante']) . "</td>
        <td class='right'>{$prod['cantidad']}</td>
        <td class='right'>$" . number_format($prod['precio_unitario'], 2) . "</td>
        <td class='right'>$" . number_format($subtotal, 2) . "</td>
    </tr>";
}
$html .= "</tbody></table>";

$html .= "
    <hr>
    <p><strong>Total de Venta:</strong> $" . number_format($total_venta, 2) . "</p>
    <p><strong>Tu Ganancia:</strong> $" . number_format($total_ganancia, 2) . "</p>
";
$html .= "<p style='text-align:center;'>Gracias por trabajar con nosotros.</p>";
$html .= "</body></html>";

$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("comprobante_proveedor_{$pedido_id}.pdf", ["Attachment" => false]);
?>
