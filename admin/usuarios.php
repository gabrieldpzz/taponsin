<?php
session_start();
if (!isset($_SESSION['firebase_uid']) || $_SESSION['rol'] !== 'admin') {
    header("Location: /index.php");
    exit;
}
require_once '../includes/db.php';

// Manejar solicitudes AJAX para actualizar roles
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['uid']) || !isset($input['rol'])) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            exit;
        }
        
        $uid = $input['uid'];
        $nuevoRol = $input['rol'];
        
        // Validar que el rol sea válido
        if (!in_array($nuevoRol, ['usuario', 'admin'])) {
            echo json_encode(['success' => false, 'message' => 'Rol inválido']);
            exit;
        }
        
        // Verificar que el usuario existe
        $stmt = $pdo->prepare("SELECT id, rol FROM usuarios WHERE firebase_uid = ?");
        $stmt->execute([$uid]);
        $usuario = $stmt->fetch();
        
        if (!$usuario) {
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
            exit;
        }
        
        // Verificar que no se está intentando quitar el último admin
        if ($usuario['rol'] === 'admin' && $nuevoRol !== 'admin') {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total_admins FROM usuarios WHERE rol = 'admin'");
            $stmt->execute();
            $totalAdmins = $stmt->fetch()['total_admins'];
            
            if ($totalAdmins <= 1) {
                echo json_encode(['success' => false, 'message' => 'No se puede quitar el último administrador']);
                exit;
            }
        }
        
        // Actualizar el rol en la base de datos
        $stmt = $pdo->prepare("UPDATE usuarios SET rol = ? WHERE firebase_uid = ?");
        $resultado = $stmt->execute([$nuevoRol, $uid]);
        
        if ($resultado) {
            // Actualizar la sesión si es el usuario actual
            if ($uid === $_SESSION['firebase_uid']) {
                $_SESSION['rol'] = $nuevoRol;
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'Rol actualizado correctamente',
                'id' => $usuario['id'],
                'nuevo_rol' => $nuevoRol
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar la base de datos']);
        }
        
    } catch (Exception $e) {
        error_log("Error al actualizar rol: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
    }
    
    exit;
}

// Manejar solicitudes para eliminar usuarios
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_usuario'])) {
    header('Content-Type: application/json');
    
    try {
        $uid = $_POST['uid'];
        
        // Verificar que no se está intentando eliminar a sí mismo
        if ($uid === $_SESSION['firebase_uid']) {
            echo json_encode(['success' => false, 'message' => 'No puedes eliminarte a ti mismo']);
            exit;
        }
        
        // Verificar que el usuario existe
        $stmt = $pdo->prepare("SELECT id, rol FROM usuarios WHERE firebase_uid = ?");
        $stmt->execute([$uid]);
        $usuario = $stmt->fetch();
        
        if (!$usuario) {
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
            exit;
        }
        
        // Verificar que no se está eliminando el último admin
        if ($usuario['rol'] === 'admin') {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total_admins FROM usuarios WHERE rol = 'admin'");
            $stmt->execute();
            $totalAdmins = $stmt->fetch()['total_admins'];
            
            if ($totalAdmins <= 1) {
                echo json_encode(['success' => false, 'message' => 'No se puede eliminar el último administrador']);
                exit;
            }
        }
        
        // Eliminar el usuario
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE firebase_uid = ?");
        $resultado = $stmt->execute([$uid]);
        
        if ($resultado) {
            echo json_encode(['success' => true, 'message' => 'Usuario eliminado correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al eliminar el usuario']);
        }
        
    } catch (Exception $e) {
        error_log("Error al eliminar usuario: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
    }
    
    exit;
}

require_once '../includes/header.php';

// Obtener todos los usuarios
$usuarios = $pdo->query("SELECT * FROM usuarios ORDER BY id DESC")->fetchAll();

// Calcular estadísticas
$total_usuarios = count($usuarios);
$admins = array_filter($usuarios, function($u) { return $u['rol'] === 'admin'; });
$clientes = array_filter($usuarios, function($u) { return $u['rol'] === 'usuario'; });
$count_admins = count($admins);
$count_clientes = count($clientes);
?>

<link rel="stylesheet" href="/assets/css/usuarios.css">

<div class="admin-container">
    <div class="page-header">
        <h2>Gestión de Usuarios</h2>
        <p class="page-subtitle">Administra todos los usuarios registrados en la tienda</p>
    </div>

    <!-- Estadísticas de usuarios -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-info">
                <span class="stat-number"><?= $total_usuarios ?></span>
                <span class="stat-label">Total Usuarios</span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">🛡️</div>
            <div class="stat-info">
                <span class="stat-number"><?= $count_admins ?></span>
                <span class="stat-label">Administradores</span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">🛒</div>
            <div class="stat-info">
                <span class="stat-number"><?= $count_clientes ?></span>
                <span class="stat-label">Usuarios</span>
            </div>
        </div>
    </div>

    <!-- Filtros y búsqueda -->
    <div class="filters-card">
        <div class="filters-section">
            <div class="search-group">
                <input type="text" id="buscar-usuario" placeholder="Buscar usuarios por email o UID..." class="search-input">
            </div>
            
            <div class="filter-group">
                <select id="filtro-rol" class="filter-select">
                    <option value="">Todos los tipos</option>
                    <option value="admin">Administradores</option>
                    <option value="usuario">Usuarios</option>
                </select>
            </div>
            
            <div class="filter-group">
                <select id="ordenar-por" class="filter-select">
                    <option value="id">Ordenar por ID</option>
                    <option value="email">Ordenar por Email</option>
                    <option value="rol">Ordenar por Rol</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Tabla de usuarios -->
    <div class="users-card">
        <div class="card-header">
            <h3>Lista de Usuarios</h3>
            <p><?= $total_usuarios ?> usuario<?= $total_usuarios !== 1 ? 's' : '' ?> registrado<?= $total_usuarios !== 1 ? 's' : '' ?> en total</p>
        </div>
        
        <div class="card-content">
            <?php if (empty($usuarios)): ?>
                <div class="empty-state">
                    <div class="empty-icon">👥</div>
                    <h4>No hay usuarios registrados</h4>
                    <p>Los usuarios aparecerán aquí cuando se registren en la plataforma</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="users-table" id="tabla-usuarios">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Identificación</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $u): ?>
                                <tr class="user-row" 
                                    data-email="<?= strtolower(htmlspecialchars($u['email'])) ?>"
                                    data-uid="<?= strtolower(htmlspecialchars($u['firebase_uid'])) ?>"
                                    data-rol="<?= strtolower($u['rol']) ?>"
                                    data-id="<?= $u['id'] ?>">
                                    <td>
                                        <div class="user-info">
                                            <div class="user-avatar">
                                                <span class="avatar-text"><?= strtoupper(substr($u['email'], 0, 2)) ?></span>
                                            </div>
                                            <div class="user-details">
                                                <span class="user-email"><?= htmlspecialchars($u['email']) ?></span>
                                                <span class="user-id">ID: #<?= $u['id'] ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="uid-info">
                                            <code class="firebase-uid" title="<?= htmlspecialchars($u['firebase_uid']) ?>">
                                                <?= substr(htmlspecialchars($u['firebase_uid']), 0, 12) ?>...
                                            </code>
                                            <button class="btn-copy-uid" onclick="copiarUID('<?= htmlspecialchars($u['firebase_uid']) ?>')" title="Copiar UID completo">
                                                📋
                                            </button>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="role-badge role-<?= strtolower($u['rol']) ?>">
                                            <?php if ($u['rol'] === 'admin'): ?>
                                                🛡️ Administrador
                                            <?php else: ?>
                                                🛒 Usuario
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-active">
                                            ✅ Activo
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-view" title="Ver detalles" onclick="verDetalles(<?= $u['id'] ?>)">
                                                👁️
                                            </button>
                                            <?php if ($u['rol'] !== 'admin' || $count_admins > 1): ?>
                                                <button class="btn-edit" title="Editar rol" onclick="abrirModalEditarRol(<?= $u['id'] ?>, '<?= $u['firebase_uid'] ?>', '<?= $u['rol'] ?>')">
                                                    ✏️
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($u['firebase_uid'] !== $_SESSION['firebase_uid']): ?>
                                                <button class="btn-delete" title="Eliminar usuario" onclick="eliminarUsuario('<?= $u['firebase_uid'] ?>', '<?= htmlspecialchars($u['email']) ?>', <?= $u['id'] ?>)">
                                                    🗑️
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Información adicional -->
    <div class="info-cards-grid">
        <div class="info-card">
            <div class="info-header">
                <h4>🔐 Seguridad</h4>
            </div>
            <div class="info-content">
                <p>Los usuarios se autentican mediante Firebase Authentication. Los UIDs son únicos y seguros.</p>
            </div>
        </div>
        
        <div class="info-card">
            <div class="info-header">
                <h4>👑 Roles</h4>
            </div>
            <div class="info-content">
                <p><strong>Admin:</strong> Acceso completo al panel<br>
                <strong>Usuario:</strong> Acceso a la tienda</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal para editar rol -->
<div id="modalEditarRol" class="modal-overlay" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>🛠️ Editar Rol de Usuario</h3>
            <button type="button" class="btn-close-modal" onclick="cerrarModalEditarRol()">✖</button>
        </div>
        <form id="formEditarRol">
            <input type="hidden" id="editarRolUid">
            <input type="hidden" id="editarRolId">
            
            <div class="form-group">
                <label for="editarRolSelect">Seleccionar nuevo rol:</label>
                <select id="editarRolSelect" class="form-control" required>
                    <option value="usuario">👤 Usuario</option>
                    <option value="admin">🛡️ Administrador</option>
                </select>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="cerrarModalEditarRol()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            </div>
        </form>
        <div id="editarRolMensaje" class="alert" style="display:none;"></div>
    </div>
</div>

<style>
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    border-radius: 12px;
    padding: 24px;
    max-width: 90vw;
    width: 400px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 1px solid #e0e0e0;
}

.modal-header h3 {
    margin: 0;
    color: #333446;
    font-size: 1.2rem;
}

.btn-close-modal {
    background: none;
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
    color: #7F8CAA;
    padding: 4px;
    border-radius: 4px;
    transition: all 0.3s ease;
}

.btn-close-modal:hover {
    background: #f0f0f0;
    color: #333446;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333446;
}

.form-control {
    width: 100%;
    padding: 12px;
    border: 2px solid #B8CFCE;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: #6C63FF;
    box-shadow: 0 0 0 3px rgba(108, 99, 255, 0.1);
}

.modal-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-primary {
    background: #6C63FF;
    color: white;
}

.btn-primary:hover {
    background: #5a52e6;
    transform: translateY(-1px);
}

.btn-secondary {
    background: #f0f0f0;
    color: #333446;
}

.btn-secondary:hover {
    background: #e0e0e0;
}

.alert {
    padding: 12px;
    border-radius: 8px;
    margin-top: 16px;
    font-size: 0.9rem;
}

.alert.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 12px 20px;
    border-radius: 8px;
    color: white;
    font-weight: 500;
    z-index: 10000;
    animation: slideInRight 0.3s ease;
}

.notification.success {
    background: #28a745;
}

.notification.error {
    background: #dc3545;
}

.notification.info {
    background: #17a2b8;
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
</style>

<script>
// Variables globales
let modalEditarRol = document.getElementById('modalEditarRol');
let formEditarRol = document.getElementById('formEditarRol');
let mensajeDiv = document.getElementById('editarRolMensaje');

// Filtrado y búsqueda
document.addEventListener('DOMContentLoaded', function() {
    const buscarInput = document.getElementById('buscar-usuario');
    const filtroRol = document.getElementById('filtro-rol');
    const ordenarPor = document.getElementById('ordenar-por');
    const filas = document.querySelectorAll('.user-row');

    function filtrarUsuarios() {
        const textoBusqueda = buscarInput.value.toLowerCase();
        const rolSeleccionado = filtroRol.value.toLowerCase();

        filas.forEach(fila => {
            const email = fila.dataset.email;
            const uid = fila.dataset.uid;
            const rol = fila.dataset.rol;

            let mostrar = true;

            if (textoBusqueda && !email.includes(textoBusqueda) && !uid.includes(textoBusqueda)) {
                mostrar = false;
            }

            if (rolSeleccionado && rol !== rolSeleccionado) {
                mostrar = false;
            }

            fila.style.display = mostrar ? '' : 'none';
        });
    }

    function ordenarTabla() {
        const tbody = document.querySelector('#tabla-usuarios tbody');
        const filasArray = Array.from(filas);
        const criterio = ordenarPor.value;

        filasArray.sort((a, b) => {
            let valorA, valorB;
            
            switch(criterio) {
                case 'email':
                    valorA = a.dataset.email;
                    valorB = b.dataset.email;
                    break;
                case 'rol':
                    valorA = a.dataset.rol;
                    valorB = b.dataset.rol;
                    break;
                default:
                    valorA = parseInt(a.dataset.id);
                    valorB = parseInt(b.dataset.id);
            }

            if (valorA < valorB) return -1;
            if (valorA > valorB) return 1;
            return 0;
        });

        filasArray.forEach(fila => tbody.appendChild(fila));
    }

    buscarInput.addEventListener('input', filtrarUsuarios);
    filtroRol.addEventListener('change', filtrarUsuarios);
    ordenarPor.addEventListener('change', ordenarTabla);
});

// Funciones de acción
function copiarUID(uid) {
    navigator.clipboard.writeText(uid).then(() => {
        mostrarNotificacion('UID copiado al portapapeles', 'success');
    }).catch(() => {
        mostrarNotificacion('Error al copiar UID', 'error');
    });
}

function verDetalles(id) {
    mostrarNotificacion('Función de detalles en desarrollo', 'info');
}

function abrirModalEditarRol(id, uid, rolActual) {
    document.getElementById('editarRolUid').value = uid;
    document.getElementById('editarRolId').value = id;
    document.getElementById('editarRolSelect').value = rolActual;
    mensajeDiv.style.display = 'none';
    modalEditarRol.style.display = 'flex';
}

function cerrarModalEditarRol() {
    modalEditarRol.style.display = 'none';
    formEditarRol.reset();
    mensajeDiv.style.display = 'none';
}

function eliminarUsuario(uid, email, id) {
    if (confirm(`¿Estás seguro de que deseas eliminar al usuario?\n\nEmail: ${email}\n\nEsta acción no se puede deshacer.`)) {
        const formData = new FormData();
        formData.append('eliminar_usuario', '1');
        formData.append('uid', uid);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarNotificacion('Usuario eliminado correctamente', 'success');
                // Remover la fila de la tabla
                const fila = document.querySelector(`[data-id="${id}"]`);
                if (fila) {
                    fila.remove();
                }
                // Actualizar estadísticas
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                mostrarNotificacion(data.message || 'Error al eliminar usuario', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarNotificacion('Error al procesar la solicitud', 'error');
        });
    }
}

function mostrarNotificacion(mensaje, tipo) {
    const notificacion = document.createElement('div');
    notificacion.className = `notification ${tipo}`;
    notificacion.textContent = mensaje;
    
    document.body.appendChild(notificacion);
    
    setTimeout(() => {
        notificacion.remove();
    }, 4000);
}

// Manejar envío del formulario de edición de rol
formEditarRol.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const uid = document.getElementById('editarRolUid').value;
    const rol = document.getElementById('editarRolSelect').value;
    const id = document.getElementById('editarRolId').value;
    
    if (!uid || !rol) {
        mostrarMensajeModal('Por favor, completa todos los campos.', 'error');
        return;
    }
    
    // Deshabilitar el botón de envío
    const submitBtn = formEditarRol.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Guardando...';
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ uid, rol })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarMensajeModal('Rol actualizado exitosamente', 'success');
            
            // Actualizar la fila en la tabla
            const fila = document.querySelector(`[data-id="${id}"]`);
            if (fila) {
                fila.dataset.rol = rol;
                const roleBadge = fila.querySelector('.role-badge');
                if (rol === 'admin') {
                    roleBadge.innerHTML = '🛡️ Administrador';
                    roleBadge.className = 'role-badge role-admin';
                } else {
                    roleBadge.innerHTML = '🛒 Usuario';
                    roleBadge.className = 'role-badge role-usuario';
                }
            }
            
            // Cerrar modal después de 2 segundos
            setTimeout(() => {
                cerrarModalEditarRol();
                mostrarNotificacion('Rol actualizado correctamente', 'success');
            }, 2000);
            
        } else {
            mostrarMensajeModal(data.message || 'Error al actualizar el rol', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarMensajeModal('Error al procesar la solicitud', 'error');
    })
    .finally(() => {
        // Rehabilitar el botón
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    });
});

function mostrarMensajeModal(mensaje, tipo) {
    mensajeDiv.textContent = mensaje;
    mensajeDiv.className = `alert ${tipo}`;
    mensajeDiv.style.display = 'block';
}

// Cerrar modal al hacer clic fuera de él
modalEditarRol.addEventListener('click', function(e) {
    if (e.target === modalEditarRol) {
        cerrarModalEditarRol();
    }
});
</script>

<?php include '../includes/footer.php'; ?>