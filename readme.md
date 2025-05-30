# 🛍️ TaponShop - Tienda en Línea

**TaponShop** es una tienda en línea moderna y funcional desarrollada con PHP, MySQL y Firebase Authentication. Ofrece una experiencia completa de compra para clientes y herramientas de gestión eficientes para administradores.

---

## 🚀 Funcionalidades principales

### 🛒 Para los Clientes

- Registro e inicio de sesión con **Firebase Authentication**
- Catálogo de productos filtrado por categoría
- Carrito de compras con variantes (color, talla, etc.)
- Aplicación de cupones de descuento
- Métodos de entrega: **domicilio** o **recoger en sucursal**
- Pagos integrados con **Wompi El Salvador**
- Historial de pedidos y seguimiento de envíos
- Visualización del estado del pedido y copia de código de rastreo

### 🔐 Para los Administradores

- Gestión de productos (crear, editar, eliminar)
- Visualización y control de pedidos y sus estados
- Administración de cupones, usuarios y sucursales
- Generación de reportes gráficos de ventas y comportamiento
- Panel intuitivo y responsive con filtros dinámicos
- Edición de estado de envío y generación de seguimientos mediante API propia

---

## ⚙️ Tecnologías utilizadas

| Componente       | Tecnología                         |
|------------------|-------------------------------------|
| Backend          | PHP 8 + MySQL                      |
| Autenticación    | Firebase Authentication            |
| Base de datos    | MySQL                              |
| Frontend         | HTML, CSS, JavaScript, jQuery      |
| Estilos UI       | Paleta de colores pastel personalizada |
| Pagos            | Wompi (pasarela para El Salvador)  |
| Tracking API     | Node.js + Express (seguimiento de pedidos) |
| Gráficos         | Chart.js + jsPDF + html2canvas      |

---

## 📦 Estructura del proyecto

/admin → Panel de administración
/carrito → Carrito, historial, detalle de pedido
/productos → Catálogo y vista por categorías
/includes → Conexiones, headers, footers
/assets/css → Archivos CSS organizados por módulo
/tracking.js → API Node.js para seguimiento de envíos
/firebase → Scripts de autenticación y logout

yaml
Copiar
Editar

---

## 📈 Reportes inteligentes

Incluye gráficos avanzados:

- **Ventas** por día, semana, mes o año
- **Productos más vendidos**
- **Promedio por pedido** por categoría
- **Análisis de ventas** por categoría
- **Exportación de gráficos a PDF**

---

## 🧪 Cómo probar la app

1. **Clona el repositorio**
2. Configura tu archivo `.env` o `db.php` con acceso a MySQL
3. Configura tu Firebase Web App e integra las claves en tu frontend
4. Instala y ejecuta el servicio de tracking:
   ```bash
   cd tracking.js
   npm install
   node tracking.js
   ```
5. ¡Listo! Ya puedes navegar en [http://localhost](http://localhost)

---

## 🔐 Seguridad

- Autenticación 100% gestionada con **Firebase**
- Rutas protegidas por sesión de usuario y roles
- Código estructurado para evitar inyecciones SQL (PDO preparado)

---

## ✨ Contribuciones

Este proyecto fue desarrollado como parte de un ejercicio académico, pero su arquitectura permite escalarlo fácilmente.

**¡Pull requests y sugerencias son bienvenidos!**

---

## 📅 Fecha del último despliegue

**23 de mayo de 2025**

---

## 👤 Desarrollado por

**Taponsito 🧠**  
Contacto: [taponsitoaeiouu@gmail.com](mailto:taponsitoaeiouu@gmail.com)