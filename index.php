<?php
session_start();
if (isset($_SESSION['firebase_uid'])) {
    header("Location: productos/index.php");
    exit;
}

require_once 'vendor/autoload.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
<link rel="icon" type="image/png" sizes="32x32" href="/assets/img/logo.png">
<link rel="icon" type="image/png" sizes="192x192" href="/assets/img/logo.png">
<link rel="apple-touch-icon" href="/assets/img/logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - TaponShop</title>
    <link rel="stylesheet" href="assets/css/index.css?v=2">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h2>🔐 Iniciar Sesión</h2>
                <p class="login-subtitle">Accede a tu cuenta</p>
            </div>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="error-message">
                    <p>❌ <?= htmlspecialchars($_GET['error']) ?></p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['success'])): ?>
                <div class="success-message">
                    <p>✅ <?= htmlspecialchars($_GET['success']) ?></p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="firebase/login.php" class="login-form" id="loginForm">
                <div class="form-group">
                    <label for="email">📧 Correo electrónico</label>
                    <input type="email" id="email" name="email" placeholder="tu@email.com" required>
                </div>
                
                <div class="form-group">
                    <label for="password">🔒 Contraseña</label>
                    <input type="password" id="password" name="password" placeholder="Tu contraseña" required>
                </div>
                
                <button type="submit" class="btn-primary" id="loginBtn">
                    Iniciar Sesión
                </button>

                <button type="button" class="btn-secondary" onclick="abrirModalReset()">
                    🔄 ¿Olvidaste tu contraseña?
                </button>

                

            </form>

            <div class="login-footer">
                <p>¿No tienes cuenta? <a href="firebase/registro.php" class="link-register">Registrarse</a></p>
            </div>
        </div>
    </div>

    <!-- Modal para reset de contraseña -->
    <div id="modalReset" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">🔄 Recuperar Contraseña</h3>
                <button class="close" onclick="cerrarModalReset()">&times;</button>
            </div>
            <div class="modal-body">
                <p class="modal-description">
                    Ingresa tu email y te enviaremos un enlace para restablecer tu contraseña.
                </p>
                <form id="resetForm">
                    <div class="form-group">
                        <label for="resetEmail">📧 Email</label>
                        <input type="email" id="resetEmail" name="email" placeholder="tu@email.com" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="cerrarModalReset()">
                    Cancelar
                </button>
                <button type="button" class="btn-reset" onclick="enviarReset()" id="resetBtn">
                    Enviar Enlace
                </button>
            </div>
        </div>
    </div>

    <script>
        // Variables globales
        let modalReset = document.getElementById('modalReset');
        let resetForm = document.getElementById('resetForm');

        // Funciones del modal
        function abrirModalReset() {
            // Pre-llenar con el email del formulario de login si existe
            const loginEmail = document.getElementById('email').value;
            if (loginEmail) {
                document.getElementById('resetEmail').value = loginEmail;
            }
            modalReset.style.display = 'block';
            document.getElementById('resetEmail').focus();
        }

        function cerrarModalReset() {
            modalReset.style.display = 'none';
            resetForm.reset();
        }

        function enviarReset() {
            const email = document.getElementById('resetEmail').value.trim();
            
            if (!email) {
                mostrarNotificacion('Por favor ingresa tu email', 'error');
                return;
            }

            if (!validarEmail(email)) {
                mostrarNotificacion('Por favor ingresa un email válido', 'error');
                return;
            }

            const resetBtn = document.getElementById('resetBtn');
            resetBtn.disabled = true;
            resetBtn.innerHTML = '<span class="spinner"></span>Enviando...';

            const formData = new FormData();
            formData.append('action', 'reset_password');
            formData.append('email', email);

            fetch('firebase/login.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarNotificacion(data.message, 'success');
                    cerrarModalReset();
                } else {
                    mostrarNotificacion(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarNotificacion('Error de conexión. Inténtalo de nuevo.', 'error');
            })
            .finally(() => {
                resetBtn.disabled = false;
                resetBtn.textContent = 'Enviar Enlace';
            });
        }

        function validarEmail(email) {
            const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        }

        function mostrarNotificacion(mensaje, tipo) {
            // Remover notificaciones existentes
            document.querySelectorAll('.notification').forEach(n => n.remove());
            
            const notificacion = document.createElement('div');
            notificacion.className = `notification ${tipo}`;
            notificacion.textContent = mensaje;
            
            document.body.appendChild(notificacion);
            
            setTimeout(() => {
                notificacion.remove();
            }, 5000);
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Manejar envío del formulario de login
            document.getElementById('loginForm').addEventListener('submit', function(e) {
                const loginBtn = document.getElementById('loginBtn');
                loginBtn.disabled = true;
                loginBtn.innerHTML = '<span class="spinner"></span>Iniciando sesión...';
            });

            // Cerrar modal con ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    cerrarModalReset();
                }
            });

            // Cerrar modal al hacer clic fuera
            window.onclick = function(event) {
                if (event.target === modalReset) {
                    cerrarModalReset();
                }
            }

            // Envío con Enter en el modal
            document.getElementById('resetEmail').addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    enviarReset();
                }
            });
        });
    </script>
</body>
</html>
