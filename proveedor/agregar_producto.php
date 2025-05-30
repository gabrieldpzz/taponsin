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
        <title>Agregar Producto</title>
        <link rel="stylesheet" href="/assets/css/agregar_producto.css">
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

// Obtener categorías
$categorias = $pdo->query("SELECT id, nombre FROM categorias ORDER BY nombre")->fetchAll();

// Procesar envío del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $precio = $_POST['precio'] ?? 0;
    $categoria_id = $_POST['categoria_id'] ?? null;
    $variantes_json = $_POST['variantes_json'] ?? '{}';
    $imagen_url = null;

    // Si subió imagen
    if (!empty($_FILES['imagen']['tmp_name'])) {
        $nombreArchivo = time() . "_" . basename($_FILES['imagen']['name']);
        $ruta = "../assets/img/" . $nombreArchivo;

        if (move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta)) {
            $imagen_url = "/assets/img/" . $nombreArchivo;
        }
    }

    // Si no subió imagen pero ingresó una URL
    if (!$imagen_url && !empty($_POST['imagen_url'])) {
        $imagen_url = $_POST['imagen_url'];
    }

    // Insertar producto
    $stmt = $pdo->prepare("INSERT INTO productos (nombre, descripcion, precio, categoria_id, imagen, proveedor_id, estado, variantes_json) VALUES (?, ?, ?, ?, ?, ?, 'pendiente', ?)");
    $stmt->execute([$nombre, $descripcion, $precio, $categoria_id, $imagen_url, $proveedor_id, $variantes_json]);

    header("Location: mis_productos.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Producto</title>
    <link rel="stylesheet" href="/assets/css/agregar_producto.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-title">
                    <h1>➕ Agregar nuevo producto</h1>
                    <p class="header-subtitle">Completa la información para agregar un producto a tu catálogo</p>
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
                        <input type="text" id="nombre" name="nombre" required placeholder="Ingresa el nombre del producto">
                    </div>
                    
                    <div class="form-group">
                        <label for="descripcion">📝 Descripción</label>
                        <textarea id="descripcion" name="descripcion" required placeholder="Describe las características del producto"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="precio">💰 Precio ($)</label>
                            <input type="number" id="precio" step="0.01" name="precio" required placeholder="0.00">
                        </div>
                        
                        <div class="form-group">
                            <label for="categoria_id">📂 Categoría</label>
                            <select id="categoria_id" name="categoria_id" required>
                                <option value="">Seleccione una categoría</option>
                                <?php foreach ($categorias as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Imagen del Producto -->
                <div class="form-section">
                    <h3 class="section-title">🖼️ Imagen del Producto</h3>
                    
                    <div class="image-upload-section">
                        <div class="form-group file-group">
                            <label for="imagen">📤 Subir imagen desde tu dispositivo</label>
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
                            <input type="url" id="imagen_url" name="imagen_url" placeholder="https://ejemplo.com/imagen.jpg">
                            <small class="url-help">Pega la URL de una imagen desde internet</small>
                        </div>
                    </div>
                </div>

                <!-- Variantes -->
                <div class="form-section">
                    <h3 class="section-title">🧩 Variantes del Producto (Opcional)</h3>
                    <p class="section-description">Agrega variantes como tallas, colores, sabores, etc.</p>
                    
                    <div id="variantes" class="variants-container">
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
                        Guardar Producto
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
    let variantCount = 1;

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