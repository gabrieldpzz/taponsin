<?php
session_start();
if (!isset($_SESSION['firebase_uid']) || $_SESSION['rol'] !== 'admin') {
    header("Location: /index.php");
    exit;
}

require_once '../includes/db.php';
require_once '../includes/header.php';
?>

<link rel="stylesheet" href="/assets/css/pedidos.css">

<div class="admin-container">
    <div class="page-header">
        <h2>Gestión de Pedidos</h2>
        <p class="page-subtitle">Administra y supervisa todos los pedidos de la tienda</p>
    </div>

    <div class="filters-card">
        <form style="margin-bottom: 15px;">
            <div class="search-section">
                <div class="search-group">
                    <label for="buscar">Buscar pedidos</label>
                    <input type="text" id="buscar" placeholder="Buscar por identificador, email o estado" class="search-input">
                </div>
                
                <div class="filter-group">
                    <label for="filtro_estado_envio">Estado de envío</label>
                    <select id="filtro_estado_envio" class="filter-select">
                        <option value="">Todos los estados</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="enviado">Enviado</option>
                        <option value="entregado">Entregado</option>
                        <option value="cancelado">Cancelado</option>
                    </select>
                </div>
            </div>
        </form>
    </div>

    <div class="results-card">
        <div id="resultado-pedidos">
            <!-- La tabla de pedidos se carga aquí automáticamente -->
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function(){
    function cargarPedidos(query = '', estado = '') {
        $.get("buscar_pedidos.php", { q: query, estado: estado }, function(data){
            $('#resultado-pedidos').html(data);
        });
    }

    $('#buscar').on('input', function() {
        const valor = $(this).val();
        const estado = $('#filtro_estado_envio').val();
        cargarPedidos(valor, estado);
    });

    $('#filtro_estado_envio').on('change', function() {
        const query = $('#buscar').val();
        const estado = $(this).val();
        cargarPedidos(query, estado);
    });

    cargarPedidos(); // carga inicial
});
</script>

<?php require_once '../includes/footer.php'; ?>
