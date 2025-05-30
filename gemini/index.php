<?php
session_start();
// Si estás usando autenticación Firebase, asegúrate de que $_SESSION['firebase_uid'] esté definida aquí si es necesario

require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';


?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asistente Virtual - TaponShop</title>
    <link rel="stylesheet" href="/assets/css/asistente.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="asistente-app">
        <div class="page-container">
            <!-- Header Principal -->
            <header class="main-header">
                <div class="header-content">
                    <div class="assistant-info">
                        <div class="assistant-avatar">
                            <div class="avatar-icon">🤖</div>
                            <div class="status-indicator"></div>
                        </div>
                        <div class="assistant-details">
                            <h1 class="assistant-title">Asistente Virtual</h1>
                            <p class="assistant-subtitle">En línea • Responde al instante</p>
                        </div>
                    </div>
                    <div class="header-actions">
                        <button class="header-btn" onclick="limpiarChat()" title="Limpiar conversación">
                            <span class="btn-icon">🗑️</span>
                        </button>
                        <button class="header-btn" onclick="exportarChat()" title="Exportar conversación">
                            <span class="btn-icon">📥</span>
                        </button>
                        <button class="header-btn" onclick="toggleFullscreen()" title="Pantalla completa">
                            <span class="btn-icon">⛶</span>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Sección de Preguntas Frecuentes -->
            <section class="frequent-questions-section">
                <div class="section-content">
                    <h2 class="section-title">Preguntas frecuentes:</h2>
                    <div class="questions-grid">
                        <button class="question-card" onclick="enviarPregunta('¿Hay ofertas disponibles?')">
                            <div class="card-icon">🔥</div>
                            <div class="card-content">
                                <h3 class="card-title">Ofertas especiales</h3>
                                <p class="card-description">Encuentra las mejores promociones</p>
                            </div>
                        </button>
                        <button class="question-card" onclick="enviarPregunta('¿Cómo puedo rastrear mi pedido?')">
                            <div class="card-icon">📍</div>
                            <div class="card-content">
                                <h3 class="card-title">Rastrear pedido</h3>
                                <p class="card-description">Consulta el estado de tu pedido</p>
                            </div>
                        </button>
                        <button class="question-card" onclick="enviarPregunta('¿Cuáles son los métodos de pago?')">
                            <div class="card-icon">💳</div>
                            <div class="card-content">
                                <h3 class="card-title">Métodos de pago</h3>
                                <p class="card-description">Conoce todas las formas de pago</p>
                            </div>
                        </button>
                    </div>
                </div>
            </section>

            <!-- Área Principal del Chat -->
            <main class="chat-main">
                <div class="chat-wrapper">
                    <!-- Área de Conversación -->
                    <div class="conversation-container" id="conversationContainer">
                        <div class="conversation-area" id="conversationArea">
                            <!-- Mensaje de bienvenida -->
                            <div class="message assistant-message welcome-message">
                                <div class="message-avatar">🤖</div>
                                <div class="message-content">
                                    <div class="message-text">
                                        ¡Hola! 👋 Soy tu asistente virtual de TaponShop. 
                                        <br><br>
                                        Estoy aquí para ayudarte a:
                                        <ul>
                                            <li>🛍️ Encontrar productos específicos</li>
                                            <li>🔍 Explorar nuestras categorías</li>
                                            <li>📦 Consultar sobre pedidos</li>
                                            <li>💰 Información sobre precios y ofertas</li>
                                        </ul>
                                        <br>
                                        Puedes usar las opciones rápidas de arriba o escribirme directamente lo que necesitas.
                                    </div>
                                    <div class="message-time"><?= date('H:i') ?></div>
                                </div>
                            </div>
                        </div>
                        <!-- Indicador de escritura -->
                        <div class="typing-indicator" id="typingIndicator" style="display: none;">
                            <div class="message-avatar">🤖</div>
                            <div class="typing-content">
                                <div class="typing-dots">
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>
                                <span class="typing-text">El asistente está escribiendo...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            <!-- Input de Mensaje -->
            <footer class="message-input-footer">
                <div class="input-section">
                    <form id="formPregunta" class="message-form" autocomplete="off">
                        <div class="input-group">
                            <div class="input-container">
                                <input type="text" 
                                       name="pregunta" 
                                       id="inputPregunta" 
                                       placeholder="Escribe tu pregunta aquí..." 
                                       required
                                       autocomplete="off">
                                <button type="button" class="voice-btn" onclick="toggleVoiceInput()" title="Usar voz">
                                    <span class="voice-icon">🎤</span>
                                </button>
                            </div>
                            <button type="submit" class="send-btn" id="btnSend">
                                <span class="send-icon">📤</span>
                                <span class="send-text">Enviar</span>
                            </button>
                        </div>
                    </form>
                    <!-- Info del footer -->
                    <div class="footer-info">
                        <span class="info-text">💡 Tip: Puedes usar comandos de voz o escribir directamente</span>
                        <span class="info-separator">•</span>
                        <span class="info-text">🤖 Powered by AI</span>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    <script>
        let conversationHistory = [];
        let isVoiceActive = false;
        let responseCounter = 0;

        // Persistent storage management
        const STORAGE_KEY = 'gemini_chat_history';
        const SESSION_KEY = 'gemini_session_id';

        // Initialize session
        function initializeSession() {
            let sessionId = localStorage.getItem(SESSION_KEY);
            if (!sessionId) {
                sessionId = 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                localStorage.setItem(SESSION_KEY, sessionId);
            }
            return sessionId;
        }

        // Save conversation to localStorage
        function saveConversation() {
            try {
                const sessionId = initializeSession();
                const conversationData = {
                    sessionId: sessionId,
                    timestamp: new Date().toISOString(),
                    messages: conversationHistory
                };
                localStorage.setItem(STORAGE_KEY, JSON.stringify(conversationData));
            } catch (error) {
                console.warn('Could not save conversation to localStorage:', error);
            }
        }

        // Load conversation from localStorage
        function loadConversation() {
            try {
                const savedData = localStorage.getItem(STORAGE_KEY);
                if (savedData) {
                    const conversationData = JSON.parse(savedData);
                    conversationHistory = conversationData.messages || [];
                    
                    // Restore conversation in UI
                    const conversationArea = document.getElementById('conversationArea');
                    
                    // Keep welcome message and clear the rest
                    const welcomeMessage = conversationArea.querySelector('.welcome-message');
                    conversationArea.innerHTML = '';
                    if (welcomeMessage) {
                        conversationArea.appendChild(welcomeMessage);
                    }
                    
                    // Restore messages
                    conversationHistory.forEach(message => {
                        if (message.tipo === 'user') {
                            restoreUserMessage(message);
                        } else if (message.tipo === 'assistant' && message.responseId) {
                            restoreAIResponse(message);
                        }
                    });
                    
                    scrollToBottom();
                }
            } catch (error) {
                console.warn('Could not load conversation from localStorage:', error);
            }
        }

        // Restore user message
        function restoreUserMessage(message) {
            const conversationArea = document.getElementById('conversationArea');
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message user-message';
            
            messageDiv.innerHTML = `
                <div class="message-content">
                    <div class="message-text">${message.texto}</div>
                    <div class="message-time">${message.timestamp}</div>
                </div>
                <div class="message-avatar">👤</div>
            `;
            
            conversationArea.appendChild(messageDiv);
        }

        // Restore AI response
        function restoreAIResponse(message) {
            const conversationArea = document.getElementById('conversationArea');
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message assistant-message ai-response-message';
            
            messageDiv.innerHTML = `
                <div class="message-avatar">🤖</div>
                <div class="message-content">
                    <div class="message-text ai-response-content" id="${message.responseId}">
                        ${message.texto}
                    </div>
                    <div class="message-time">${message.timestamp}</div>
                </div>
            `;
            
            conversationArea.appendChild(messageDiv);
            
            // Restore event listeners for feedback buttons
            setTimeout(() => {
                const responseElement = document.getElementById(message.responseId);
                if (responseElement) {
                    responseElement.addEventListener('click', function(e) {
                        handleFeedbackClick(e, message.responseId);
                    });
                }
            }, 100);
        }

        // Enviar pregunta
        document.getElementById('formPregunta').addEventListener('submit', async function (e) {
            e.preventDefault();
            const pregunta = this.pregunta.value.trim();
            
            if (!pregunta) return;
            
            await procesarPregunta(pregunta);
            this.pregunta.value = '';
        });

        async function procesarPregunta(pregunta) {
            // Agregar mensaje del usuario
            agregarMensaje(pregunta, 'user');
            
            // Mostrar indicador de escritura
            mostrarTyping(true);

            try {
                const res = await fetch('/gemini/consulta.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'pregunta=' + encodeURIComponent(pregunta)
                });

                const data = await res.json();
                const respuesta = data.respuesta || 'Lo siento, no pude procesar tu consulta en este momento.';
                
                // Ocultar indicador de escritura
                mostrarTyping(false);
                
                // Agregar respuesta del AI como mensaje en la conversación
                agregarRespuestaAI(respuesta);
                
                // Scroll al final
                scrollToBottom();
                
            } catch (error) {
                mostrarTyping(false);
                const errorHtml = `
                    <div class="error-response">
                        <div class="error-icon">⚠️</div>
                        <div class="error-content">
                            <h4>Error de conexión</h4>
                            <p>Lo siento, ocurrió un error. Por favor, intenta nuevamente.</p>
                        </div>
                    </div>
                `;
                agregarRespuestaAI(errorHtml);
            }
        }

        function agregarMensaje(texto, tipo) {
            const conversationArea = document.getElementById('conversationArea');
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${tipo}-message`;
            
            const timestamp = new Date().toLocaleTimeString('es-ES', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
            
            if (tipo === 'user') {
                messageDiv.innerHTML = `
                    <div class="message-content">
                        <div class="message-text">${texto}</div>
                        <div class="message-time">${timestamp}</div>
                    </div>
                    <div class="message-avatar">👤</div>
                `;
            } else {
                messageDiv.innerHTML = `
                    <div class="message-avatar">🤖</div>
                    <div class="message-content">
                        <div class="message-text">${texto}</div>
                        <div class="message-time">${timestamp}</div>
                    </div>
                `;
            }
            
            conversationArea.appendChild(messageDiv);
            scrollToBottom();
            
            // Agregar al historial
            const messageData = { texto, tipo, timestamp };
            conversationHistory.push(messageData);
            saveConversation();
        }

        function agregarRespuestaAI(respuestaHtml) {
            const conversationArea = document.getElementById('conversationArea');
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message assistant-message ai-response-message';
            
            const timestamp = new Date().toLocaleTimeString('es-ES', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
            
            // Crear un ID único para esta respuesta
            responseCounter++;
            const responseId = 'response_' + Date.now() + '_' + responseCounter;
            
            messageDiv.innerHTML = `
                <div class="message-avatar">🤖</div>
                <div class="message-content">
                    <div class="message-text ai-response-content" id="${responseId}">
                        ${respuestaHtml}
                    </div>
                    <div class="message-time">${timestamp}</div>
                </div>
            `;
            
            conversationArea.appendChild(messageDiv);
            scrollToBottom();
            
            // Agregar event listeners para los botones de feedback de esta respuesta específica
            setTimeout(() => {
                const responseElement = document.getElementById(responseId);
                if (responseElement) {
                    responseElement.addEventListener('click', function(e) {
                        handleFeedbackClick(e, responseId);
                    });
                }
            }, 100);
            
            // Agregar al historial
            const messageData = { 
                texto: respuestaHtml, 
                tipo: 'assistant', 
                timestamp: timestamp,
                responseId: responseId
            };
            conversationHistory.push(messageData);
            saveConversation();
        }

        function handleFeedbackClick(e, responseId) {
            if (e.target.classList.contains('respuesta-btn')) {
                const respuesta = e.target.dataset.respuesta;
                const responseElement = document.getElementById(responseId);
                let extra = responseElement.querySelector('#sugerenciaExtra');
                
                // Si no existe el contenedor de sugerencias, crearlo
                if (!extra) {
                    extra = document.createElement('div');
                    extra.id = 'sugerenciaExtra';
                    extra.style.marginTop = '15px';
                    responseElement.appendChild(extra);
                }

                if (respuesta === 'si') {
                    extra.innerHTML = `
                        <div class="feedback-success">
                            <div class="success-icon">✅</div>
                            <div class="success-content">
                                <h4>¡Excelente!</h4>
                                <p>Nos alegra haberte ayudado. ¿Hay algo más en lo que pueda asistirte?</p>
                            </div>
                        </div>
                    `;
                    
                    // Deshabilitar botones después de responder
                    disableFeedbackButtons(responseElement);
                    
                } else {
                    const preguntaOriginal = e.target.dataset.pregunta || '';
                    extra.innerHTML = `
                        <div class="feedback-improvement">
                            <div class="improvement-header">
                                <div class="improvement-icon">😕</div>
                                <div class="improvement-content">
                                    <h4>Entiendo. ¿Qué estás buscando exactamente?</h4>
                                    <p>Ayúdanos a mejorar describiendo lo que necesitas:</p>
                                </div>
                            </div>
                            <div class="improvement-form">
                                <input type="text" class="inputSugerencia" value="${preguntaOriginal}" placeholder="Describe lo que necesitas..." />
                                <button class="enviarBtn improvement-btn">
                                    <span class="btn-icon">📩</span>
                                    <span>Enviar sugerencia</span>
                                </button>
                            </div>
                        </div>
                    `;

                    // Agregar event listener para el botón de enviar sugerencia
                    const enviarBtn = extra.querySelector('.enviarBtn');
                    const inputSugerencia = extra.querySelector('.inputSugerencia');
                    
                    enviarBtn.addEventListener('click', () => {
                        const sugerencia = inputSugerencia.value;
                        fetch('/gemini/registrar_sugerencia.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'sugerencia=' + encodeURIComponent(sugerencia)
                        })
                        .then(res => res.text())
                        .then(msg => {
                            extra.innerHTML = `
                                <div class="feedback-success">
                                    <div class="success-icon">✅</div>
                                    <div class="success-content">
                                        <h4>¡Gracias por tu feedback!</h4>
                                        <p>Hemos registrado tu sugerencia para mejorar nuestro servicio.</p>
                                    </div>
                                </div>
                            `;
                            
                            // Deshabilitar botones después de responder
                            disableFeedbackButtons(responseElement);
                        });
                    });
                }
                
                // Update conversation history with feedback
                updateMessageInHistory(responseId, responseElement.innerHTML);
            }
        }

        function disableFeedbackButtons(responseElement) {
            const buttons = responseElement.querySelectorAll('.respuesta-btn');
            buttons.forEach(btn => {
                btn.disabled = true;
                btn.style.opacity = '0.6';
                btn.style.cursor = 'not-allowed';
                btn.style.pointerEvents = 'none';
            });
        }

        function updateMessageInHistory(responseId, updatedHtml) {
            const messageIndex = conversationHistory.findIndex(msg => msg.responseId === responseId);
            if (messageIndex !== -1) {
                conversationHistory[messageIndex].texto = updatedHtml;
                saveConversation();
            }
        }

        function mostrarTyping(mostrar) {
            const indicator = document.getElementById('typingIndicator');
            indicator.style.display = mostrar ? 'flex' : 'none';
            
            if (mostrar) {
                scrollToBottom();
            }
        }

        function scrollToBottom() {
            const container = document.getElementById('conversationContainer');
            setTimeout(() => {
                container.scrollTop = container.scrollHeight;
            }, 100);
        }

        function enviarPregunta(pregunta) {
            document.getElementById('inputPregunta').value = pregunta;
            procesarPregunta(pregunta);
        }

        function limpiarChat() {
            if (confirm('¿Estás seguro de que quieres limpiar la conversación? Esta acción no se puede deshacer.')) {
                const conversationArea = document.getElementById('conversationArea');
                // Mantener solo el mensaje de bienvenida
                const welcomeMessage = conversationArea.querySelector('.welcome-message');
                conversationArea.innerHTML = '';
                if (welcomeMessage) {
                    conversationArea.appendChild(welcomeMessage);
                }
                
                // Limpiar historial y storage
                conversationHistory = [];
                localStorage.removeItem(STORAGE_KEY);
                localStorage.removeItem(SESSION_KEY);
                
                // Reinicializar sesión
                initializeSession();
            }
        }

        function exportarChat() {
            if (conversationHistory.length === 0) {
                alert('No hay conversación para exportar.');
                return;
            }
            
            const sessionId = localStorage.getItem(SESSION_KEY);
            const exportData = {
                sessionId: sessionId,
                exportDate: new Date().toISOString(),
                totalMessages: conversationHistory.length,
                conversation: conversationHistory.map(msg => ({
                    type: msg.tipo,
                    content: msg.texto.replace(/<[^>]*>/g, ''), // Remove HTML tags
                    timestamp: msg.timestamp
                }))
            };
            
            const dataStr = JSON.stringify(exportData, null, 2);
            const dataBlob = new Blob([dataStr], {type: 'application/json'});
            
            const link = document.createElement('a');
            link.href = URL.createObjectURL(dataBlob);
            link.download = `chat_export_${new Date().toISOString().split('T')[0]}.json`;
            link.click();
        }

        function toggleFullscreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen();
            } else {
                document.exitFullscreen();
            }
        }

        function toggleVoiceInput() {
            if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
                alert('Tu navegador no soporta reconocimiento de voz.');
                return;
            }

            const recognition = new (window.SpeechRecognition || window.webkitSpeechRecognition)();
            recognition.lang = 'es-ES';
            recognition.continuous = false;
            recognition.interimResults = false;

            if (!isVoiceActive) {
                recognition.start();
                isVoiceActive = true;
                document.querySelector('.voice-icon').textContent = '🔴';
                
                recognition.onresult = function(event) {
                    const transcript = event.results[0][0].transcript;
                    document.getElementById('inputPregunta').value = transcript;
                    isVoiceActive = false;
                    document.querySelector('.voice-icon').textContent = '🎤';
                };
                
                recognition.onerror = function() {
                    isVoiceActive = false;
                    document.querySelector('.voice-icon').textContent = '🎤';
                };
                
                recognition.onend = function() {
                    isVoiceActive = false;
                    document.querySelector('.voice-icon').textContent = '🎤';
                };
            }
        }

        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize session and load previous conversation
            initializeSession();
            loadConversation();
            
            document.getElementById('inputPregunta').focus();
            scrollToBottom();
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'Enter') {
                document.getElementById('formPregunta').dispatchEvent(new Event('submit'));
            }
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                exportarChat();
            }
        });

        // Auto-save conversation periodically
        setInterval(saveConversation, 30000); // Save every 30 seconds

        // Save conversation before page unload
        window.addEventListener('beforeunload', function() {
            saveConversation();
        });
    </script>
</body>


</html>

<?php require_once '../includes/footer.php'; ?>