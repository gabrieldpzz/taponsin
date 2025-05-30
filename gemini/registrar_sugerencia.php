<?php
session_start();
require_once '../includes/db.php';

$uid = $_SESSION['firebase_uid'] ?? null;
$sugerencia = $_POST['sugerencia'] ?? '';

if ($sugerencia) {
    $stmt = $pdo->prepare("INSERT INTO sugerencias_productos (sugerencia, uid_cliente, fecha) VALUES (?, ?, NOW())");
    $stmt->execute([$sugerencia, $uid]);
    echo "✅ Sugerencia registrada.";
} else {
    echo "❌ Error al registrar.";
}
