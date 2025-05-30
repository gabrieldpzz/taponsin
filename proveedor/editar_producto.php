<?php
session_start();
if (!isset($_SESSION['firebase_uid'])) {
    header("Location: /index.php");
    exit;
}

require_once '../includes/header.php';
require_once '../includes/db.php';
$uid = $_SESSION['firebase_uid'];

// Obtener proveedor_id
$stmt = $pdo->prepare("SELECT id FROM proveedores WHERE firebase_uid = ?");
$stmt->execute([$uid]);
$proveedor = $stmt->fetch();

if (!$proveedor) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Editar Producto</title>
        <link rel="stylesheet" href="/assets/css/editar_producto_proveedor.css?v=2">
    </head>
    <body>
        <div class="container">
            <div class="error-card">
                <div class="error-icon">⚠️</div>
                <h2>Acceso Denegado</h2>
                <p>No estás registrado como proveedor.</p>
                <a href="/dashboard.php" class="btn btn-info">Volver al Dashboard</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$proveedor_id = $proveedor['id'];
$producto_id = $_GET['id'] ?? null;

// Obtener producto
$stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ? AND proveedor_id = ?");
$stmt->execute([$producto_id, $proveedor_id]);
$producto = $stmt->fetch();

if (!$producto) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Editar Producto</title>
        <link rel="stylesheet" href="/assets/css/editar_producto_proveedor.css?v=2">
    </head>
    <body>
        <div class="container">
            <div class="error-card">
                <div class="error-icon">❌</div>
                <h2>Producto No Encontrado</h2>
                <p>El producto no existe o no tienes permiso para editarlo.</p>
                <a href="/proveedor/mis_productos.php" class="btn btn-info">Volver a Mis Productos</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Obtener categorías
$categorias = $pdo->query("SELECT id, nombre FROM categorias ORDER BY nombre")->fetchAll();

// Procesar envío del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $precio = $_POST['precio'] ?? 0;
    $categoria_id = $_POST['categoria_id'] ?? null;
    $variantes_json = $_POST['variantes_json'] ?? '{}';
    $imagen_url = $producto['imagen']; // por defecto la actual

    // Si subió nueva imagen
    if (!empty($_FILES['imagen']['tmp_name'])) {
        $nombreArchivo = time() . "_" . basename($_FILES['imagen']['name']);
        $ruta = "../assets/img/" . $nombreArchivo;
        if (move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta)) {
            $imagen_url = "/assets/img/" . $nombreArchivo;
        }
    } elseif (!empty($_POST['imagen_url'])) {
        $imagen_url = $_POST['imagen_url'];
    }

    // Actualizar
    $stmt = $pdo->prepare("UPDATE productos SET nombre = ?, descripcion = ?, precio = ?, categoria_id = ?, imagen = ?, variantes_json = ? WHERE id = ? AND proveedor_id = ?");
    $stmt->execute([$nombre, $descripcion, $precio, $categoria_id, $imagen_url, $variantes_json, $producto_id, $proveedor_id]);

    header("Location: mis_productos.php");
    exit;
}

// Decodificar variantes
$variantes = json_decode($producto['variantes_json'] ?? '{}', true);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Producto</title>
    <link rel="stylesheet" href="/assets/css/editar_producto_proveedor.css?v=2">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-title">
                    <h1>✏️ Editar Producto</h1>
                    <p class="header-subtitle">Actualiza la información de tu producto</p>
                </div>
            </div>
        </div>

        <!-- Formulario -->
        <div class="form-card">
            <form method="POST" enctype="multipart/form-data" onsubmit="generarJSON()" class="product-form">
                
                <!-- Información Básica -->
                <div class="form-section">
                    <h3 class="section-title">📋 Información Básica</h3>
                    
                    <div class="form-group">
                        <label for="nombre">📛 Nombre del producto</label>
                        <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($producto['nombre']) ?>" required placeholder="Ingresa el nombre del producto">
                    </div>
                    
                    <div class="form-group">
                        <label for="descripcion">📝 Descripción</label>
                        <textarea id="descripcion" name="descripcion" required placeholder="Describe las características del producto"><?= htmlspecialchars($producto['descripcion']) ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="precio">💰 Precio ($)</label>
                            <input type="number" id="precio" step="0.01" name="precio" value="<?= $producto['precio'] ?>" required placeholder="0.00">
                        </div>
                        
                        <div class="form-group">
                            <label for="categoria_id">📂 Categoría</label>
                            <select id="categoria_id" name="categoria_id" required>
                                <option value="">Seleccione una categoría</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $producto['categoria_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Imagen del Producto -->
                <div class="form-section">
                    <h3 class="section-title">🖼️ Imagen del Producto</h3>
                    
                    <?php if (!empty($producto['imagen'])): ?>
                        <div class="current-image">
                            <h4>Imagen actual:</h4>
                            <div class="image-preview">
                                <img src="<?= htmlspecialchars($producto['imagen']) ?>" alt="Imagen actual">
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="no-image">
                            <div class="no-image-icon">🖼️</div>
                            <p>Este producto no tiene imagen</p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="image-upload-section">
                        <div class="form-group file-group">
                            <label for="imagen">📤 Subir nueva imagen</label>
                            <div class="file-input-wrapper">
                                <input type="file" id="imagen" name="imagen" accept="image/*">
                                <div class="file-input-display">
                                    <span class="file-icon">📷</span>
                                    <span class="file-text">Seleccionar imagen</span>
                                </div>
                            </div>
                            <small class="file-help">Formatos aceptados: JPG, PNG, GIF (máx. 5MB)</small>
                        </div>
                        
                        <div class="divider">
                            <span class="divider-text">O</span>
                        </div>
                        
                        <div class="form-group">
                            <label for="imagen_url">🌐 URL de imagen externa</label>
                            <input type="url" id="imagen_url" name="imagen_url" 
                                   placeholder="https://ejemplo.com/imagen.jpg"
                                   value="<?= (strpos($producto['imagen'], 'http') === 0) ? htmlspecialchars($producto['imagen']) : '' ?>">
                            <small class="url-help">Pega la URL de una imagen desde internet</small>
                        </div>
                    </div>
                </div>

                <!-- Variantes -->
                <div class="form-section">
                    <h3 class="section-title">🧩 Variantes del Producto</h3>
                    <p class="section-description">Edita o agrega variantes como tallas, colores, sabores, etc.</p>
                    
                    <div id="variantes" class="variants-container">
                        <?php if (!empty($variantes)): ?>
                            <?php $varianteIndex = 0; ?>
                            <?php foreach ($variantes as $nombreVar => $valores): ?>
                                <?php $varianteIndex++; ?>
                                <div class="variante variant-item">
                                    <div class="variant-header">
                                        <span class="variant-number"><?= $varianteIndex ?></span>
                                        <span class="variant-title">Variante</span>
                                        <?php if ($varianteIndex > 1): ?>
                                            <button type="button" class="btn-remove-variant" onclick="eliminarVariante(this)">
                                                <span>🗑️</span>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <div class="variant-content">
                                        <div class="form-group">
                                            <label>Nombre de la variante</label>
                                            <input type="text" placeholder="Ej: Talla, Color, Sabor" class="nombre-variante" value="<?= htmlspecialchars($nombreVar) ?>">
                                        </div>
                                        <div class="form-group">
                                            <label>Valores disponibles</label>
                                            <textarea placeholder="Separa los valores con comas. Ej: S, M, L, XL" class="valores-variante"><?= htmlspecialchars(implode(', ', $valores)) ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="variante variant-item">
                                <div class="variant-header">
                                    <span class="variant-number">1</span>
                                    <span class="variant-title">Variante</span>
                                </div>
                                <div class="variant-content">
                                    <div class="form-group">
                                        <label>Nombre de la variante</label>
                                        <input type="text" placeholder="Ej: Talla, Color, Sabor" class="nombre-variante">
                                    </div>
                                    <div class="form-group">
                                        <label>Valores disponibles</label>
                                        <textarea placeholder="Separa los valores con comas. Ej: S, M, L, XL" class="valores-variante"></textarea>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <button type="button" onclick="agregarVariante()" class="btn btn-secondary btn-add-variant">
                        <span class="btn-icon">➕</span>
                        Agregar otra variante
                    </button>
                </div>

                <input type="hidden" name="variantes_json" id="variantes_json">

                <!-- Acciones del formulario -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <span class="btn-icon">💾</span>
                        Guardar Cambios
                    </button>
                    <a href="mis_productos.php" class="btn btn-secondary">
                        <span class="btn-icon">❌</span>
                        Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
    let variantCount = <?= !empty($variantes) ? count($variantes) : 1 ?>;

    function agregarVariante() {
        variantCount++;
        const div = document.createElement('div');
        div.classList.add('variante', 'variant-item');
        div.innerHTML = `
            <div class="variant-header">
                <span class="variant-number">${variantCount}</span>
                <span class="variant-title">Variante</span>
                <button type="button" class="btn-remove-variant" onclick="eliminarVariante(this)">
                    <span>🗑️</span>
                </button>
            </div>
            <div class="variant-content">
                <div class="form-group">
                    <label>Nombre de la variante</label>
                    <input type="text" placeholder="Ej: Talla, Color, Sabor" class="nombre-variante">
                </div>
                <div class="form-group">
                    <label>Valores disponibles</label>
                    <textarea placeholder="Separa los valores con comas. Ej: S, M, L, XL" class="valores-variante"></textarea>
                </div>
            </div>
        `;
        document.getElementById('variantes').appendChild(div);
    }

    function eliminarVariante(button) {
        const variantItem = button.closest('.variant-item');
        variantItem.remove();
        actualizarNumerosVariantes();
    }

    function actualizarNumerosVariantes() {
        const variants = document.querySelectorAll('.variant-item');
        variants.forEach((variant, index) => {
            const numberSpan = variant.querySelector('.variant-number');
            numberSpan.textContent = index + 1;
        });
        variantCount = variants.length;
    }

    function generarJSON() {
        const nombres = document.querySelectorAll('.nombre-variante');
        const valores = document.querySelectorAll('.valores-variante');
        const resultado = {};

        for (let i = 0; i < nombres.length; i++) {
            const nombre = nombres[i].value.trim();
            const valoresArray = valores[i].value.split(',').map(v => v.trim()).filter(v => v !== '');
            if (nombre && valoresArray.length) {
                resultado[nombre] = valoresArray;
            }
        }

        document.getElementById('variantes_json').value = JSON.stringify(resultado);
    }
    </script>
</body>
</html>