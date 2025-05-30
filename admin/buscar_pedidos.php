<?php
session_start();
if (!isset($_SESSION['firebase_uid']) || $_SESSION['rol'] !== 'admin') {
    exit('Acceso denegado');
}

require_once '../includes/db.php';

$q = $_GET['q'] ?? '';
$estado = $_GET['estado'] ?? '';

$sql = "
    SELECT p.*, s.estado AS estado_envio, s.empresa_envio, s.fecha_estimada_entrega
    FROM pedidos p
    LEFT JOIN seguimientos s ON p.id = s.pedido_id
    WHERE 1=1
";

$params = [];

if ($q !== '') {
    $sql .= " AND (p.identificador LIKE ? OR p.email LIKE ? OR s.estado LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

if ($estado !== '') {
    $sql .= " AND s.estado = ?";
    $params[] = $estado;
}

$sql .= " ORDER BY p.fecha DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll();
?>

<table class="pedidos-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Correo</th>
            <th>Identificador</th>
            <th>Monto</th>
            <th>Fecha</th>
            <th>Estado</th>
            <th>Estado Envío</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($pedidos)): ?>
            <tr>
                <td colspan="8" style="text-align: center; padding: 40px; color: #7f8caa;">
                    No se encontraron pedidos que coincidan con los criterios de búsqueda.
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($pedidos as $p): ?>
                <tr>
                    <td><strong>#<?= $p['id'] ?></strong></td>
                    <td><?= htmlspecialchars($p['email']) ?></td>
                    <td><code><?= htmlspecialchars($p['identificador']) ?></code></td>
                    <td><strong>$<?= number_format($p['monto'], 2) ?></strong></td>
                    <td><?= date('d/m/Y H:i', strtotime($p['fecha'])) ?></td>
                    <td><?= ucfirst($p['estado']) ?></td>
                    <td>
                        <span class="estado-<?= strtolower($p['estado_envio'] ?? 'sin-estado') ?>">
                            <?= ucfirst(htmlspecialchars($p['estado_envio'] ?? 'Sin estado')) ?>
                        </span>
                    </td>
                    <td>
                        <a href="detalle_pedido.php?id=<?= $p['id'] ?>" class="btn-detalles">
                            Ver detalles
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>