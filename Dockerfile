# ============================================================
# Sistema POS Pro — imagen AUTOCONTENIDA para la demo embebida.
# Un solo contenedor = PHP 8.2 + Apache (la app) + MariaDB (los datos),
# para correr como UN servicio en Render/Railway sin base de datos externa.
# La base se resiembra desde database.sql en cada arranque en frío: el sandbox
# público queda siempre limpio, sin datos de clientes acumulados (buena práctica
# para una demo y coherente con no exponer PII).
# Paridad dev/prod: la MISMA imagen corre en local (docker compose) y en Render.
# ============================================================
FROM php:8.2-apache

# --------------------------------------------------------------
# Extensiones PHP, módulos de Apache y el servidor MariaDB embebido.
#   - pdo_mysql, mysqli: acceso a datos (config/database.php usa PDO).
#   - mbstring (+libonig-dev): el ticket de 80mm usa mb_substr().
#   - mariadb-server / mariadb-client: base de datos dentro del propio contenedor.
#   - curl: usado por el HEALTHCHECK.
#   - mod_rewrite/headers: los exige el .htaccess y el embebido en iframe.
# --------------------------------------------------------------
RUN apt-get update && apt-get install -y --no-install-recommends \
        libonig-dev \
        curl \
        mariadb-server \
        mariadb-client \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mysqli mbstring \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# php.ini de producción: no muestra errores/paths al visitante (log_errors sigue On).
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# El .htaccess del proyecto (bloquea *.sql/*.env/*.md y /config/) requiere AllowOverride All.
# Se elimina X-Frame-Options para permitir el iframe same-origin del portafolio.
RUN { \
        echo '<Directory /var/www/html/>'; \
        echo '    AllowOverride All'; \
        echo '</Directory>'; \
        echo '<IfModule mod_headers.c>'; \
        echo '    Header always unset X-Frame-Options'; \
        echo '</IfModule>'; \
    } >> /etc/apache2/apache2.conf \
    && echo 'ServerName localhost' >> /etc/apache2/apache2.conf

# La app se conecta a la MariaDB local del contenedor con un usuario dedicado.
ENV DB_HOST=127.0.0.1 \
    DB_NAME=pos_db \
    DB_USER=pos \
    DB_PASS=pos \
    POS_BASE_PATH=/pos-demo

WORKDIR /var/www/html
COPY --chown=www-data:www-data . /var/www/html/
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
# Normaliza CRLF→LF (por si se editó en Windows) y marca ejecutable.
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

# Render enruta al puerto que anuncie el servicio (PORT). En local usamos 80.
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=45s --retries=6 \
    CMD curl -fsS "http://127.0.0.1:${PORT:-80}/index.php" >/dev/null || exit 1
CMD ["docker-entrypoint.sh"]
