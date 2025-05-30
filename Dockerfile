FROM php:8.2-apache

# Copiar código al contenedor
COPY . /var/www/html/

# Instalar extensiones PHP y herramientas necesarias para Composer
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    tzdata \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql gd

# Configurar zona horaria
RUN ln -fs /usr/share/zoneinfo/America/El_Salvador /etc/localtime && \
    dpkg-reconfigure -f noninteractive tzdata

# Habilitar mod_rewrite de Apache
RUN a2enmod rewrite

# Exponer puerto 80
EXPOSE 80
