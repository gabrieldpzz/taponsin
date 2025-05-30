<?php
require_once 'email.php';

function notificarProveedoresDeVenta(PDO $pdo, int $pedido_id) {
    // 1. Obtener todos los productos del pedido con sus datos + proveedor
    $stmt = $pdo->prepare("
        SELECT 
            pd.*, 
            pr.nombre AS nombre_producto, 
            pr.imagen, 
            pr.proveedor_id,
            p.nombre AS nombre_proveedor, 
            p.correo AS correo_proveedor, 
            p.comision_porcentaje
        FROM pedido_detalle pd
        JOIN productos pr ON pd.producto_id = pr.id
        JOIN proveedores p ON pr.proveedor_id = p.id
        WHERE pd.pedido_id = ?
    ");
    $stmt->execute([$pedido_id]);
    $productos = $stmt->fetchAll();

    // 2. Agrupar por proveedor
    $proveedores = [];

    foreach ($productos as $item) {
        $id_proveedor = $item['proveedor_id'];

        if (!isset($proveedores[$id_proveedor])) {
            $proveedores[$id_proveedor] = [
                'nombre' => $item['nombre_proveedor'],
                'correo' => $item['correo_proveedor'],
                'productos' => [],
            ];
        }

        $proveedores[$id_proveedor]['productos'][] = $item;
    }

    // 3. Enviar un correo por proveedor
    foreach ($proveedores as $proveedor) {
        $nombre = htmlspecialchars($proveedor['nombre']);
        $correo = $proveedor['correo'];
        $productosHTML = '';

        foreach ($proveedor['productos'] as $prod) {
            $nombreProd = htmlspecialchars($prod['nombre_producto']);
            $img = '/uploads/' . $prod['imagen']; // ajusta ruta si es necesario
            $cantidad = (int)$prod['cantidad'];
            $precio = number_format($prod['precio_unitario'], 2);
            $comision = number_format($prod['precio_unitario'] - $prod['ganancia_proveedor'], 2);
            $ganancia = number_format($prod['ganancia_proveedor'], 2);
            $variante = htmlspecialchars($prod['variante']);

            $productosHTML .= "
                <tr>
                    <td><img src='{$img}' width='80'></td>
                    <td>{$nombreProd}<br><small>Variante: {$variante}</small></td>
                    <td>{$cantidad}</td>
                    <td>\${$precio}</td>
                    <td>\${$comision}</td>
                    <td>\${$ganancia}</td>
                </tr>
            ";
        }

        // 4. Construir mensaje
        $mensaje = "
            <h2>🛒 Has realizado una nueva venta</h2>
            <p>Hola <strong>{$nombre}</strong>, uno o más de tus productos han sido vendidos. Aquí están los detalles:</p>
            <table border='1' cellpadding='10' cellspacing='0' style='border-collapse: collapse; width: 100%; text-align: center;'>
                <tr style='background: #f0f0f0;'>
                    <th>Imagen</th>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Precio</th>
                    <th>Comisión</th>
                    <th>Ganancia</th>
                </tr>
                {$productosHTML}
            </table>
            <p style='margin-top:20px;'>Gracias por formar parte de TaponShop.</p>
        ";

        // 5. Enviar
        enviarCorreo($correo, "🛍️ ¡Vendiste productos en TaponShop!", $mensaje);
    }
}
