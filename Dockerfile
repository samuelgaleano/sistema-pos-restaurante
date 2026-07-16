# ============================================================
# Sistema POS Pro — imagen para la demo embebida (iframe same-origin)
# PHP 8.2 + Apache, docroot = raíz del proyecto (se sirve en "/").
# ============================================================
FROM php:8.2-apache

# --------------------------------------------------------------
# Extensiones PHP y módulos de Apache
#   - pdo_mysql, mysqli: conexión a MySQL (config/database.php usa PDO; mysqli no lo usa
#     el código hoy, pero se instala porque el enunciado del proyecto lo exige explícitamente).
#   - mbstring: modules/cajero/imprimir.php usa mb_substr() para truncar nombres de producto
#     en el ticket de 80mm; sin esta extensión esa pantalla daría error fatal.
#   - libonig-dev: dependencia de compilación de mbstring (se deja instalada, no solo de build,
#     para no arriesgar romper el enlazado en runtime al purgarla).
#   - curl: lo usa el HEALTHCHECK de este servicio en docker-compose.yml.
#   - mod_rewrite: el .htaccess del proyecto lo necesita (bloquea acceso directo a /config/).
#   - mod_headers: necesario para poder quitar cabeceras (ver X-Frame-Options más abajo).
# --------------------------------------------------------------
RUN apt-get update && apt-get install -y --no-install-recommends \
        libonig-dev \
        curl \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mysqli mbstring \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# php.ini de producción: no muestra errores/paths al visitante de la demo pública.
# Los errores se siguen registrando (log_errors=On) y quedan visibles con
# `docker compose logs web`.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# --------------------------------------------------------------
# La app se apoya en el .htaccess del proyecto (bloquea *.sql/*.md/*.env/*.log,
# bloquea /config/ por completo, y desactiva el listado de directorios). La imagen
# base trae "AllowOverride None" para /var/www/, lo que haría que ese .htaccess se
# ignorase por completo dentro del contenedor. Lo habilitamos explícitamente para
# que el proyecto se comporte igual que en el hosting cPanel para el que fue escrito.
#
# También quitamos explícitamente X-Frame-Options de toda respuesta: ni la app ni su
# .htaccess la fijan hoy, pero se elimina en profundidad para garantizar que el
# sistema pueda embeberse en un <iframe> same-origin desde el portafolio. No se agrega
# Content-Security-Policy: la UI usa atributos onclick="" y <script> inline, así que un
# CSP con restricciones de script rompería la app sin necesidad real para esta demo.
# --------------------------------------------------------------
RUN { \
        echo '<Directory /var/www/html/>'; \
        echo '    AllowOverride All'; \
        echo '</Directory>'; \
        echo ''; \
        echo '<IfModule mod_headers.c>'; \
        echo '    Header always unset X-Frame-Options'; \
        echo '</IfModule>'; \
    } >> /etc/apache2/apache2.conf \
    && echo 'ServerName localhost' >> /etc/apache2/apache2.conf

WORKDIR /var/www/html

# Copiamos la app con el docroot en la raíz del proyecto (index.php, dashboard.php,
# modules/, ajax/, assets/, includes/, config/...) y con dueño www-data desde ya
# (evita una capa extra de `chown -R`). El .dockerignore se encarga de que .git,
# install.php, database.sql y demás archivos que no deben viajar a la imagen queden
# fuera del contexto de build.
COPY --chown=www-data:www-data . /var/www/html/

EXPOSE 80
