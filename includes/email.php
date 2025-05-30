<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php'; // Asegúrate que PHPMailer esté instalado vía Composer

function enviarCorreo($destinatario, $asunto, $mensajeHtml) {
    $mail = new PHPMailer(true);
    try {

        $mail->CharSet = 'UTF-8';                  // ✅ ESTO
        $mail->Encoding = 'base64';      

        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'xxxperrin@gmail.com'; // TU GMAIL
        $mail->Password = 'bdcm yzum soqj bjmb'; // TU CLAVE DE APLICACIÓN
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        // Configuración del mensaje
        $mail->setFrom('xxxperrin@gmail.com', 'TaponShop');
        $mail->addAddress($destinatario);
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $mensajeHtml;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Error al enviar correo: {$mail->ErrorInfo}");
        return false;
    }
}
