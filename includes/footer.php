</main>

    <footer class="main-footer">
        <div class="footer-container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4 class="footer-title">
                        <span class="footer-icon">🛒</span>
                        TaponShop
                    </h4>
                    <p class="footer-description">
                        Tu tienda online de confianza con los mejores productos y precios.
                    </p>
                </div>
                
                <div class="footer-section">
                    <h5 class="section-title">Categorías</h5>
                    <ul class="footer-links">
                        <li><a href="/productos/categoria.php?id=1">Ropa</a></li>
                        <li><a href="/productos/categoria.php?id=2">Electrónica</a></li>
                        <li><a href="/productos/categoria.php?id=3">Moda</a></li>
                        <li><a href="/productos/categoria.php?id=4">Alimentos</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h5 class="section-title">Mi Cuenta</h5>
                    <ul class="footer-links">
                        <li><a href="/carrito/historial.php">Mis Compras</a></li>
                        <li><a href="/carrito/index.php">Carrito</a></li>
                        <li><a href="/tracking.php">Rastrear Envío</a></li>
                        <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                            <li><a href="/admin/index.php">Panel Admin</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h5 class="section-title">Contacto</h5>
                    <div class="contact-info">
                        <p class="contact-item">
                            <span class="contact-icon">📧</span>
                            info@taponshop.com
                        </p>
                        <p class="contact-item">
                            <span class="contact-icon">📞</span>
                            +503 1-800-Taponsito
                        </p>
                        <p class="contact-item">
                            <span class="contact-icon">📍</span>
                            San Salvador, El Salvador
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p class="copyright">
                    © <?= date('Y') ?> TaponShop. Todos los derechos reservados.
                </p>
                <div class="footer-meta">
                    <span class="build-info">v1.0.0</span>
                    <span class="separator">•</span>
                    <span class="last-update">Actualizado: <?= date('d/m/Y') ?></span>
                </div>
            </div>
        </div>
    </footer>

    <style>
    .main-footer {
        background: #333446;
        color: #b8cfce;
        margin-top: 40px;
    }

    .footer-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 40px 20px 20px;
    }

    .footer-content {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 32px;
        margin-bottom: 32px;
    }

    .footer-section {
        display: flex;
        flex-direction: column;
    }

    .footer-title {
        color: white;
        font-size: 20px;
        font-weight: 600;
        margin: 0 0 12px 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .footer-icon {
        font-size: 24px;
    }

    .footer-description {
        color: #b8cfce;
        font-size: 14px;
        line-height: 1.6;
        margin: 0;
    }

    .section-title {
        color: white;
        font-size: 16px;
        font-weight: 600;
        margin: 0 0 16px 0;
    }

    .footer-links {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .footer-links li {
        margin-bottom: 8px;
    }

    .footer-links a {
        color: #b8cfce;
        text-decoration: none;
        font-size: 14px;
        transition: color 0.3s ease;
    }

    .footer-links a:hover {
        color: #aed9e0;
    }

    .contact-info {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .contact-item {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #b8cfce;
        font-size: 14px;
        margin: 0;
    }

    .contact-icon {
        font-size: 16px;
    }

    .footer-bottom {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 20px;
        border-top: 1px solid #4a5568;
        flex-wrap: wrap;
        gap: 16px;
    }

    .copyright {
        color: #b8cfce;
        font-size: 14px;
        margin: 0;
    }

    .footer-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #7f8caa;
        font-size: 12px;
    }

    .separator {
        opacity: 0.5;
    }

    @media (max-width: 768px) {
        .footer-container {
            padding: 30px 15px 15px;
        }

        .footer-content {
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
        }

        .footer-bottom {
            flex-direction: column;
            text-align: center;
            gap: 12px;
        }
    }

    @media (max-width: 480px) {
        .footer-content {
            grid-template-columns: 1fr;
        }
    }
    </style>
</body>
</html>
