<?php
session_start();
require_once '../includes/db.php';

$userId = $_SESSION['firebase_uid'] ?? null;
$productId = $_POST['product_id'] ?? null;

if (!$userId || !$productId) {
    header("Location: ../carrito/index.php?error=Datos+incompletos");
    exit;
}

$variant = $_POST['variant'] ?? '';

$stmt = $pdo->prepare("DELETE FROM carrito WHERE usuario_id = ? AND producto_id = ? AND variante = ?");
$stmt->execute([$userId, $productId, $variant]);


// ✅ Redirigir con mensaje de éxito
header("Location: ../carrito/index.php?mensaje=Producto+eliminado+correctamente");
exit;
