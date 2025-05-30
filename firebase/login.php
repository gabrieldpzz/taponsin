<?php
require_once '../vendor/autoload.php';
require_once '../includes/db.php';
session_start();

use GuzzleHttp\Client;

// Procesar reset de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    header('Content-Type: application/json');

    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Por favor ingresa tu email']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Por favor ingresa un email válido']);
        exit;
    }

    try {
        $client = new Client();
        $response = $client->post(
            'https://identitytoolkit.googleapis.com/v1/accounts:sendOobCode?key=AIzaSyCfJD1P8oQJ53ul5G9H29i-4btxcF6ihc4',
            ['json' => [
                'requestType' => 'PASSWORD_RESET',
                'email' => $email
            ]]
        );
        
        $data = json_decode($response->getBody(), true);
        
        if (isset($data['email'])) {
            echo json_encode([
                'success' => true, 
                'message' => 'Se ha enviado un enlace de recuperación a tu email. Revisa tu bandeja de entrada y spam.'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al enviar el email de recuperación']);
        }
        
    } catch (Exception $e) {
        $errorBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : '';
        $errorData = json_decode($errorBody, true);
        
        if (isset($errorData['error']['message'])) {
            $errorMessage = $errorData['error']['message'];
            switch ($errorMessage) {
                case 'EMAIL_NOT_FOUND':
                    $message = 'No existe una cuenta con este email';
                    break;
                case 'INVALID_EMAIL':
                    $message = 'Email inválido';
                    break;
                default:
                    $message = 'Error al procesar la solicitud. Inténtalo de nuevo.';
            }
        } else {
            $message = 'Error de conexión. Inténtalo de nuevo.';
        }
        
        echo json_encode(['success' => false, 'message' => $message]);
    }
    exit;
}

// Procesar login normal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] === 'login')) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        header("Location: ../index.php?error=Por favor completa todos los campos");
        exit;
    }

    try {
        $client = new Client();
        $response = $client->post(
            'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key=AIzaSyCfJD1P8oQJ53ul5G9H29i-4btxcF6ihc4',
            ['json' => [
                'email' => $email,
                'password' => $password,
                'returnSecureToken' => true
            ]]
        );

        $data = json_decode($response->getBody(), true);

        $_SESSION['firebase_uid'] = $data['localId'];
        $_SESSION['firebase_email'] = $data['email'];

        // Buscar o registrar usuario por UID
        $stmt = $pdo->prepare("SELECT rol FROM usuarios WHERE firebase_uid = ?");
        $stmt->execute([$data['localId']]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            $stmt = $pdo->prepare("INSERT INTO usuarios (firebase_uid, email, rol) VALUES (?, ?, 'usuario')");
            $stmt->execute([$data['localId'], $data['email']]);
            $rol = 'usuario';
        } else {
            $rol = $usuario['rol'];
        }

        $_SESSION['rol'] = $rol;

        if ($rol === 'admin') {
            header('Location: ../admin/index.php');
        } else {
            header('Location: ../productos/index.php');
        }
        exit;

    } catch (Exception $e) {
        $errorMessage = 'Credenciales incorrectas';
        
        if ($e->getResponse()) {
            $errorBody = $e->getResponse()->getBody()->getContents();
            $errorData = json_decode($errorBody, true);
            
            if (isset($errorData['error']['message'])) {
                switch ($errorData['error']['message']) {
                    case 'EMAIL_NOT_FOUND':
                        $errorMessage = 'No existe una cuenta con este email';
                        break;
                    case 'INVALID_PASSWORD':
                        $errorMessage = 'Contraseña incorrecta';
                        break;
                    case 'USER_DISABLED':
                        $errorMessage = 'Esta cuenta ha sido deshabilitada';
                        break;
                    case 'TOO_MANY_ATTEMPTS_TRY_LATER':
                        $errorMessage = 'Demasiados intentos fallidos. Inténtalo más tarde';
                        break;
                }
            }
        }
        
        header("Location: ../index.php?error=" . urlencode($errorMessage));
        exit;
    }
}

// Si no es POST, redirigir al index
header("Location: ../index.php");
exit;
?>