<?php
ob_start();
session_start();
if (!isset($_SESSION['firebase_uid']) || $_SESSION['rol'] !== 'admin') {
    header("Location: /index.php");
    exit;
}
require_once '../includes/db.php';
require_once '../includes/header.php';

// 🔧 Función para obtener UID del proveedor
function obtenerUIDProveedor($pdo, $id) {
    $stmt = $pdo->prepare("SELECT firebase_uid FROM proveedores WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ? $row['firebase_uid'] : 'desconocido';
}

$categorias = $pdo->query("SELECT * FROM categorias")->fetchAll();
$proveedores = $pdo->query("SELECT id, nombre FROM proveedores ORDER BY nombre")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $precio = $_POST['precio'] ?? 0;
    $categoria_id = $_POST['categoria_id'] ?? null;
    $variantes_json = $_POST['variantes_json'] ?? '{}';
    $proveedor_id = !empty($_POST['asignar_proveedor']) && !empty($_POST['proveedor_id']) ? $_POST['proveedor_id'] : null;
    $imagen_final = null;

    // Imagen: primero archivo, luego URL
    if (!empty($_FILES['imagen_archivo']['tmp_name'])) {
        $ext = pathinfo($_FILES['imagen_archivo']['name'], PATHINFO_EXTENSION);
        $nombreArchivo = ($proveedor_id ? obtenerUIDProveedor($pdo, $proveedor_id) . "_" : "") . time() . "." . $ext;
        $ruta = "../assets/img/" . $nombreArchivo;
        if (move_uploaded_file($_FILES['imagen_archivo']['tmp_name'], $ruta)) {
            $imagen_final = "/assets/img/" . $nombreArchivo;
        }
    } elseif (!empty($_POST['imagen_url'])) {
        $imagen_final = $_POST['imagen_url'];
    }

    // Preparar SQL
    $sql = "INSERT INTO productos (nombre, descripcion, precio, categoria_id, imagen, variantes_json" . ($proveedor_id ? ", proveedor_id" : "") . ") VALUES (?, ?, ?, ?, ?, ?" . ($proveedor_id ? ", ?" : "") . ")";
    $params = [$nombre, $descripcion, $precio, $categoria_id, $imagen_final, $variantes_json];
    if ($proveedor_id) $params[] = $proveedor_id;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    header("Location: productos.php");
    exit;
}
?>

<h2>Nuevo Producto</h2>
<form method="post" onsubmit="generarJSON()" enctype="multipart/form-data">
    <label>Nombre:</label><br>
    <input name="nombre" placeholder="Nombre" required><br><br>

    <label>Descripción:</label><br>
    <textarea name="descripcion" placeholder="Descripción" required></textarea><br><br>

    <label>Precio ($):</label><br>
    <input name="precio" type="number" step="0.01" required><br><br>

    <label>Categoría:</label><br>
    <select name="categoria_id" required>
        <?php foreach ($categorias as $cat): ?>
            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
        <?php endforeach; ?>
    </select><br><br>

    <label>🖼️ Subir imagen:</label><br>
    <input type="file" name="imagen_archivo" accept="image/*"><br><br>

    <label>🌐 O ingresar URL de imagen:</label><br>
    <input name="imagen_url" placeholder="https://..."><br><br>

    <label>
        <input type="checkbox" name="asignar_proveedor" id="asignar_proveedor" onchange="toggleProveedor()">
        Asignar a proveedor
    </label><br><br>

    <div id="proveedor_select" style="display:none;">
        <label>Seleccionar proveedor:</label><br>
        <select name="proveedor_id">
            <option value="">-- Seleccionar --</option>
            <?php foreach ($proveedores as $prov): ?>
                <option value="<?= $prov['id'] ?>"><?= htmlspecialchars($prov['nombre']) ?></option>
            <?php endforeach; ?>
        </select><br><br>
    </div>

    <div id="variantes">
        <h4>Variantes (opcional)</h4>
        <div class="variante">
            <input type="text" placeholder="Nombre de variante (ej. talla)" class="nombre-variante">
            <textarea placeholder="Valores separados por coma (ej. S,M,L)" class="valores-variante"></textarea>
        </div>
    </div>
    <button type="button" onclick="agregarVariante()">+ Agregar otra variante</button><br><br>

    <input type="hidden" name="variantes_json" id="variantes_json">
    <button type="submit">Guardar producto</button>
</form>

<script>
function agregarVariante() {
    const div = document.createElement('div');
    div.classList.add('variante');
    div.innerHTML = `
        <input type="text" placeholder="Nombre de variante" class="nombre-variante">
        <textarea placeholder="Valores separados por coma" class="valores-variante"></textarea>
    `;
    document.getElementById('variantes').appendChild(div);
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

function toggleProveedor() {
    const checkbox = document.getElementById('asignar_proveedor');
    const selector = document.getElementById('proveedor_select');
    selector.style.display = checkbox.checked ? 'block' : 'none';
}
</script>

<?php ob_end_flush(); ?>
