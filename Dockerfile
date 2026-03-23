# =============================================================
# Dockerfile – es21plus · Sistema de Inventario para Motos
# @author Carlos Vico
# =============================================================
FROM php:apache

# Instalar extensiones PDO para MySQL
RUN docker-php-ext-install pdo_mysql

# Habilitar módulos de Apache necesarios
RUN a2enmod rewrite

# Copiar configuración de Apache (permite acceso al directorio en Docker+Windows)
COPY apache.conf /etc/apache2/sites-available/000-default.conf

# Copiar configuración PHP personalizada
COPY php.ini /usr/local/etc/php/conf.d/custom.ini

# Copiar el código fuente
COPY ./src /var/www/html/

# Permisos correctos para www-data
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80