# Usar imagen oficial de PHP con Apache
FROM php:8.2-apache

# Instalar extensiones para conectarse a MySQL
RUN docker-php-ext-install mysqli pdo_mysql

# Habilitar módulo de reescritura (útil si usas rutas amigables)
RUN a2enmod rewrite

# Copiar el código de la aplicación al directorio web de Apache
COPY ./src /var/www/html/

# Configurar permisos para que Apache pueda leer/escribir si es necesario
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Exponer el puerto 80
EXPOSE 80