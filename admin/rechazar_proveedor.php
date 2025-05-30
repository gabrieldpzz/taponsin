<?php
require_once '../includes/db.php';
require_once '../includes/email.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    die("ID de solicitud no especificado.");
}

// Obtener datos del solicitante
$stmt = $pdo->prepare("SELECT nombre, correo FROM solicitudes_proveedor WHERE id = ?");
$stmt->execute([$id]);
$solicitud = $stmt->fetch();

if (!$solicitud) {
    die("Solicitud no encontrada.");
}

// Eliminar solicitud
$pdo->prepare("DELETE FROM solicitudes_proveedor WHERE id = ?")->execute([$id]);

// Enviar correo de rechazo
$template = file_get_contents('../includes/plantillas/plantilla_rechazo.html');
$mensaje = str_replace('{NOMBRE}', htmlspecialchars($solicitud['nombre']), $template);
enviarCorreo($solicitud['correo'], '❌ Solicitud rechazada', $mensaje);

// Redirigir
header("Location: /admin/solicitudes.php");
exit;
