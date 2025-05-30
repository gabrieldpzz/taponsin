<?php
require_once '../vendor/autoload.php';
require_once '../includes/db.php';
session_start();

use GuzzleHttp\Client;

$mensaje = "";
$tipo_mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $es_proveedor = isset($_POST['quiero_ser_proveedor']);

    if (!$email || !$password || !$confirm_password) {
        $mensaje = "Todos los campos son obligatorios.";
        $tipo_mensaje = "error";
    } elseif ($password !== $confirm_password) {
        $mensaje = "Las contraseñas no coinciden.";
        $tipo_mensaje = "error";
    } elseif (strlen($password) < 6) {
        $mensaje = "La contraseña debe tener al menos 6 caracteres.";
        $tipo_mensaje = "error";
    } else {
        try {
            $client = new Client();
            $response = $client->post('https://identitytoolkit.googleapis.com/v1/accounts:signUp?key=AIzaSyCfJD1P8oQJ53ul5G9H29i-4btxcF6ihc4', [
                'json' => [
                    'email' => $email,
                    'password' => $password,
                    'returnSecureToken' => true
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            $_SESSION['firebase_uid'] = $data['localId'];
            $_SESSION['firebase_email'] = $data['email'];

            // Guardar en DB
            $stmt = $pdo->prepare("INSERT INTO usuarios (firebase_uid, email, rol) VALUES (?, ?, 'usuario')");
            $stmt->execute([$data['localId'], $data['email']]);
            $_SESSION['rol'] = 'usuario';

            // Redirigir
            if ($es_proveedor) {
                header('Location: /proveedor/registro_proveedor.php');
            } else {
                header('Location: ../productos/index.php');
            }
            exit;

        } catch (Exception $e) {
            $mensaje = "No se pudo registrar. El correo electrónico puede estar ya en uso.";
            $tipo_mensaje = "error";
        }
    }
}
?>

<link rel="stylesheet" href="/assets/css/auth.css">

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-icon">👤</div>
            <h2 class="auth-title">Crear cuenta</h2>
            <p class="auth-subtitle">Únete a nuestra tienda y disfruta de una experiencia de compra única</p>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipo_mensaje ?>">
                <span class="alert-icon">
                    <?= $tipo_mensaje === 'error' ? '⚠️' : '✅' ?>
                </span>
                <span class="alert-message"><?= htmlspecialchars($mensaje) ?></span>
            </div>
        <?php endif; ?>

        <form method="post" class="auth-form" id="registerForm">
            <div class="form-group">
                <label for="email" class="form-label">Correo electrónico</label>
                <div class="input-container">
                    <span class="input-icon">📧</span>
                    <input type="email" 
                           name="email" 
                           id="email" 
                           class="form-input" 
                           placeholder="tu@email.com" 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           required>
                </div>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Contraseña</label>
                <div class="input-container">
                    <span class="input-icon">🔒</span>
                    <input type="password" 
                           name="password" 
                           id="password" 
                           class="form-input" 
                           placeholder="Mínimo 6 caracteres" 
                           required>
                    <button type="button" class="toggle-password" onclick="togglePassword('password')">
                        <span id="password-toggle-icon">👁️</span>
                    </button>
                </div>
                <div class="password-strength" id="passwordStrength"></div>
            </div>

            <div class="form-group">
                <label for="confirm_password" class="form-label">Confirmar contraseña</label>
                <div class="input-container">
                    <span class="input-icon">🔒</span>
                    <input type="password" 
                           name="confirm_password" 
                           id="confirm_password" 
                           class="form-input" 
                           placeholder="Repite tu contraseña" 
                           required>
                    <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                        <span id="confirm_password-toggle-icon">👁️</span>
                    </button>
                </div>
                <div class="password-match" id="passwordMatch"></div>
            </div>

            <div class="form-group">
    <label>
        <input type="checkbox" name="quiero_ser_proveedor" value="1">
        Quiero registrarme como proveedor
    </label>
</div>



            <button type="submit" class="btn btn-primary btn-large" id="submitBtn">
                <span class="btn-icon">✨</span>
                <span class="btn-text">Crear mi cuenta</span>
            </button>
        </form>

        <div class="auth-footer">
            <p class="auth-switch">
                ¿Ya tienes una cuenta? 
                <a href="../index.php" class="link">Inicia sesión aquí</a>
            </p>
        </div>

        <div class="auth-benefits">
            <h3 class="benefits-title">¿Por qué registrarte?</h3>
            <div class="benefits-list">
                <div class="benefit-item">
                    <span class="benefit-icon">🚚</span>
                    <span class="benefit-text">Envío gratis en tu primera compra</span>
                </div>
                <div class="benefit-item">
                    <span class="benefit-icon">📦</span>
                    <span class="benefit-text">Seguimiento de pedidos en tiempo real</span>
                </div>
                <div class="benefit-item">
                    <span class="benefit-icon">💰</span>
                    <span class="benefit-text">Ofertas exclusivas para miembros</span>
                </div>
                <div class="benefit-item">
                    <span class="benefit-icon">🎁</span>
                    <span class="benefit-text">Programa de puntos y recompensas</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Validación de fortaleza de contraseña
function checkPasswordStrength(password) {
    const strengthIndicator = document.getElementById('passwordStrength');
    let strength = 0;
    let feedback = [];

    if (password.length >= 6) strength++;
    else feedback.push('Al menos 6 caracteres');

    if (/[a-z]/.test(password)) strength++;
    else feedback.push('Una letra minúscula');

    if (/[A-Z]/.test(password)) strength++;
    else feedback.push('Una letra mayúscula');

    if (/[0-9]/.test(password)) strength++;
    else feedback.push('Un número');

    if (/[^A-Za-z0-9]/.test(password)) strength++;
    else feedback.push('Un carácter especial');

    const levels = ['Muy débil', 'Débil', 'Regular', 'Buena', 'Muy fuerte'];
    const colors = ['#FF8C94', '#FF8C94', '#FFF3B0', '#AED9E0', '#A8E6CF'];
    
    if (password.length > 0) {
        strengthIndicator.innerHTML = `
            <div class="strength-bar">
                <div class="strength-fill" style="width: ${(strength/5)*100}%; background-color: ${colors[strength-1] || colors[0]}"></div>
            </div>
            <span class="strength-text" style="color: ${colors[strength-1] || colors[0]}">${levels[strength-1] || levels[0]}</span>
        `;
        strengthIndicator.style.display = 'block';
    } else {
        strengthIndicator.style.display = 'none';
    }
}

// Validación de coincidencia de contraseñas
function checkPasswordMatch() {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const matchIndicator = document.getElementById('passwordMatch');

    if (confirmPassword.length > 0) {
        if (password === confirmPassword) {
            matchIndicator.innerHTML = '<span style="color: #A8E6CF;">✅ Las contraseñas coinciden</span>';
        } else {
            matchIndicator.innerHTML = '<span style="color: #FF8C94;">❌ Las contraseñas no coinciden</span>';
        }
        matchIndicator.style.display = 'block';
    } else {
        matchIndicator.style.display = 'none';
    }
}

// Toggle visibilidad de contraseña
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '-toggle-icon');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.textContent = '🙈';
    } else {
        field.type = 'password';
        icon.textContent = '👁️';
    }
}

// Event listeners
document.getElementById('password').addEventListener('input', function() {
    checkPasswordStrength(this.value);
    checkPasswordMatch();
});

document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);

// Validación del formulario
document.getElementById('registerForm').addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const terms = document.getElementById('terms').checked;
    
    if (password !== confirmPassword) {
        e.preventDefault();
        alert('Las contraseñas no coinciden.');
        return;
    }
    
    if (password.length < 6) {
        e.preventDefault();
        alert('La contraseña debe tener al menos 6 caracteres.');
        return;
    }
    
    if (!terms) {
        e.preventDefault();
        alert('Debes aceptar los términos y condiciones.');
        return;
    }
    
    // Mostrar estado de carga
    const submitBtn = document.getElementById('submitBtn');
    const btnText = submitBtn.querySelector('.btn-text');
    const btnIcon = submitBtn.querySelector('.btn-icon');
    
    btnText.textContent = 'Creando cuenta...';
    btnIcon.textContent = '⏳';
    submitBtn.disabled = true;
});

// Animación de entrada
document.addEventListener('DOMContentLoaded', function() {
    const card = document.querySelector('.auth-card');
    card.style.opacity = '0';
    card.style.transform = 'translateY(20px)';
    
    setTimeout(() => {
        card.style.transition = 'all 0.6s ease-out';
        card.style.opacity = '1';
        card.style.transform = 'translateY(0)';
    }, 100);
});
</script>