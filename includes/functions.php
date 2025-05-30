<?php
function limpiarTexto($txt) {
    return htmlspecialchars(trim($txt), ENT_QUOTES, 'UTF-8');
}

function formatearPrecio($valor) {
    return "$" . number_format($valor, 2);
}
?>
