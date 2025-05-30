<?php
require_once '../includes/db.php';

$code = trim($_POST['codigo']);

$stmt = $pdo->prepare("SELECT * FROM cupones WHERE codigo = ? AND fecha_expiracion >= CURDATE() AND usados < uso_maximo");
$stmt->execute([$code]);
$cupon = $stmt->fetch();

if ($cupon) {
    echo json_encode([
        "valido" => true,
        "descuento" => $cupon['descuento_porcentaje']
    ]);
} else {
    echo json_encode([
        "valido" => false
    ]);
}
?>
