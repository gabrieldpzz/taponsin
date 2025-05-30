<?php
session_start();

// ✅ Verificar login
if (!isset($_SESSION['firebase_uid'])) {
    header("Location: /index.php");
    exit;
}

require_once '../includes/db.php';
require_once '../includes/email.php';

// ✅ Si ya envió solicitud, evitar duplicados

$uid = $_SESSION['firebase_uid'];
$stmt = $pdo->prepare("SELECT estado FROM solicitudes_proveedor WHERE firebase_uid = ?");
$stmt->execute([$uid]);
$solicitud = $stmt->fetch();

if ($solicitud) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Registro de Proveedor</title>
        <link rel="stylesheet" href="/assets/css/registro_proveedor.css">
    </head>
    <body>
        <div class="container">
            <div class="status-card">
                <div class="status-icon">📋</div>
                <h2>Solicitud Enviada</h2>
                <p>Ya enviaste una solicitud de proveedor.</p>
                <div class="status-badge status-<?php echo strtolower($solicitud['estado']); ?>">
                    Estado: <strong><?php echo $solicitud['estado']; ?></strong>
                </div>
                <a href="/dashboard.php" class="btn btn-secondary">Volver al Dashboard</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $telefono = $_POST['telefono'] ?? '';
    $documento_url = null;
    $foto_cara_url = null;

    $rutaCarpeta = __DIR__ . "/documentos_proveedor";
    if (!is_dir($rutaCarpeta)) {
        mkdir($rutaCarpeta, 0777, true);
    }

    // 📄 Documento
    if (!empty($_FILES['documento']['tmp_name'])) {
        $ext = pathinfo($_FILES['documento']['name'], PATHINFO_EXTENSION);
        $nombreArchivo = $uid . "_documento." . $ext;
        $rutaDestino = $rutaCarpeta . "/" . $nombreArchivo;
        if (move_uploaded_file($_FILES['documento']['tmp_name'], $rutaDestino)) {
            $documento_url = $nombreArchivo;
        }
    }

    // 📸 Foto del rostro
    if (!empty($_FILES['foto_cara']['tmp_name'])) {
        $ext2 = pathinfo($_FILES['foto_cara']['name'], PATHINFO_EXTENSION);
        $nombreFoto = $uid . "_cara." . $ext2;
        $rutaFoto = $rutaCarpeta . "/" . $nombreFoto;
        if (move_uploaded_file($_FILES['foto_cara']['tmp_name'], $rutaFoto)) {
            $foto_cara_url = $nombreFoto;
        }
    }

    // 🔒 Guardar solicitud en DB
    $stmt = $pdo->prepare("INSERT INTO solicitudes_proveedor 
        (firebase_uid, nombre_proveedor, descripcion, telefono, documento_url, foto_cara_url) 
        VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$uid, $nombre, $descripcion, $telefono, $documento_url, $foto_cara_url]);

    // 📧 Incluir email.php para enviar notificación
    require_once '../includes/email.php'; // <--- NO OLVIDES ESTE

    // ✉️ Enviar correo al admin
    $adminEmail = 'g.alexis7112@gmail.com';
    $asunto = '📩 Nueva solicitud de proveedor';
    $mensaje = "
        <h3>📌 Nueva solicitud recibida</h3>
        <p><strong>Nombre:</strong> $nombre</p>
        <p><strong>Descripción:</strong> $descripcion</p>
        <p><strong>Teléfono:</strong> $telefono</p>
        <p>Revisa el panel de administración para aprobarla.</p>
    ";

    enviarCorreo($adminEmail, $asunto, $mensaje);

    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Registro de Proveedor</title>
        <link rel="stylesheet" href="/assets/css/registro_proveedor.css">
    </head>
    <body>
        <div class="container">
            <div class="success-card">
                <div class="success-icon">✅</div>
                <h2>Solicitud Enviada</h2>
                <p>Tu solicitud ha sido enviada correctamente. Serás contactado pronto.</p>
                <a href="/dashboard.php" class="btn btn-primary">Volver al Dashboard</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Proveedor</title>
    <link rel="stylesheet" href="/assets/css/registro_proveedor.css">
</head>
<body>
    <div class="container">
        <div class="form-card">
            <div class="form-header">
                <div class="form-icon">🛍️</div>
                <h2>Postulación como Proveedor</h2>
                <p class="form-subtitle">Completa el formulario para solicitar ser proveedor en nuestra plataforma</p>
            </div>
            
            <form method="POST" enctype="multipart/form-data" class="provider-form">
                <div class="form-section">
                    <h3>Información Básica</h3>
                    
                    <div class="form-group">
                        <label for="nombre">📛 Nombre del proveedor</label>
                        <input type="text" id="nombre" name="nombre" required placeholder="Ingresa el nombre de tu negocio">
                    </div>
                    
                    <div class="form-group">
                        <label for="descripcion">📦 ¿Qué vendes o a qué se dedica tu negocio?</label>
                        <textarea id="descripcion" name="descripcion" required placeholder="Describe tu negocio y los productos que ofreces"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="telefono">📞 Teléfono</label>
                        <input type="text" id="telefono" name="telefono" required placeholder="Número de contacto">
                    </div>
                </div>

                <div class="form-section">
                    <h3>Documentación Requerida</h3>
                    
                    <div class="form-group file-group">
                        <label for="documento">🪪 Sube una imagen legible de tu DUI</label>
                        <div class="file-input-wrapper">
                            <input type="file" id="documento" name="documento" required accept="image/*,.pdf">
                            <div class="file-input-display">
                                <span class="file-icon">📄</span>
                                <span class="file-text">Seleccionar archivo</span>
                            </div>
                        </div>
                        <small class="file-help">Formatos aceptados: JPG, PNG, PDF</small>
                    </div>
                    
                    <div class="form-group file-group">
                        <label for="foto_cara">📸 Subir foto del rostro</label>
                        <div class="file-input-wrapper">
                            <input type="file" id="foto_cara" name="foto_cara" required accept="image/*">
                            <div class="file-input-display">
                                <span class="file-icon">📷</span>
                                <span class="file-text">Seleccionar foto</span>
                            </div>
                        </div>
                        <small class="file-help">Foto clara del rostro para verificación</small>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <span class="btn-icon">📤</span>
                        Enviar solicitud
                    </button>
                    <a href="/dashboard.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>