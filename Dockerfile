# =============================================================
# Dockerfile – es21plus · Sistema de Inventario para Motos
# @author Carlos Vico
# =============================================================
FROM php:apache

# Instalar extensiones PDO para MySQL
RUN docker-php-ext-install pdo_mysql

# Instalar unzip (requerido por Composer para descargar paquetes)
RUN apt-get update && apt-get install -y --no-install-recommends unzip && rm -rf /var/lib/apt/lists/*

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Habilitar módulos de Apache necesarios
RUN a2enmod rewrite

# Copiar configuración de Apache (permite acceso al directorio en Docker+Windows)
COPY apache.conf /etc/apache2/sites-available/000-default.conf

# Copiar configuración PHP personalizada
COPY php.ini /usr/local/etc/php/conf.d/custom.ini

# Instalar paquetes sin autoloader (classmap apunta a src/ que aún no existe en /var/www/html/)
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-autoloader --no-interaction

# Copiar el código fuente
COPY ./src /var/www/html/

# Generar autoloader ahora que includes/ existe; corregir path del classmap
RUN sed -i 's|src/includes/|includes/|g' composer.json \
    && composer dump-autoload --no-dev --optimize --no-interaction

# Permisos correctos para www-data
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80