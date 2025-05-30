<?php
require_once '../includes/db.php';
require_once '../includes/email.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    die("ID de solicitud no especificado.");
}

// Obtener datos del solicitante
$stmt = $pdo->prepare("SELECT nombre, correo, firebase_uid FROM solicitudes_proveedor WHERE id = ?");
$stmt->execute([$id]);
$solicitud = $stmt->fetch();

if (!$solicitud) {
    die("Solicitud no encontrada.");
}

// Insertar en la tabla de proveedores
$stmt = $pdo->prepare("INSERT INTO proveedores (nombre, correo, firebase_uid, fecha_registro, comision_porcentaje) VALUES (?, ?, ?, NOW(), 15)");
$stmt->execute([$solicitud['nombre'], $solicitud['correo'], $solicitud['firebase_uid']]);

// Eliminar solicitud
$pdo->prepare("DELETE FROM solicitudes_proveedor WHERE id = ?")->execute([$id]);

// Enviar correo de aprobación
$template = file_get_contents('../includes/plantillas/plantilla_aprobacion.html');
$mensaje = str_replace('{NOMBRE}', htmlspecialchars($solicitud['nombre']), $template);
enviarCorreo($solicitud['correo'], '✅ Aprobación como proveedor', $mensaje);

// Redirigir
header("Location: /admin/solicitudes.php");
exit;
