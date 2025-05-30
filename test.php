<?php
require_once 'includes/email.php';

$destinatario = 'santos3paola@gmail.com'; // ✅ Asegúrate de poner un correo real aquí
$nombreProveedor = 'Taponsito Test';

$templatePath = 'includes/plantillas/plantilla_aprobacion.html';
$log = "";

if (!file_exists($templatePath)) {
    $log .= "❌ No se encontró la plantilla: $templatePath\n";
} else {
    $template = file_get_contents($templatePath);
    
    if ($template === false) {
        $log .= "❌ Error al leer el contenido de la plantilla.\n";
    } else {
        $mensaje  = str_replace('{NOMBRE}', $nombreProveedor, $template);

        $log .= "➡️ Enviando a: $destinatario\n";
        $log .= "➡️ Nombre del proveedor: $nombreProveedor\n";

        $resultado = enviarCorreo($destinatario, '🎉 Prueba de Aprobación como Proveedor', $mensaje);

        if ($resultado) {
            $log .= "✅ Correo enviado exitosamente\n";
        } else {
            $log .= "❌ Error al enviar el correo. Verifica error_log() o configuración SMTP\n";
        }
    }
}

// Mostrar resultado como texto plano
header('Content-Type: text/plain; charset=utf-8');
echo $log;
