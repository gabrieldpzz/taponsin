<?php
session_start();
if (!isset($_SESSION['firebase_uid']) || $_SESSION['rol'] !== 'admin') {
    exit('noauth');
}

require_once '../includes/db.php';

$pedido_id = $_POST['pedido_id'] ?? null;
$estado_envio = $_POST['estado_envio'] ?? '';

$estados_validos = ['pendiente', 'enviado', 'entregado', 'cancelado'];

if ($pedido_id && in_array($estado_envio, $estados_validos)) {
    $stmt = $pdo->prepare("UPDATE seguimientos SET estado = ? WHERE pedido_id = ?");
    if ($stmt->execute([$estado_envio, $pedido_id])) {
        echo 'ok';
    } else {
        echo 'error';
    }
} else {
    echo 'invalid';
}
