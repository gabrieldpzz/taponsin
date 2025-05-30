<?php
session_start();
if (!isset($_SESSION['firebase_uid']) || $_SESSION['rol'] !== 'admin') {
    header("Location: /index.php");
    exit;
}

require_once '../includes/db.php';
require_once '../includes/email.php';

$producto_id = $_GET['id'] ?? null;
if (!$producto_id) {
    die("ID no especificado.");
}

// Cambiar estado
$stmt = $pdo->prepare("UPDATE productos SET estado = 'rechazado' WHERE id = ?");
$stmt->execute([$producto_id]);

// Obtener datos del producto + proveedor
$stmt = $pdo->prepare("
    SELECT p.nombre AS nombre_producto, p.imagen, v.nombre AS nombre_proveedor, v.correo
    FROM productos p
    JOIN proveedores v ON p.proveedor_id = v.id
    WHERE p.id = ?
");
$stmt->execute([$producto_id]);
$datos = $stmt->fetch();

if ($datos) {
    $template = file_get_contents('../includes/plantillas/producto_rechazado.html');
    $mensaje = str_replace(
        ['{NOMBRE_PROVEEDOR}', '{NOMBRE_PRODUCTO}', '{IMAGEN}'],
        [htmlspecialchars($datos['nombre_proveedor']), htmlspecialchars($datos['nombre_producto']), '/uploads/' . $datos['imagen']],
        $template
    );

    enviarCorreo($datos['correo'], '❌ Tu producto fue rechazado', $mensaje);
}

header("Location: /admin/productos_pendientes.php");
exit;
?>
