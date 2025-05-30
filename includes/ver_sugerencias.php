<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/header.php';

// Verificar autenticación
$firebase_uid = $_SESSION['firebase_uid'] ?? null;
if (!$firebase_uid) {
    header('Location: /auth/login.php');
    exit;
}

// Obtener información del usuario
$stmt = $pdo->prepare("SELECT id, email, rol FROM usuarios WHERE firebase_uid = ?");
$stmt->execute([$firebase_uid]);
$usuario = $stmt->fetch();

if (!$usuario) {
    header('Location: /auth/login.php');
    exit;
}

$es_admin = $usuario['rol'] === 'admin';

// Procesar nueva sugerencia
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear_sugerencia') {
    header('Content-Type: application/json');
    
    $sugerencia = trim($_POST['sugerencia'] ?? '');
    
    if (empty($sugerencia)) {
        echo json_encode(['success' => false, 'message' => 'La sugerencia no puede estar vacía']);
        exit;
    }
    
    if (strlen($sugerencia) < 10) {
        echo json_encode(['success' => false, 'message' => 'La sugerencia debe tener al menos 10 caracteres']);
        exit;
    }
    
    if (strlen($sugerencia) > 1000) {
        echo json_encode(['success' => false, 'message' => 'La sugerencia no puede exceder 1000 caracteres']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO sugerencias_productos (uid_cliente, sugerencia, fecha) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$firebase_uid, $sugerencia]);
        
        echo json_encode([
            'success' => true, 
            'message' => '¡Sugerencia enviada exitosamente! Gracias por tu aporte.',
            'id' => $pdo->lastInsertId()
        ]);
    } catch (Exception $e) {
        error_log("Error al crear sugerencia: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al guardar la sugerencia. Inténtalo de nuevo.']);
    }
    exit;
}

// Procesar respuesta de admin (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if (!$es_admin) {
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit;
    }
    
    if ($_POST['action'] === 'responder_sugerencia') {
        $sugerencia_id = filter_var($_POST['sugerencia_id'], FILTER_VALIDATE_INT);
        $respuesta = trim($_POST['respuesta']);
        
        if (!$sugerencia_id || empty($respuesta)) {
            echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("
                UPDATE sugerencias_productos 
                SET respuesta = ?, fecha_respuesta = NOW(), admin_respuesta = ? 
                WHERE id = ?
            ");
            $stmt->execute([$respuesta, $usuario['email'], $sugerencia_id]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Respuesta guardada exitosamente',
                'fecha_respuesta' => date('d/m/Y H:i'),
                'admin_email' => $usuario['email']
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error al guardar la respuesta']);
        }
        exit;
    }
    
    if ($_POST['action'] === 'eliminar_sugerencia') {
        $sugerencia_id = filter_var($_POST['sugerencia_id'], FILTER_VALIDATE_INT);
        
        if (!$sugerencia_id) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("DELETE FROM sugerencias_productos WHERE id = ?");
            $stmt->execute([$sugerencia_id]);
            
            echo json_encode(['success' => true, 'message' => 'Sugerencia eliminada']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error al eliminar']);
        }
        exit;
    }
}

// Obtener filtros
$filtro_estado = $_GET['estado'] ?? 'todas';
$filtro_fecha = $_GET['fecha'] ?? '';
$buscar = $_GET['buscar'] ?? '';
$orden = $_GET['orden'] ?? 'desc';

// Construir consulta
if ($es_admin) {
    $sql = "SELECT s.*, u.email as usuario_email, u.id as usuario_id
            FROM sugerencias_productos s 
            LEFT JOIN usuarios u ON s.uid_cliente = u.firebase_uid 
            WHERE 1=1";
} else {
    $sql = "SELECT s.*, u.email as usuario_email, u.id as usuario_id
            FROM sugerencias_productos s 
            LEFT JOIN usuarios u ON s.uid_cliente = u.firebase_uid 
            WHERE s.uid_cliente = ?";
}

$params = $es_admin ? [] : [$firebase_uid];

// Aplicar filtros
if ($filtro_estado === 'respondidas') {
    $sql .= " AND s.respuesta IS NOT NULL";
} elseif ($filtro_estado === 'pendientes') {
    $sql .= " AND s.respuesta IS NULL";
}

if ($filtro_fecha) {
    $sql .= " AND DATE(s.fecha) = ?";
    $params[] = $filtro_fecha;
}

if ($buscar) {
    $sql .= " AND (s.sugerencia LIKE ? OR s.respuesta LIKE ? OR u.email LIKE ?)";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

$sql .= " ORDER BY s.fecha " . ($orden === 'asc' ? 'ASC' : 'DESC');

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sugerencias = $stmt->fetchAll();

// Estadísticas
$stats_sql = $es_admin ? 
    "SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN respuesta IS NOT NULL THEN 1 END) as respondidas,
        COUNT(CASE WHEN respuesta IS NULL THEN 1 END) as pendientes,
        COUNT(CASE WHEN DATE(fecha) = CURDATE() THEN 1 END) as hoy
     FROM sugerencias_productos" :
    "SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN respuesta IS NOT NULL THEN 1 END) as respondidas,
        COUNT(CASE WHEN respuesta IS NULL THEN 1 END) as pendientes,
        COUNT(CASE WHEN DATE(fecha) = CURDATE() THEN 1 END) as hoy
     FROM sugerencias_productos 
     WHERE uid_cliente = ?";

$stmt = $pdo->prepare($stats_sql);
$stmt->execute($es_admin ? [] : [$firebase_uid]);
$estadisticas = $stmt->fetch();

function tiempoTranscurrido($fecha) {
    $ahora = new DateTime();
    $fecha_obj = new DateTime($fecha);
    $diff = $ahora->diff($fecha_obj);
    
    if ($diff->days > 0) {
        return $diff->days . ' día' . ($diff->days > 1 ? 's' : '');
    } elseif ($diff->h > 0) {
        return $diff->h . ' hora' . ($diff->h > 1 ? 's' : '');
    } elseif ($diff->i > 0) {
        return $diff->i . ' minuto' . ($diff->i > 1 ? 's' : '');
    } else {
        return 'Hace un momento';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Sugerencias - TaponShop</title>
    <link rel="stylesheet" href="/assets/css/asistente.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .page-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #EAEFEF 0%, #B8CFCE 100%);
        }

        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            background: linear-gradient(135deg, #333446 0%, #7F8CAA 100%);
            color: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header-info h1 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0 0 8px 0;
        }

        .header-info p {
            opacity: 0.9;
            margin: 0;
            font-size: 1.1rem;
        }

        .admin-badge {
            background: linear-gradient(135deg, #A8E6CF 0%, #7dd3a0 100%);
            color: #2d5a3d;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-left: 8px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
        }

        .stat-card.total { border-left: 4px solid #6C63FF; }
        .stat-card.respondidas { border-left: 4px solid #A8E6CF; }
        .stat-card.pendientes { border-left: 4px solid #FF8C94; }
        .stat-card.hoy { border-left: 4px solid #7F8CAA; }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #333446;
            margin: 0 0 8px 0;
        }

        .stat-label {
            color: #7F8CAA;
            font-weight: 500;
        }

        .filters-section {
            background: white;
            padding: 24px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .filter-input {
            padding: 12px;
            border: 2px solid #B8CFCE;
            border-radius: 8px;
            font-size: 1rem;
            color: #333446;
            transition: border-color 0.3s ease;
        }

        .filter-input:focus {
            outline: none;
            border-color: #6C63FF;
            box-shadow: 0 0 0 3px rgba(108, 99, 255, 0.1);
        }

        .sugerencia-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            position: relative;
        }

        .sugerencia-card:hover {
            border-color: #6C63FF;
            box-shadow: 0 8px 32px rgba(108, 99, 255, 0.1);
            transform: translateY(-2px);
        }

        .sugerencia-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .sugerencia-meta {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .sugerencia-fecha {
            color: #7F8CAA;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .sugerencia-usuario {
            background: #6C63FF;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .estado-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .estado-pendiente {
            background: #FFF3CD;
            color: #856404;
        }

        .estado-respondida {
            background: #D4EDDA;
            color: #155724;
        }

        .sugerencia-content {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid #6C63FF;
            margin-bottom: 16px;
            line-height: 1.6;
            color: #333446;
        }

        .respuesta-admin {
            background: linear-gradient(135deg, #A8E6CF 0%, #7dd3a0 100%);
            padding: 20px;
            border-radius: 12px;
            margin-top: 16px;
            border-left: 4px solid #2d5a3d;
        }

        .respuesta-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 8px;
        }

        .respuesta-admin-info {
            color: #2d5a3d;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .respuesta-fecha {
            color: #2d5a3d;
            font-size: 0.8rem;
            opacity: 0.8;
        }

        .respuesta-content {
            color: #2d5a3d;
            line-height: 1.6;
            font-weight: 500;
        }

        .admin-actions {
            display: flex;
            gap: 8px;
            margin-top: 16px;
            flex-wrap: wrap;
        }

        .btn-admin {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-responder {
            background: #6C63FF;
            color: white;
        }

        .btn-responder:hover {
            background: #5a52e6;
        }

        .btn-eliminar {
            background: #FF8C94;
            color: white;
        }

        .btn-eliminar:hover {
            background: #ff6b7a;
        }

        .btn-editar {
            background: #7F8CAA;
            color: white;
        }

        .btn-editar:hover {
            background: #6b7694;
        }

        /* Floating Action Button */
        .fab {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #A8E6CF 0%, #7dd3a0 100%);
            border: none;
            border-radius: 50%;
            color: #2d5a3d;
            font-size: 1.8rem;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 8px 25px rgba(168, 230, 207, 0.4);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .fab:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 12px 35px rgba(168, 230, 207, 0.6);
            background: linear-gradient(135deg, #7dd3a0 0%, #6bc97a 100%);
        }

        .fab:active {
            transform: translateY(-1px) scale(1.02);
        }

        .fab-tooltip {
            position: absolute;
            right: 70px;
            top: 50%;
            transform: translateY(-50%);
            background: #333446;
            color: white;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            pointer-events: none;
        }

        .fab-tooltip::after {
            content: '';
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            border: 6px solid transparent;
            border-left-color: #333446;
        }

        .fab:hover .fab-tooltip {
            opacity: 1;
            visibility: visible;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 16px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header {
            background: linear-gradient(135deg, #6C63FF 0%, #5a52e6 100%);
            color: white;
            padding: 20px 24px;
            border-radius: 16px 16px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header.crear {
            background: linear-gradient(135deg, #A8E6CF 0%, #7dd3a0 100%);
            color: #2d5a3d;
        }

        .modal-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin: 0;
        }

        .close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background-color 0.3s ease;
        }

        .modal-header.crear .close {
            color: #2d5a3d;
        }

        .close:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .modal-header.crear .close:hover {
            background-color: rgba(45, 90, 61, 0.2);
        }

        .modal-body {
            padding: 24px;
        }

        .sugerencia-preview {
            background: #f8f9fa;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #6C63FF;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333446;
        }

        .form-textarea {
            width: 100%;
            min-height: 120px;
            padding: 12px;
            border: 2px solid #B8CFCE;
            border-radius: 8px;
            font-size: 1rem;
            color: #333446;
            resize: vertical;
            font-family: inherit;
            line-height: 1.5;
        }

        .form-textarea:focus {
            outline: none;
            border-color: #6C63FF;
            box-shadow: 0 0 0 3px rgba(108, 99, 255, 0.1);
        }

        .form-textarea.crear:focus {
            border-color: #A8E6CF;
            box-shadow: 0 0 0 3px rgba(168, 230, 207, 0.2);
        }

        .char-counter {
            text-align: right;
            font-size: 0.8rem;
            color: #7F8CAA;
            margin-top: 4px;
        }

        .char-counter.warning {
            color: #FF8C94;
        }

        .modal-footer {
            padding: 20px 24px;
            border-top: 1px solid #e0e0e0;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .btn-modal {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-cancel {
            background: #7F8CAA;
            color: white;
        }

        .btn-cancel:hover {
            background: #6b7694;
        }

        .btn-submit {
            background: #6C63FF;
            color: white;
        }

        .btn-submit:hover {
            background: #5a52e6;
        }

        .btn-submit.crear {
            background: #A8E6CF;
            color: #2d5a3d;
        }

        .btn-submit.crear:hover {
            background: #7dd3a0;
        }

        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 1001;
            animation: slideInRight 0.3s ease;
            max-width: 350px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        .notification.success {
            background: linear-gradient(135deg, #A8E6CF 0%, #7dd3a0 100%);
            color: #2d5a3d;
        }

        .notification.error {
            background: linear-gradient(135deg, #FF8C94 0%, #ff6b7a 100%);
            color: white;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .form-tips {
            background: #f0f8ff;
            border: 1px solid #B8CFCE;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
        }

        .form-tips h4 {
            color: #333446;
            margin: 0 0 8px 0;
            font-size: 1rem;
        }

        .form-tips ul {
            margin: 0;
            padding-left: 20px;
            color: #7F8CAA;
        }

        .form-tips li {
            margin: 4px 0;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 10px;
            }

            .modal-content {
                margin: 10% auto;
                width: 95%;
            }

            .admin-actions {
                justify-content: center;
            }

            .sugerencia-header {
                flex-direction: column;
                align-items: stretch;
            }

            .fab {
                bottom: 20px;
                right: 20px;
                width: 56px;
                height: 56px;
                font-size: 1.6rem;
            }

            .fab-tooltip {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .modal-body {
                padding: 16px;
            }

            .modal-footer {
                padding: 16px;
                flex-direction: column;
            }

            .btn-modal {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <div class="main-content">
            <!-- Header -->
            <header class="page-header">
                <div class="header-content">
                    <div class="header-info">
                        <h1>💡 Gestión de Sugerencias</h1>
                        <p>
                            <?php if ($es_admin): ?>
                                Panel de administración - Gestionar sugerencias de clientes
                                <span class="admin-badge">ADMIN</span>
                            <?php else: ?>
                                Tus sugerencias y respuestas del equipo
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </header>

            <!-- Estadísticas -->
            <section class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-number"><?= $estadisticas['total'] ?></div>
                    <div class="stat-label">Total</div>
                </div>
                <div class="stat-card respondidas">
                    <div class="stat-number"><?= $estadisticas['respondidas'] ?></div>
                    <div class="stat-label">Respondidas</div>
                </div>
                <div class="stat-card pendientes">
                    <div class="stat-number"><?= $estadisticas['pendientes'] ?></div>
                    <div class="stat-label">Pendientes</div>
                </div>
                <div class="stat-card hoy">
                    <div class="stat-number"><?= $estadisticas['hoy'] ?></div>
                    <div class="stat-label">Hoy</div>
                </div>
            </section>

            <!-- Filtros -->
            <section class="filters-section">
                <form method="GET" action="">
                    <div class="filters-grid">
                        <input type="text" name="buscar" class="filter-input" 
                               placeholder="Buscar en sugerencias..." 
                               value="<?= htmlspecialchars($buscar) ?>">
                        
                        <select name="estado" class="filter-input">
                            <option value="todas" <?= $filtro_estado === 'todas' ? 'selected' : '' ?>>Todas</option>
                            <option value="pendientes" <?= $filtro_estado === 'pendientes' ? 'selected' : '' ?>>Pendientes</option>
                            <option value="respondidas" <?= $filtro_estado === 'respondidas' ? 'selected' : '' ?>>Respondidas</option>
                        </select>
                        
                        <input type="date" name="fecha" class="filter-input" 
                               value="<?= htmlspecialchars($filtro_fecha) ?>">
                        
                        <select name="orden" class="filter-input">
                            <option value="desc" <?= $orden === 'desc' ? 'selected' : '' ?>>Más recientes</option>
                            <option value="asc" <?= $orden === 'asc' ? 'selected' : '' ?>>Más antiguas</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-admin btn-responder">🔍 Buscar</button>
                </form>
            </section>

            <!-- Lista de Sugerencias -->
            <section class="sugerencias-list">
                <?php if (empty($sugerencias)): ?>
                    <div style="text-align: center; padding: 60px 20px; color: #7F8CAA;">
                        <div style="font-size: 4rem; margin-bottom: 16px;">💭</div>
                        <h3 style="color: #333446; margin: 0 0 8px 0;">No se encontraron sugerencias</h3>
                        <p>
                            <?php if (!$es_admin && $estadisticas['total'] == 0): ?>
                                ¡Sé el primero en enviar una sugerencia! Usa el botón verde para crear una nueva.
                            <?php else: ?>
                                No hay sugerencias que coincidan con los filtros aplicados.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($sugerencias as $sugerencia): ?>
                    <div class="sugerencia-card" data-id="<?= $sugerencia['id'] ?>">
                        <div class="sugerencia-header">
                            <div class="sugerencia-meta">
                                <div class="sugerencia-fecha">
                                    📅 <?= date('d/m/Y H:i', strtotime($sugerencia['fecha'])) ?>
                                    <span style="margin-left: 8px; color: #6C63FF;">
                                        (Hace <?= tiempoTranscurrido($sugerencia['fecha']) ?>)
                                    </span>
                                </div>
                                <?php if ($es_admin && $sugerencia['usuario_email']): ?>
                                    <div class="sugerencia-usuario">
                                        👤 <?= htmlspecialchars($sugerencia['usuario_email']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="estado-badge <?= $sugerencia['respuesta'] ? 'estado-respondida' : 'estado-pendiente' ?>">
                                <?= $sugerencia['respuesta'] ? '✅ Respondida' : '⏳ Pendiente' ?>
                            </div>
                        </div>
                        
                        <div class="sugerencia-content">
                            <strong>Sugerencia:</strong><br>
                            <?= nl2br(htmlspecialchars($sugerencia['sugerencia'])) ?>
                        </div>
                        
                        <?php if ($sugerencia['respuesta']): ?>
                            <div class="respuesta-admin">
                                <div class="respuesta-header">
                                    <div class="respuesta-admin-info">
                                        🛡️ Respuesta del equipo
                                        <?php if ($sugerencia['admin_respuesta']): ?>
                                            - <?= htmlspecialchars($sugerencia['admin_respuesta']) ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($sugerencia['fecha_respuesta']): ?>
                                        <div class="respuesta-fecha">
                                            <?= date('d/m/Y H:i', strtotime($sugerencia['fecha_respuesta'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="respuesta-content">
                                    <?= nl2br(htmlspecialchars($sugerencia['respuesta'])) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($es_admin): ?>
                            <div class="admin-actions">
                                <?php if (!$sugerencia['respuesta']): ?>
                                    <button onclick="abrirModalRespuesta(<?= $sugerencia['id'] ?>, '<?= addslashes($sugerencia['sugerencia']) ?>')" 
                                            class="btn-admin btn-responder">
                                        💬 Responder
                                    </button>
                                <?php else: ?>
                                    <button onclick="abrirModalRespuesta(<?= $sugerencia['id'] ?>, '<?= addslashes($sugerencia['sugerencia']) ?>', '<?= addslashes($sugerencia['respuesta']) ?>')" 
                                            class="btn-admin btn-editar">
                                        ✏️ Editar Respuesta
                                    </button>
                                <?php endif; ?>
                                <button onclick="eliminarSugerencia(<?= $sugerencia['id'] ?>)" 
                                        class="btn-admin btn-eliminar">
                                    🗑️ Eliminar
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <!-- Floating Action Button -->
    <button class="fab" onclick="abrirModalCrear()" title="Nueva Sugerencia">
        <span class="fab-tooltip">Nueva Sugerencia</span>
        +
    </button>

    <!-- Modal para crear sugerencia -->
    <div id="modalCrear" class="modal">
        <div class="modal-content">
            <div class="modal-header crear">
                <h2 class="modal-title">💡 Nueva Sugerencia</h2>
                <button class="close" onclick="cerrarModalCrear()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-tips">
                    <h4>💡 Tips para una buena sugerencia:</h4>
                    <ul>
                        <li>Sé específico y claro en tu propuesta</li>
                        <li>Explica cómo mejoraría tu experiencia</li>
                        <li>Incluye detalles relevantes del producto o servicio</li>
                        <li>Mantén un tono constructivo y respetuoso</li>
                    </ul>
                </div>
                <form id="formCrear">
                    <div class="form-group">
                        <label class="form-label">Tu sugerencia:</label>
                        <textarea id="sugerenciaTexto" class="form-textarea crear" 
                                  placeholder="Comparte tu idea para mejorar nuestros productos o servicios. Tu opinión es muy valiosa para nosotros..."
                                  required maxlength="1000"></textarea>
                        <div class="char-counter" id="charCounter">0 / 1000 caracteres</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modal btn-cancel" onclick="cerrarModalCrear()">
                    Cancelar
                </button>
                <button type="button" class="btn-modal btn-submit crear" onclick="enviarSugerencia()">
                    💾 Enviar Sugerencia
                </button>
            </div>
        </div>
    </div>

    <!-- Modal para responder -->
    <div id="modalRespuesta" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">💬 Responder Sugerencia</h2>
                <button class="close" onclick="cerrarModalRespuesta()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="sugerencia-preview">
                    <strong>Sugerencia del cliente:</strong><br>
                    <span id="sugerenciaTextoRespuesta"></span>
                </div>
                <form id="formRespuesta">
                    <div class="form-group">
                        <label class="form-label">Tu respuesta:</label>
                        <textarea id="respuestaTexto" class="form-textarea" 
                                  placeholder="Escribe una respuesta útil y profesional para el cliente..."
                                  required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modal btn-cancel" onclick="cerrarModalRespuesta()">
                    Cancelar
                </button>
                <button type="button" class="btn-modal btn-submit" onclick="enviarRespuesta()">
                    💾 Guardar Respuesta
                </button>
            </div>
        </div>
    </div>

    <script>
        let sugerenciaActual = null;
        let modalCrearAbierto = false;

        // Funciones para modal de crear sugerencia
        function abrirModalCrear() {
            document.getElementById('modalCrear').style.display = 'block';
            document.getElementById('sugerenciaTexto').focus();
            modalCrearAbierto = true;
            actualizarContador();
        }

        function cerrarModalCrear() {
            const textarea = document.getElementById('sugerenciaTexto');
            if (textarea.value.trim() && !confirm('¿Estás seguro de que quieres cerrar? Se perderá el texto escrito.')) {
                return;
            }
            document.getElementById('modalCrear').style.display = 'none';
            document.getElementById('formCrear').reset();
            modalCrearAbierto = false;
            actualizarContador();
        }

        function actualizarContador() {
            const textarea = document.getElementById('sugerenciaTexto');
            const counter = document.getElementById('charCounter');
            const length = textarea.value.length;
            const maxLength = 1000;
            
            counter.textContent = `${length} / ${maxLength} caracteres`;
            
            if (length > maxLength * 0.9) {
                counter.classList.add('warning');
            } else {
                counter.classList.remove('warning');
            }
        }

        function enviarSugerencia() {
            const sugerencia = document.getElementById('sugerenciaTexto').value.trim();
            
            if (!sugerencia) {
                mostrarNotificacion('Por favor escribe una sugerencia', 'error');
                return;
            }

            if (sugerencia.length < 10) {
                mostrarNotificacion('La sugerencia debe tener al menos 10 caracteres', 'error');
                return;
            }

            if (sugerencia.length > 1000) {
                mostrarNotificacion('La sugerencia no puede exceder 1000 caracteres', 'error');
                return;
            }

            const submitBtn = document.querySelector('.btn-submit.crear');
            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ Enviando...';

            const formData = new FormData();
            formData.append('action', 'crear_sugerencia');
            formData.append('sugerencia', sugerencia);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(async response => {
                let data;
                try {
                    data = await response.json();
                } catch (e) {
                    mostrarNotificacion('Respuesta inesperada del servidor, pero la sugerencia pudo haberse enviado. Actualiza la página para verificar.', 'error');
                    cerrarModalCrear();
                    setTimeout(() => location.reload(), 2000);
                    return;
                }
                if (data.success) {
                    mostrarNotificacion(data.message, 'success');
                    cerrarModalCrear();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    mostrarNotificacion(data.message, 'error');
                }
            })
            .catch(error => {
                mostrarNotificacion('No se pudo conectar con el servidor, pero es posible que la sugerencia se haya enviado. Actualiza la página para verificar.', 'error');
                cerrarModalCrear();
                setTimeout(() => location.reload(), 2000);
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = '💾 Enviar Sugerencia';
            });
        }

        // Funciones para modal de respuesta (admin)
        function abrirModalRespuesta(id, sugerencia, respuestaExistente = '') {
            sugerenciaActual = id;
            document.getElementById('sugerenciaTextoRespuesta').textContent = sugerencia;
            document.getElementById('respuestaTexto').value = respuestaExistente;
            document.getElementById('modalRespuesta').style.display = 'block';
            document.getElementById('respuestaTexto').focus();
        }

        function cerrarModalRespuesta() {
            document.getElementById('modalRespuesta').style.display = 'none';
            sugerenciaActual = null;
        }

        function enviarRespuesta() {
            if (!sugerenciaActual) return;

            const respuesta = document.getElementById('respuestaTexto').value.trim();
            if (!respuesta) {
                mostrarNotificacion('Por favor escribe una respuesta', 'error');
                return;
            }

            const submitBtn = document.querySelector('#modalRespuesta .btn-submit');
            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ Guardando...';

            const formData = new FormData();
            formData.append('action', 'responder_sugerencia');
            formData.append('sugerencia_id', sugerenciaActual);
            formData.append('respuesta', respuesta);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(async response => {
                let data;
                try {
                    data = await response.json();
                } catch (e) {
                    mostrarNotificacion('Respuesta inesperada del servidor, pero la acción pudo haberse realizado. Actualiza la página para verificar.', 'error');
                    cerrarModalRespuesta();
                    location.reload();
                    return;
                }
                if (data.success) {
                    mostrarNotificacion(data.message, 'success');
                    cerrarModalRespuesta();
                    location.reload();
                } else {
                    mostrarNotificacion(data.message, 'error');
                }
            })
            .catch(error => {
                mostrarNotificacion('No se pudo conectar con el servidor, pero es posible que la acción se haya realizado. Actualiza la página para verificar.', 'error');
                cerrarModalRespuesta();
                location.reload();
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = '💾 Guardar Respuesta';
            });
        }

        function eliminarSugerencia(id) {
            if (!confirm('¿Estás seguro de que quieres eliminar esta sugerencia? Esta acción no se puede deshacer.')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'eliminar_sugerencia');
            formData.append('sugerencia_id', id);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(async response => {
                let data;
                try {
                    data = await response.json();
                } catch (e) {
                    document.querySelector(`[data-id="${id}"]`).remove();
                    return;
                }
                if (data.success) {
                    mostrarNotificacion(data.message, 'success');
                    document.querySelector(`[data-id="${id}"]`).remove();
                } else {
                    mostrarNotificacion(data.message, 'error');
                }
            })
            .catch(error => {
                mostrarNotificacion('No se pudo conectar con el servidor, pero es posible que la acción se haya realizado. Actualiza la página para verificar.', 'error');
                document.querySelector(`[data-id="${id}"]`).remove();
            });
        }

        function mostrarNotificacion(mensaje, tipo) {
            // Remover notificaciones existentes
            document.querySelectorAll('.notification').forEach(n => n.remove());
            
            const notificacion = document.createElement('div');
            notificacion.className = `notification ${tipo}`;
            notificacion.textContent = mensaje;
            
            document.body.appendChild(notificacion);
            
            setTimeout(() => {
                notificacion.remove();
            }, 5000);
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Contador de caracteres
            const textarea = document.getElementById('sugerenciaTexto');
            if (textarea) {
                textarea.addEventListener('input', actualizarContador);
            }

            // Auto-submit filtros
            document.querySelectorAll('select[name="estado"], select[name="orden"]').forEach(select => {
                select.addEventListener('change', function() {
                    this.form.submit();
                });
            });

            // Highlight search terms
            const searchTerm = '<?= addslashes($buscar) ?>';
            if (searchTerm) {
                document.querySelectorAll('.sugerencia-content, .respuesta-content').forEach(element => {
                    const text = element.innerHTML;
                    const regex = new RegExp(`(${searchTerm})`, 'gi');
                    if (regex.test(text)) {
                        element.innerHTML = text.replace(regex, '<mark style="background: #A8E6CF; padding: 2px 4px; border-radius: 3px;">$1</mark>');
                    }
                });
            }
        });

        // Cerrar modales con ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (modalCrearAbierto) {
                    cerrarModalCrear();
                } else {
                    cerrarModalRespuesta();
                }
            }
        });

        // Cerrar modales al hacer clic fuera
        window.onclick = function(event) {
            const modalCrear = document.getElementById('modalCrear');
            const modalRespuesta = document.getElementById('modalRespuesta');
            
            if (event.target === modalCrear) {
                cerrarModalCrear();
            } else if (event.target === modalRespuesta) {
                cerrarModalRespuesta();
            }
        }

        // Envío con Enter + Ctrl
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'Enter') {
                if (modalCrearAbierto) {
                    enviarSugerencia();
                } else if (sugerenciaActual) {
                    enviarRespuesta();
                }
            }
        });
    </script>
</body>
</html>

<?php include '../includes/footer.php'; ?>