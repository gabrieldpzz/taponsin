<?php
session_start();
require_once '../includes/db.php';


// ⚠️ Solo permitir acceso a admin
if (!isset($_SESSION['firebase_uid']) || $_SESSION['rol'] !== 'admin') {
    header("Location: /index.php");
    exit;
}

// 🧾 Traer solicitudes
$stmt = $pdo->query("
    SELECT sp.*, u.email 
    FROM solicitudes_proveedor sp
    LEFT JOIN usuarios u ON sp.firebase_uid = u.firebase_uid
    ORDER BY sp.id DESC
");
$solicitudes = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitudes de Proveedores</title>
    <link rel="stylesheet" href="/assets/css/index.css?v=2">
    <link rel="stylesheet" href="/assets/css/solicitudes.css?v=<?= time() ?>">
</head>
<body>
    <div class="admin-container">
        <div class="page-header">
            <h2>📋 Solicitudes de Proveedores</h2>
            <div class="header-actions">
                <a href="index.php" class="btn-secondary">
                    <span class="icon">🏠</span> Dashboard
                </a>
            </div>
        </div>

        <?php if (count($solicitudes) === 0): ?>
            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <h3>No hay solicitudes</h3>
                <p>Actualmente no hay solicitudes de proveedores registradas.</p>
            </div>
        <?php else: ?>
            <div class="filter-bar">
                <div class="search-container">
                    <input type="text" id="searchInput" placeholder="Buscar proveedor..." class="search-input">
                    <span class="search-icon">🔍</span>
                </div>
                <div class="filter-options">
                    <select id="statusFilter" class="filter-select">
                        <option value="todos">Todos los estados</option>
                        <option value="espera">En espera</option>
                        <option value="aceptado">Aceptados</option>
                        <option value="rechazado">Rechazados</option>
                    </select>
                </div>
            </div>

            <div class="solicitudes-grid">
                <?php foreach ($solicitudes as $sol): ?>
                    <div class="solicitud-card" data-estado="<?= $sol['estado'] ?>">
                        <div class="card-header">
                            <div class="provider-info">
                                <h3><?= htmlspecialchars($sol['nombre_proveedor']) ?></h3>
                                <span class="provider-id">ID: <?= $sol['id'] ?></span>
                            </div>
                            <?php if ($sol['estado'] === 'espera'): ?>
                                <span class="status-badge status-waiting">🕓 En espera</span>
                            <?php elseif ($sol['estado'] === 'aceptado'): ?>
                                <span class="status-badge status-accepted">✅ Aceptado</span>
                            <?php else: ?>
                                <span class="status-badge status-rejected">❌ Rechazado</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="card-content">
                            <div class="info-row">
                                <div class="info-label">📧 Correo:</div>
                                <div class="info-value"><?= htmlspecialchars($sol['email']) ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">📱 Teléfono:</div>
                                <div class="info-value"><?= htmlspecialchars($sol['telefono']) ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">📅 Fecha:</div>
                                <div class="info-value"><?= date('d/m/Y H:i', strtotime($sol['fecha_solicitud'])) ?></div>
                            </div>
                        </div>
                        
                        <div class="card-footer">
                            <a href="ver_solicitud.php?id=<?= $sol['id'] ?>" class="btn-primary">
                                <span class="icon">👁️</span> Ver detalles
                            </a>
                            <?php if ($sol['estado'] === 'espera'): ?>
                            
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Filtrado de solicitudes
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const statusFilter = document.getElementById('statusFilter');
            const cards = document.querySelectorAll('.solicitud-card');
            
            function filterCards() {
                const searchTerm = searchInput.value.toLowerCase();
                const statusValue = statusFilter.value;
                
                cards.forEach(card => {
                    const providerName = card.querySelector('h3').textContent.toLowerCase();
                    const providerEmail = card.querySelector('.info-value').textContent.toLowerCase();
                    const cardStatus = card.dataset.estado;
                    
                    const matchesSearch = providerName.includes(searchTerm) || providerEmail.includes(searchTerm);
                    const matchesStatus = statusValue === 'todos' || cardStatus === statusValue;
                    
                    if (matchesSearch && matchesStatus) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                });
            }
            
            searchInput.addEventListener('input', filterCards);
            statusFilter.addEventListener('change', filterCards);
        });
    </script>
</body>
</html>