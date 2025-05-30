<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db.php';

$API_KEY = 'AIzaSyBqDJCZdSAb1BSl5zru0TXvn-Pck2PtHXs';
$pregunta = $_POST['pregunta'] ?? '';
$uid = $_SESSION['firebase_uid'] ?? null;

if (!$pregunta) {
    echo json_encode(['respuesta' => '❌ Pregunta vacía.']);
    exit;
}

// 0️⃣ Si contiene un código de pedido, buscar en seguimientos
if (preg_match('/pedido_[a-z0-9]+_uid_[a-zA-Z0-9]+/', $pregunta, $matches)) {
    $codigo = $matches[0];
    $stmt = $pdo->prepare("SELECT * FROM seguimientos WHERE codigo = ?");
    $stmt->execute([$codigo]);
    $seguimiento = $stmt->fetch();

    if ($seguimiento) {
        $estado = ucfirst($seguimiento['estado']);
        $ubicacion = $seguimiento['ubicacion'];
        $fecha_envio = date('d/m/Y', strtotime($seguimiento['fecha_envio']));
        $fecha_entrega = date('d/m/Y', strtotime($seguimiento['fecha_estimada_entrega']));
        $empresa = $seguimiento['empresa_envio'];

        $mensaje = <<<HTML
<div class="seguimiento-box">
    <p>📦 <strong>Seguimiento de tu pedido:</strong></p>
    <ul>
        <li><strong>Estado:</strong> $estado</li>
        <li><strong>Ubicación actual:</strong> $ubicacion</li>
        <li><strong>Enviado el:</strong> $fecha_envio</li>
        <li><strong>Entrega estimada:</strong> $fecha_entrega</li>
        <li><strong>Empresa de envío:</strong> $empresa</li>
    </ul>
    <p><strong>¿Te sirvió el resultado?</strong></p>
    <button class="respuesta-btn" data-respuesta="si">Sí</button>
    <button class="respuesta-btn" data-respuesta="no" data-pregunta="$codigo">No</button>
    <div id="sugerenciaExtra" style="margin-top: 15px;"></div>
</div>
HTML;

        echo json_encode(['respuesta' => $mensaje]);
        exit;
    } else {
        echo json_encode(['respuesta' => '❌ No encontré información con ese número de rastreo. Asegúrate de que esté escrito correctamente.']);
        exit;
    }
}

// 1️⃣ Si parece una pregunta de rastreo pero no trae código
$frasesRastreo = ['mi pedido', 'mi envío', 'rastrear', 'seguimiento', 'cómo va', 'dónde está mi paquete'];
foreach ($frasesRastreo as $frase) {
    if (stripos($pregunta, $frase) !== false) {
        echo json_encode(['respuesta' => '📫 Para ayudarte, por favor indícame tu <strong>número de rastreo</strong> (ej: <code>pedido_xxxx_uid_yyy</code>).']);
        exit;
    }
}

// 🔤 Normalizar preguntas para mejorar interpretación semántica
$pregunta = trim($pregunta);

// Reemplazos inteligentes para unificar verbos y expresiones comunes
$reemplazos = [
    '/\btienes\b/i'      => 'tienen',
    '/\btiene\b/i'       => 'tienen',
    '/\btengo\b/i'       => 'tienen',
    '/\bdame\b/i'        => 'muéstrame',
    '/\bmuestrame\b/i'   => 'muéstrame',
    '/\bquiero ver\b/i'  => 'muéstrame',
    '/\bdeseo ver\b/i'   => 'muéstrame',
    '/\benséñame\b/i'    => 'muéstrame',
    '/\bproducto\b/i'    => 'productos',
    '/\bproductos de\b/i'=> 'productos para',
    '/\bque hay de\b/i'  => 'qué productos tienen de',
    '/\bcatalogo\b/i'    => 'productos',
    '/\bcatalógo\b/i'    => 'productos',
];

// Aplicar todos los reemplazos
foreach ($reemplazos as $patron => $reemplazo) {
    $pregunta = preg_replace($patron, $reemplazo, $pregunta);
}



// 🔍 1. Obtener todos los productos
$stmt = $pdo->query("SELECT id, nombre, descripcion, precio FROM productos");
$productos = $stmt->fetchAll();

// 🔍 2. Definir categorías con enlaces
$categorias = [
    1 => 'Ropa',
    2 => 'Electrónica',
    3 => 'Moda',
    4 => 'Alimentos',
    5 => 'Hogar',
    6 => 'Juguetes',
    7 => 'Belleza',
    8 => 'Salud',
    9 => 'Mascotas',
    10 => 'Libros',
    11 => 'Ferretería'
];

$contexto_productos = "📦 Lista de productos disponibles (con precio y categoría):\n";
foreach ($productos as $p) {
    $nombre = $p['nombre'];
    $descripcion = $p['descripcion'] ?? '';
    $precio = number_format($p['precio'], 2);
    $id = $p['id'];
    $categoria_id = $p['categoria_id'] ?? null;
    $categoria_nombre = $categorias[$categoria_id] ?? 'General';
    $link = "/productos/detalle.php?id=$id";
    $contexto_productos .= "- \"$nombre\" (Categoría: $categoria_nombre): $descripcion. Precio: \$$precio → $link\n";
}


// 🧠 4. Contexto: categorías
$contexto_categorias = "📁 Categorías disponibles:\n";
foreach ($categorias as $id => $nombre) {
    $contexto_categorias .= "- $nombre → /productos/categoria.php?id=$id\n";
}

// 📣 5. Prompt personalizado
// 4️⃣ Prompt para Gemini
$prompt = <<<TXT
Eres un asistente virtual para una tienda en línea salvadoreña. Ayuda al cliente de forma cálida, natural y útil a encontrar productos o secciones que esté buscando.

🎯 Información clave que debes conocer:

- Las **ofertas especiales** están disponibles en la página:  
  🔗 `/productos/index.php`  
  Si el cliente pregunta "¿tienen ofertas?" o "¿hay promociones?", respóndele con entusiasmo y muéstrale un botón como este:

  <a href="/productos/index.php" style="display:inline-block; background:#FF6B6B; color:white; padding:10px 16px; border-radius:12px; text-decoration:none;">🔥 Ver ofertas especiales</a>

- Los **métodos de pago** disponibles son:
  💳 Tarjeta de crédito o débito  
  ✅ Procesados de forma segura a través de la pasarela **Wompi** (una plataforma respaldada por Banco Agrícola).

  Si el cliente pregunta "¿cuáles son los métodos de pago?" o "¿puedo pagar con tarjeta?", responde de forma confiable y profesional, explicando brevemente que se puede pagar con tarjeta mediante Wompi, que es seguro y confiable. Puedes usar tus propias palabras y variar el tono o estilo según el contexto de la conversación (ej. más formal, más alegre, más directo). 

  Ejemplos válidos (usa como inspiración, no como copia exacta):

  - "Sí, aceptamos pagos con tarjeta a través de Wompi, una plataforma segura del Banco Agrícola. ¡Tus datos están protegidos!"
  - "Puedes pagar con tu tarjeta sin problema 😄, trabajamos con Wompi para garantizar transacciones seguras."
  - "Claro, todos nuestros pagos son vía Wompi. Aceptamos tarjetas de débito y crédito de forma segura."

  Recuerda: no uses una frase fija cada vez. Elige tú cómo explicarlo de manera clara, amable y confiable.  

- No respondas simplemente "no tengo esa información" si puedes redirigir al usuario a una sección adecuada del sitio.

$contexto_categorias

$contexto_productos

🎯 Instrucciones:

🔍 Analiza con cuidado la **intención del mensaje del cliente**, más allá de las palabras literales. Piensa como un humano razonable:

- ¿Está el cliente buscando una solución?
- ¿Tiene una necesidad urgente, emocional o práctica?
- ¿Está pidiendo inspiración, reparación, organización, entretenimiento, o algo más?

A partir de eso, seleccioná productos que **de verdad ayuden a resolver** la situación que plantea. Evitá recomendar artículos que no sean útiles o relacionados, aunque estén dentro de la misma categoría.

---

📚 INTENCIÓN ↔️ RESPUESTA RELEVANTE

Usá esta lógica de interpretación:

| Si el cliente dice...                     | Entendé que busca...                          |
|------------------------------------------|-----------------------------------------------|
| “se arruinó”, “no funciona”, “me falló”  | Productos para reparación o reemplazo         |
| “necesito algo para…”                    | Una herramienta, accesorio o solución concreta|
| “quiero arreglar / mejorar / decorar”    | Organización, funcionalidad o estética        |
| “cuál es el precio”, “cuánto cuesta”     | Información precisa con enlaces y montos      |
| “tienen ofertas / promociones”           | Mostrar la página de promociones              |

---

- Si el cliente pregunta por una categoría (ej: “¿Tienen cosas de ferretería?”, “¿Dónde veo los libros?”), haz lo siguiente:

  1. Elige un saludo variado y natural (ej: "¡Por supuesto! 😊", "Con gusto te muestro lo que tenemos 📚", "Aquí tienes lo que encontré 👇").
  2. Elige 3 productos relacionados con esa categoría **de forma aleatoria** para que no siempre sean los mismos.
  3. Muestra esos 3 productos como botones usando el formato:

<a href="/productos/detalle.php?id=123" style="display:inline-block; background:#ffffff; color:#1a202c; padding:16px 20px; margin:8px 6px; border-radius:16px; text-decoration:none; font-weight:600; font-size:14px; box-shadow:0 8px 25px rgba(0,0,0,0.08), 0 3px 6px rgba(0,0,0,0.05); transition:all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); border:2px solid #e2e8f0; position:relative; overflow:hidden;" onmouseover="this.style.transform='translateY(-4px) scale(1.02)'; this.style.boxShadow='0 20px 40px rgba(0,0,0,0.12), 0 8px 16px rgba(0,0,0,0.08)'; this.style.borderColor='#4299e1'; this.style.background='linear-gradient(135deg, #667eea 0%, #764ba2 100%)'; this.style.color='white';" onmouseout="this.style.transform='translateY(0) scale(1)'; this.style.boxShadow='0 8px 25px rgba(0,0,0,0.08), 0 3px 6px rgba(0,0,0,0.05)'; this.style.borderColor='#e2e8f0'; this.style.background='#ffffff'; this.style.color='#1a202c';">🛒 Nombre del producto</a>
 
4. Luego añade el mensaje: "Aquí tienes más productos relacionados en nuestra sección de [Nombre de la categoría]:"
     Y muestra un botón así:

<a href="/productos/categoria.php?id=10" style="display:inline-block; background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); color:white; padding:18px 32px; margin:12px 0; text-decoration:none; border-radius:50px; font-weight:700; font-size:16px; box-shadow:0 12px 35px rgba(102,126,234,0.4), 0 4px 8px rgba(102,126,234,0.2); transition:all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); border:none; position:relative; overflow:hidden; text-transform:uppercase; letter-spacing:0.5px;" onmouseover="this.style.transform='translateY(-6px) scale(1.05)'; this.style.boxShadow='0 25px 50px rgba(102,126,234,0.6), 0 8px 16px rgba(102,126,234,0.3)'; this.style.background='linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%)';" onmouseout="this.style.transform='translateY(0) scale(1)'; this.style.boxShadow='0 12px 35px rgba(102,126,234,0.4), 0 4px 8px rgba(102,126,234,0.2)'; this.style.background='linear-gradient(135deg, #667eea 0%, #764ba2 100%)';">📚 Ver toda la categoría</a>

- Si el cliente pregunta por productos específicos (como “camisetas del Real Madrid” o “audífonos Sony”), responde:

  "¡Genial! Aquí tienes los productos que encontré relacionados con tu búsqueda:"

  Y muestra uno o varios productos como botones usando el mismo estilo.

- No mezcles categorías y productos en una misma respuesta.

- Nunca inventes productos ni enlaces. Usa solo los del contexto que se te proporcionó.

- Si **no encuentras coincidencias**, **no digas que ya registraste la sugerencia**. En vez de eso, escribe:
  "No encontré productos relacionados. Pero si haces clic en el botón “No”, podrás dejarnos tu sugerencia para que consideremos agregar lo que buscas. 😉"

- Si el cliente pregunta por precios (ej. “¿Cuánto cuesta...?”, “¿Qué precio tiene...?”, “¿Cuáles son los precios de...?”), asegúrate de:
  1. Mencionar claramente el **precio exacto** de los productos que coincidan.
  2. Mostrar el nombre, precio y un enlace al detalle.
  3. Usar frases como:
     - "Este producto cuesta \$49.99."
     - "Aquí tienes los taladros disponibles con sus precios:"
     - "Si hay varios productos, dile que la opción más económica cuesta \$X, mientras que la más costosa cuesta \$Y."

Cliente: "$pregunta"
TXT;



// 🤖 6. Llamar a Gemini
$geminiInput = [
    "contents" => [[
        "parts" => [[ "text" => $prompt ]]
    ]]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=$API_KEY");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($geminiInput));
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
$aiText = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'No se pudo obtener respuesta.';

// ✅ 7. Mostrar resultado con botones de Sí / No
// ✅ 7. Mostrar resultado con botones de Sí / No
$encodedPregunta = htmlspecialchars($pregunta, ENT_QUOTES);
$html = <<<HTML
<div class="ai-response-content">
    <!-- Texto de la respuesta -->
    <div class="ai-text">
        <p>$aiText</p>
    </div>
    
    <!-- Productos relacionados -->
    <div class="ai-products">
    </div>
    
    <!-- Sección de feedback -->
    <div class="feedback-section">
        <p><strong>¿Te sirvió el resultado?</strong></p>
        <div class="feedback-buttons">
            <button class="respuesta-btn" data-respuesta="si">
                <span>👍</span> Sí
            </button>
            <button class="respuesta-btn" data-respuesta="no" data-pregunta="{$encodedPregunta}">
                <span>👎</span> No
            </button>
        </div>
    </div>
    <div id="sugerenciaExtra"></div>
</div>
HTML;

echo json_encode(['respuesta' => $html]);
