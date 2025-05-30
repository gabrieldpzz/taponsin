<?php
session_start();
if (!isset($_SESSION['firebase_uid']) || $_SESSION['rol'] !== 'admin') {
    header("Location: /index.php");
    exit;
}
require_once '../includes/db.php';

// 🧠 Manejo del POST antes de cualquier salida
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create') {
            // Crear nuevo cupón
            $codigo = $_POST['codigo'];
            $descuento = $_POST['descuento'];
            $fecha = $_POST['fecha_expiracion'];
            $uso_max = $_POST['uso_maximo'];

            $stmt = $pdo->prepare("INSERT INTO cupones (codigo, descuento_porcentaje, fecha_expiracion, uso_maximo) VALUES (?, ?, ?, ?)");
            $stmt->execute([$codigo, $descuento, $fecha, $uso_max]);
        } elseif ($_POST['action'] === 'edit') {
            // Editar cupón existente
            $id = $_POST['cupon_id'];
            $codigo = $_POST['codigo'];
            $descuento = $_POST['descuento'];
            $fecha = $_POST['fecha_expiracion'];
            $uso_max = $_POST['uso_maximo'];

            $stmt = $pdo->prepare("UPDATE cupones SET codigo = ?, descuento_porcentaje = ?, fecha_expiracion = ?, uso_maximo = ? WHERE id = ?");
            $stmt->execute([$codigo, $descuento, $fecha, $uso_max, $id]);
        } elseif ($_POST['action'] === 'delete') {
            // Eliminar cupón
            $id = $_POST['cupon_id'];
            $stmt = $pdo->prepare("DELETE FROM cupones WHERE id = ?");
            $stmt->execute([$id]);
        }
    }

    header("Location: cupones.php");
    exit;
}

// ✅ Ahora sí, incluye el header visual después
require_once '../includes/header.php';

$cupones = $pdo->query("SELECT * FROM cupones ORDER BY id DESC")->fetchAll();
?>

<link rel="stylesheet" href="/assets/css/cupones.css?v=2">

<div class="admin-container">
    <div class="page-header">
        <h2>Gestión de Cupones</h2>
        <p class="page-subtitle">Crea y administra cupones de descuento para tu tienda</p>
    </div>

    <div class="form-card">
        <div class="card-header">
            <h3>Crear Nuevo Cupón</h3>
            <p>Completa los datos para generar un cupón de descuento</p>
        </div>
        
        <form method="post" class="coupon-form">
            <input type="hidden" name="action" value="create">
            <div class="form-grid">
                <div class="form-group">
                    <label for="codigo">Código del cupón</label>
                    <input name="codigo" id="codigo" placeholder="Ej: DESCUENTO20" required class="form-input">
                </div>
                
                <div class="form-group">
                    <label for="descuento">Porcentaje de descuento</label>
                    <input name="descuento" id="descuento" type="number" min="1" max="100" placeholder="20" required class="form-input">
                    <span class="input-suffix">%</span>
                </div>
                
                <div class="form-group">
                    <label for="fecha_expiracion">Fecha de expiración</label>
                    <input name="fecha_expiracion" id="fecha_expiracion" type="date" required class="form-input">
                </div>
                
                <div class="form-group">
                    <label for="uso_maximo">Usos máximos</label>
                    <input name="uso_maximo" id="uso_maximo" type="number" min="1" placeholder="100" required class="form-input">
                </div>
            </div>
            
            <button type="submit" class="btn-primary">
                <span class="btn-icon">+</span>
                Crear Cupón
            </button>
        </form>
    </div>

    <div class="table-card">
        <div class="card-header">
            <h3>Cupones Existentes</h3>
            <p>Lista de todos los cupones creados y su estado actual</p>
        </div>
        
        <div class="table-container">
            <?php if (empty($cupones)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🎫</div>
                    <h4>No hay cupones creados</h4>
                    <p>Crea tu primer cupón usando el formulario de arriba</p>
                </div>
            <?php else: ?>
                <table class="cupones-table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Descuento</th>
                            <th>Fecha de Expiración</th>
                            <th>Uso</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cupones as $c): ?>
                            <?php
                            $hoy = date('Y-m-d');
                            $expirado = $c['fecha_expiracion'] < $hoy;
                            $agotado = $c['usados'] >= $c['uso_maximo'];
                            $activo = !$expirado && !$agotado;
                            ?>
                            <tr class="<?= $activo ? 'row-active' : 'row-inactive' ?>">
                                <td>
                                    <code class="coupon-code"><?= htmlspecialchars($c['codigo']) ?></code>
                                </td>
                                <td>
                                    <span class="discount-badge"><?= $c['descuento_porcentaje'] ?>%</span>
                                </td>
                                <td><?= date('d/m/Y', strtotime($c['fecha_expiracion'])) ?></td>
                                <td>
                                    <div class="usage-info">
                                        <span class="usage-numbers"><?= $c['usados'] ?>/<?= $c['uso_maximo'] ?></span>
                                        <div class="usage-bar">
                                            <div class="usage-progress" style="width: <?= ($c['uso_maximo'] > 0) ? ($c['usados'] / $c['uso_maximo'] * 100) : 0 ?>%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($expirado): ?>
                                        <span class="status-badge status-expired">Expirado</span>
                                    <?php elseif ($agotado): ?>
                                        <span class="status-badge status-exhausted">Agotado</span>
                                    <?php else: ?>
                                        <span class="status-badge status-active">Activo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button type="button" class="btn-edit" onclick="editarCupon(<?= htmlspecialchars(json_encode($c)) ?>)">
                                            <span class="action-icon">✏️</span>
                                        </button>
                                        <button type="button" class="btn-delete" onclick="eliminarCupon(<?= $c['id'] ?>, '<?= htmlspecialchars($c['codigo']) ?>')">
                                            <span class="action-icon">🗑️</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal para editar cupón -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Editar Cupón</h3>
            <button type="button" class="btn-close" onclick="cerrarModal()">&times;</button>
        </div>
        
        <form method="post" id="editForm" class="modal-form">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="cupon_id" id="edit_cupon_id">
            
            <div class="form-grid">
                <div class="form-group">
                    <label for="edit_codigo">Código del cupón</label>
                    <input name="codigo" id="edit_codigo" required class="form-input">
                </div>
                
                <div class="form-group">
                    <label for="edit_descuento">Porcentaje de descuento</label>
                    <input name="descuento" id="edit_descuento" type="number" min="1" max="100" required class="form-input">
                    <span class="input-suffix">%</span>
                </div>
                
                <div class="form-group">
                    <label for="edit_fecha_expiracion">Fecha de expiración</label>
                    <input name="fecha_expiracion" id="edit_fecha_expiracion" type="date" required class="form-input">
                </div>
                
                <div class="form-group">
                    <label for="edit_uso_maximo">Usos máximos</label>
                    <input name="uso_maximo" id="edit_uso_maximo" type="number" min="1" required class="form-input">
                </div>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="cerrarModal()">
                    <span class="btn-icon">✖️</span>
                    Cancelar
                </button>
                <button type="submit" class="btn-primary">
                    <span class="btn-icon">💾</span>
                    Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Overlay del modal -->
<div id="modalOverlay" class="modal-overlay" onclick="cerrarModal()"></div>

<script>
function editarCupon(cupon) {
    // Llenar el formulario con los datos del cupón
    document.getElementById('edit_cupon_id').value = cupon.id;
    document.getElementById('edit_codigo').value = cupon.codigo;
    document.getElementById('edit_descuento').value = cupon.descuento_porcentaje;
    document.getElementById('edit_fecha_expiracion').value = cupon.fecha_expiracion;
    document.getElementById('edit_uso_maximo').value = cupon.uso_maximo;
    
    // Mostrar el modal
    document.getElementById('editModal').classList.add('show');
    document.getElementById('modalOverlay').classList.add('show');
    document.body.classList.add('modal-open');
}

function cerrarModal() {
    document.getElementById('editModal').classList.remove('show');
    document.getElementById('modalOverlay').classList.remove('show');
    document.body.classList.remove('modal-open');
}

function eliminarCupon(id, codigo) {
    if (confirm(`¿Estás seguro de que quieres eliminar el cupón "${codigo}"?`)) {
        // Crear formulario para eliminar
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="cupon_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Cerrar modal con tecla Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        cerrarModal();
    }
});

// Prevenir cierre del modal al hacer click dentro del contenido
document.querySelector('.modal-content').addEventListener('click', function(e) {
    e.stopPropagation();
});
</script>

<?php include '../includes/footer.php'; ?>
