<?php
session_start();
require_once '../includes/db.php';

$userId = $_SESSION['firebase_uid'] ?? null;
$productId = $_POST['product_id'] ?? null;
$quantity = $_POST['quantity'] ?? 1;

// Procesar variantes múltiples si existen
$variant = '';
if (isset($_POST['variant']) && is_array($_POST['variant'])) {
    $parts = [];
    foreach ($_POST['variant'] as $k => $v) {
        $parts[] = "$k:$v";
    }
    $variant = implode(', ', $parts);
} else {
    $variant = '';
}

// Validar entrada
if (!$userId || !$productId) {
    http_response_code(400);
    header("Location: ../productos/index.php?error=Datos+incompletos");
    exit;
}

// Insertar o actualizar considerando la variante
$stmt = $pdo->prepare("
    INSERT INTO carrito (usuario_id, producto_id, cantidad, variante)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE cantidad = cantidad + VALUES(cantidad)
");
$stmt->execute([$userId, $productId, $quantity, $variant]);

header("Location: ../carrito/index.php");
exit;
