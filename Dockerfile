FROM php:8.2-apache

# Instalar dependencias para PostgreSQL
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Copiar el contenido de la carpeta 'login' al servidor
COPY login/ /var/www/html/

# Configurar permisos
RUN chown -R www-data:www-data /var/www/html/

# Exponer el puerto 80
EXPOSE 80
