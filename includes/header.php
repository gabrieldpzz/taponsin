<?php
// Asegurar que la sesión esté iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Obtener información del usuario si está autenticado
$usuario_info = null;
if (isset($_SESSION['firebase_uid'])) {
    try {
        // Buscar el archivo db.php en diferentes ubicaciones posibles
        $db_paths = [
            'includes/db.php',
            '../includes/db.php',
            '../../includes/db.php',
            dirname(__FILE__) . '/db.php',
            dirname(__FILE__) . '/../db.php'
        ];
        
        $db_found = false;
        foreach ($db_paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                $db_found = true;
                break;
            }
        }
        
        if ($db_found && isset($pdo)) {
            $stmt = $pdo->prepare("SELECT email, rol FROM usuarios WHERE firebase_uid = ?");
            $stmt->execute([$_SESSION['firebase_uid']]);
            $usuario_info = $stmt->fetch();
        }
    } catch (Exception $e) {
        // En caso de error, usar información de sesión como fallback
    }
    
    // Fallback a datos de sesión si no se pudo conectar a la BD
    if (!$usuario_info) {
        $usuario_info = [
            'email' => $_SESSION['email'] ?? 'Usuario',
            'rol' => $_SESSION['rol'] ?? 'user'
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaponShop</title>
    <!-- Favicon para navegadores modernos -->
<link rel="icon" type="image/png" sizes="32x32" href="/assets/img/logo.png">
<link rel="icon" type="image/png" sizes="192x192" href="/assets/img/logo.png">
<link rel="apple-touch-icon" href="/assets/img/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/header.css?v=2">
</head>
<body>
    <header class="unified-header">
        <div class="header-full-width">
            <!-- Logo solo icono -->
            <a href="/productos/index.php" class="header-brand">
                <img src="/assets/img/logo.png" alt="Logo Perolito" class="brand-icon" style="height: 75px;">
            </a>
            
            <!-- Navegación que usa TODO el ancho disponible -->
            <div class="header-navigation-full">
                <button onclick="toggleSidebar()" class="btn-departments">
                    <span class="departments-icon">☰</span>
                    <span class="departments-text">Departamentos</span>
                </button>
                
                <a href="/productos/index.php" class="nav-link">
                    <span class="nav-icon">🏠</span>
                    <span class="nav-text">Inicio</span>
                </a>
                <a href="/carrito/index.php" class="nav-link">
                    <span class="nav-icon">🛒</span>
                    <span class="nav-text">Carrito</span>
                </a>
                <a href="/carrito/historial.php" class="nav-link">
                    <span class="nav-icon">📋</span>
                    <span class="nav-text">Mis Compras</span>
                </a>
                <a href="/tracking.php" class="nav-link">
                    <span class="nav-icon">📦</span>
                    <span class="nav-text">Rastrear Envío</span>
                </a>
                <a href="/includes/ver_sugerencias.php" class="nav-link">
                    <span class="nav-icon">💡</span>
                    <span class="nav-text">Sugerencias</span>
                </a>
                <a href="/gemini/index.php" class="nav-link">
                    <span class="nav-icon">🤖</span>
                    <span class="nav-text">Asistente IA</span>
                </a>
               <?php if ($usuario_info): ?>
    <?php if ($usuario_info['rol'] === 'admin'): ?>
        <a href="/admin/index.php" class="nav-link admin-link">
            <span class="nav-icon">🔐</span>
            <span class="nav-text">Panel Admin</span>
        </a>
    <?php elseif ($usuario_info['rol'] === 'proveedor'): ?>
        <a href="/proveedor/dashboard.php" class="nav-link proveedor-link">
            <span class="nav-icon">🛠️</span>
            <span class="nav-text">Panel Proveedor</span>
        </a>
    <?php endif; ?>
<?php endif; ?>


            </div>
            
            <!-- Sección de Usuario -->
            <div class="header-user-section">
                <?php if ($usuario_info): ?>
                    <div class="user-info">
                        <div class="user-details">
                            <span class="user-greeting">
                                Hola, <?= htmlspecialchars(explode('@', $usuario_info['email'])[0]) ?>
                            </span>
                            <span class="user-email">
                                <?= htmlspecialchars($usuario_info['email']) ?>
                            </span>
                        </div>
                        <?php if ($usuario_info['rol'] === 'admin'): ?>
                            <span class="user-role-badge">ADMIN</span>
                        <?php endif; ?>
                    </div>
                    <form method="post" action="/firebase/logout.php" class="logout-form">
                        <button type="submit" class="btn-logout">
                            <span class="logout-icon">🚪</span>
                            <span class="logout-text">Cerrar sesión</span>
                        </button>
                    </form>
                <?php else: ?>
                
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Sidebar de Categorías -->
    <aside id="sidebar" class="sidebar">
        <div class="sidebar-header">
            <h3 class="sidebar-title">
                <span class="sidebar-icon">📂</span>
                Categorías
            </h3>
            <button onclick="toggleSidebar()" class="btn-close-sidebar">
                <span class="close-icon">✖</span>
            </button>
        </div>
        
        <div class="sidebar-content">
            <ul class="categories-list">
                <li class="category-item">
                    <a href="/productos/categoria.php?id=1" class="category-link">
                        <span class="category-icon">👕</span>
                        <span class="category-name">Ropa</span>
                    </a>
                </li>
                <li class="category-item">
                    <a href="/productos/categoria.php?id=2" class="category-link">
                        <span class="category-icon">📱</span>
                        <span class="category-name">Electrónica</span>
                    </a>
                </li>
                <li class="category-item">
                    <a href="/productos/categoria.php?id=3" class="category-link">
                        <span class="category-icon">👗</span>
                        <span class="category-name">Moda</span>
                    </a>
                </li>
                <li class="category-item">
                    <a href="/productos/categoria.php?id=4" class="category-link">
                        <span class="category-icon">🍎</span>
                        <span class="category-name">Alimentos</span>
                    </a>
                </li>
                <li class="category-item">
                    <a href="/productos/categoria.php?id=5" class="category-link">
                        <span class="category-icon">🏠</span>
                        <span class="category-name">Hogar</span>
                    </a>
                </li>
                <li class="category-item">
                    <a href="/productos/categoria.php?id=6" class="category-link">
                        <span class="category-icon">🧸</span>
                        <span class="category-name">Juguetes</span>
                    </a>
                </li>
                <li class="category-item">
                    <a href="/productos/categoria.php?id=7" class="category-link">
                        <span class="category-icon">💄</span>
                        <span class="category-name">Belleza</span>
                    </a>
                </li>
                <li class="category-item">
                    <a href="/productos/categoria.php?id=8" class="category-link">
                        <span class="category-icon">💊</span>
                        <span class="category-name">Salud</span>
                    </a>
                </li>
                <li class="category-item">
                    <a href="/productos/categoria.php?id=9" class="category-link">
                        <span class="category-icon">🐕</span>
                        <span class="category-name">Mascotas</span>
                    </a>
                </li>
                <li class="category-item">
                    <a href="/productos/categoria.php?id=10" class="category-link">
                        <span class="category-icon">📚</span>
                        <span class="category-name">Libros</span>
                    </a>
                </li>
                <li class="category-item">
                    <a href="/productos/categoria.php?id=11" class="category-link">
                        <span class="category-icon">🔧</span>
                        <span class="category-name">Ferretería</span>
                    </a>
                </li>
            </ul>
        </div>
    </aside>

    <div id="sidebar-overlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            const body = document.body;
            
            if (sidebar.classList.contains('visible')) {
                sidebar.classList.remove('visible');
                overlay.classList.remove('visible');
                body.classList.remove('sidebar-open');
            } else {
                sidebar.classList.add('visible');
                overlay.classList.add('visible');
                body.classList.add('sidebar-open');
            }
        }

        // Cerrar sidebar al hacer clic en un enlace (móvil)
        document.querySelectorAll('.category-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    toggleSidebar();
                }
            });
        });

        // Cerrar sidebar al redimensionar ventana
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebar-overlay');
                const body = document.body;
                
                sidebar.classList.remove('visible');
                overlay.classList.remove('visible');
                body.classList.remove('sidebar-open');
            }
        });

        // Efecto de hover mejorado para enlaces de navegación
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
            });
            
            link.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    </script>

    <main class="main-content">
        <!-- El contenido principal de la página va aquí -->
