<?php
session_start();
if (!isset($_SESSION['firebase_uid']) || $_SESSION['rol'] !== 'admin') {
    header("Location: /index.php");
    exit;
}

require_once '../includes/db.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    die("ID de producto no proporcionado.");
}

// Obtener datos del producto
$stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
$stmt->execute([$id]);
$producto = $stmt->fetch();

if (!$producto) {
    die("Producto no encontrado.");
}

// Obtener categorías para el select
$categorias = $pdo->query("SELECT * FROM categorias")->fetchAll();

// Actualizar si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'] ?? '';
    $precio = $_POST['precio'] ?? 0;
    $descripcion = $_POST['descripcion'] ?? '';
    $categoria_id = $_POST['categoria_id'] ?? null;
    $stock = $_POST['stock'] ?? 0;

    $stmt = $pdo->prepare("UPDATE productos SET nombre = ?, precio = ?, descripcion = ?, categoria_id = ?, stock = ? WHERE id = ?");
    $stmt->execute([$nombre, $precio, $descripcion, $categoria_id, $stock, $id]);

    header("Location: productos.php");
    exit;
}

require_once '../includes/header.php';
?>

<link rel="stylesheet" href="/assets/css/editar_producto.css">

<div class="admin-container">
    <div class="page-header">
        <h2>Editar Producto</h2>
        <p class="page-subtitle">Modifica la información del producto seleccionado</p>
        <div class="product-id">
            <span class="id-label">ID del producto:</span>
            <code class="id-code">#<?= $producto['id'] ?></code>
        </div>
    </div>

    <div class="content-wrapper">
        <div class="form-card">
            <div class="card-header">
                <h3>Información del Producto</h3>
                <p>Completa todos los campos para actualizar el producto</p>
            </div>
            
            <div class="card-content">
                <form method="post" class="product-form">
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label for="nombre">Nombre del producto</label>
                            <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($producto['nombre']) ?>" required class="form-input">
                            <span class="field-hint">Nombre descriptivo y único del producto</span>
                        </div>

                        <div class="form-group">
                            <label for="precio">Precio</label>
                            <div class="input-with-prefix">
                                <span class="input-prefix">$</span>
                                <input type="number" id="precio" step="0.01" name="precio" value="<?= $producto['precio'] ?>" required class="form-input">
                            </div>
                            <span class="field-hint">Precio en dólares (USD)</span>
                        </div>

                        <div class="form-group">
                            <label for="stock">Stock disponible</label>
                            <input type="number" id="stock" name="stock" value="<?= $producto['stock'] ?>" required class="form-input">
                            <span class="field-hint">Cantidad disponible en inventario</span>
                        </div>

                        <div class="form-group">
                            <label for="categoria_id">Categoría</label>
                            <select id="categoria_id" name="categoria_id" required class="form-select">
                                <option value="">-- Seleccionar categoría --</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $producto['categoria_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="field-hint">Categoría a la que pertenece el producto</span>
                        </div>

                        <div class="form-group full-width">
                            <label for="descripcion">Descripción</label>
                            <textarea id="descripcion" name="descripcion" rows="4" class="form-textarea" placeholder="Describe las características principales del producto..."><?= htmlspecialchars($producto['descripcion']) ?></textarea>
                            <span class="field-hint">Descripción detallada del producto (opcional)</span>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <span class="btn-icon">💾</span>
                            Guardar Cambios
                        </button>
                        <a href="productos.php" class="btn-secondary">
                            <span class="btn-icon">←</span>
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Vista previa del producto -->
        <div class="preview-card">
            <div class="card-header">
                <h3>Vista Previa</h3>
                <p>Así se verá el producto actualizado</p>
            </div>
            <div class="card-content">
                <div class="product-preview">
                    <?php if ($producto['imagen']): ?>
                        <div class="preview-image">
                            <img src="<?= htmlspecialchars($producto['imagen']) ?>" alt="<?= htmlspecialchars($producto['nombre']) ?>" class="product-image">
                        </div>
                    <?php else: ?>
                        <div class="preview-image placeholder">
                            <div class="placeholder-icon">📷</div>
                            <p>Sin imagen</p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="preview-info">
                        <h4 class="preview-name" id="preview-nombre"><?= htmlspecialchars($producto['nombre']) ?></h4>
                        <p class="preview-price" id="preview-precio">$<?= number_format($producto['precio'], 2) ?></p>
                        <div class="preview-stock">
                            <span class="stock-label">Stock:</span>
                            <span class="stock-value" id="preview-stock"><?= $producto['stock'] ?> unidades</span>
                        </div>
                        <div class="preview-category">
                            <span class="category-label">Categoría:</span>
                            <span class="category-value" id="preview-categoria">
                                <?php 
                                $categoria_actual = array_filter($categorias, function($cat) use ($producto) {
                                    return $cat['id'] == $producto['categoria_id'];
                                });
                                echo $categoria_actual ? htmlspecialchars(reset($categoria_actual)['nombre']) : 'Sin categoría';
                                ?>
                            </span>
                        </div>
                        <?php if ($producto['descripcion']): ?>
                            <div class="preview-description">
                                <p id="preview-descripcion"><?= htmlspecialchars($producto['descripcion']) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Actualizar vista previa en tiempo real
document.addEventListener('DOMContentLoaded', function() {
    const nombreInput = document.getElementById('nombre');
    const precioInput = document.getElementById('precio');
    const stockInput = document.getElementById('stock');
    const categoriaSelect = document.getElementById('categoria_id');
    const descripcionTextarea = document.getElementById('descripcion');

    const previewNombre = document.getElementById('preview-nombre');
    const previewPrecio = document.getElementById('preview-precio');
    const previewStock = document.getElementById('preview-stock');
    const previewCategoria = document.getElementById('preview-categoria');
    const previewDescripcion = document.getElementById('preview-descripcion');

    function actualizarPreview() {
        if (nombreInput.value) {
            previewNombre.textContent = nombreInput.value;
        }
        
        if (precioInput.value) {
            previewPrecio.textContent = '$' + parseFloat(precioInput.value).toFixed(2);
        }
        
        if (stockInput.value) {
            previewStock.textContent = stockInput.value + ' unidades';
        }
        
        if (categoriaSelect.value) {
            const selectedOption = categoriaSelect.options[categoriaSelect.selectedIndex];
            previewCategoria.textContent = selectedOption.text;
        } else {
            previewCategoria.textContent = 'Sin categoría';
        }
        
        if (previewDescripcion) {
            previewDescripcion.textContent = descripcionTextarea.value || 'Sin descripción';
        }
    }

    nombreInput.addEventListener('input', actualizarPreview);
    precioInput.addEventListener('input', actualizarPreview);
    stockInput.addEventListener('input', actualizarPreview);
    categoriaSelect.addEventListener('change', actualizarPreview);
    descripcionTextarea.addEventListener('input', actualizarPreview);
});
</script>

<?php require_once '../includes/footer.php'; ?>
