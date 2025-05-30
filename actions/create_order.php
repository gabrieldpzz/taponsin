<?php
session_start();
require_once '../includes/db.php';
require_once '../wompi/wompi_token.php';

$userId = $_SESSION['firebase_uid'] ?? null;
$email = $_SESSION['firebase_email'] ?? 'sin-correo';

if (!$userId) {
    header("Location: /index.php");
    exit;
}

// Obtener ítems del carrito
$stmt = $pdo->prepare("SELECT c.*, p.precio FROM carrito c JOIN productos p ON c.producto_id = p.id WHERE c.usuario_id = ?");
$stmt->execute([$userId]);
$items = $stmt->fetchAll();

if (!$items) {
    echo "Tu carrito está vacío.";
    exit;
}

// Calcular total
$total = 0;
foreach ($items as $item) {
    $total += $item['precio'] * $item['cantidad'];
}

// Validar cupón
$codigo = $_POST['codigo'] ?? null;
$descuento = 0;
$cupon_aplicado = false;

if ($codigo) {
    $stmt = $pdo->prepare("SELECT * FROM cupones WHERE codigo = ? AND usados < uso_maximo AND fecha_expiracion >= CURDATE()");
    $stmt->execute([$codigo]);
    $cupon = $stmt->fetch();

    if ($cupon) {
        $descuento = $total * ($cupon['descuento_porcentaje'] / 100);
        $cupon_aplicado = true;
    }
}

$total_final = $total - $descuento;

// Identificador único
$identificador = "pedido_" . uniqid() . "_uid_" . $userId;

// Obtener entrega y comentarios
$tipo_entrega = $_POST['entrega'] ?? '';
$sucursal_id = null;
$comentarios = '';

// Dirección a domicilio
if ($tipo_entrega === 'domicilio') {
    $departamento_id = $_POST['dir_departamento'] ?? '';
    $municipio_id = $_POST['dir_municipio'] ?? '';
    $colonia = $_POST['dir_colonia'] ?? '';
    $calle = $_POST['dir_calle'] ?? '';
    $numero = $_POST['dir_numero'] ?? '';
    $referencia = $_POST['dir_referencia'] ?? '';

    $comentarios = "Depto ID: $departamento_id; Municipio ID: $municipio_id; " .
        "Colonia: $colonia; Calle: $calle; Nº: $numero; Ref: $referencia";

} elseif ($tipo_entrega === 'sucursal') {
    $sucursal_id = $_POST['sucursal_id'] ?? null;
    $comentarios = trim($_POST['comentarios'] ?? '');
}


// Guardar carrito temporal incluyendo tipo_entrega, sucursal_id y comentarios
foreach ($items as $item) {
    $insert = $pdo->prepare("INSERT INTO carrito_temporal 
        (identificador, producto_id, cantidad, precio_unitario, variante, tipo_entrega, sucursal_id, comentarios)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $insert->execute([
        $identificador,
        $item['producto_id'],
        $item['cantidad'],
        $item['precio'],
        $item['variante'] ?? '',
        $tipo_entrega,
        $sucursal_id,
        $comentarios
    ]);
}



// Cupón aplicado, actualiza uso
if ($cupon_aplicado) {
    $pdo->prepare("UPDATE cupones SET usados = usados + 1 WHERE id = ?")->execute([$cupon['id']]);
}

// Generar enlace Wompi
$token = obtenerTokenWompi();

$data = [
    "idAplicativo" => "",
    "identificadorEnlaceComercio" => $identificador,
    "monto" => floatval(number_format($total_final, 2, '.', '')),
    "nombreProducto" => "Compra en TaponShop",
    "formaPago" => [
        "permitirTarjetaCreditoDebido" => true,
        "permitirPagoConPuntoAgricola" => false,
        "permitirPagoEnCuotasAgricola" => true,
        "permitirPagoEnBitcoin" => false
    ],
    "cantidadMaximaCuotas" => 12,
    "infoProducto" => [
        "descripcionProducto" => "Compra desde carrito TaponShop",
        "urlImagenProducto" => "https://i.imgur.com/tw9sl6x.png"
    ],
    "configuracion" => [
        "urlRedirect" => "http://localhost:8080/carrito/confirmacion.php?pedido=$identificador",
        "esMontoEditable" => false,
        "esCantidadEditable" => false,
        "cantidadPorDefecto" => 1,
        "duracionInterfazIntentoMinutos" => 15,
        "urlRetorno" => "http://localhost:8080/productos/index.php",
        "emailsNotificacion" => $email,
        "urlWebhook" => "https://5bc7-200-31-174-130.ngrok-free.app/wompi/webhook.php",
        "telefonosNotificacion" => "00000000",
        "notificarTransaccionCliente" => true
    ],
    "vigencia" => [
        "fechaInicio" => "2025-05-01T23:59:59.999Z",
        "fechaFin" => "2025-12-31T23:59:59.999Z"
    ],
    "limitesDeUso" => [
        "cantidadMaximaPagosExitosos" => 1,
        "cantidadMaximaPagosFallidos" => 3
    ]
];

$ch = curl_init("https://api.wompi.sv/EnlacePago");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $token",
        "Content-Type: application/json-patch+json"
    ]
]);

$response = json_decode(curl_exec($ch), true);
curl_close($ch);

// Redirigir
if (isset($response['urlEnlace'])) {
    header("Location: " . $response['urlEnlace']);
    exit;
} else {
    echo "Error al generar el pago: ";
    print_r($response);
}
