#!/bin/sh
# ============================================================
# Arranque del contenedor autocontenido de la demo POS.
# Un solo contenedor = Apache+PHP (la app) + MariaDB (los datos), pensado para
# correr como UN servicio en hosts de contenedores (Render, Railway, etc.) sin
# depender de una base de datos externa gestionada.
#
# Es una DEMO: la base vive en el disco efímero del contenedor y se resiembra
# desde database.sql en cada arranque en frío. Eso es deseable aquí — el sandbox
# público queda siempre limpio, sin datos de clientes acumulados.
# ============================================================
set -e

PORT="${PORT:-80}"          # Render inyecta PORT; en local usamos 80
DATADIR=/var/lib/mysql

# --- Apache escucha en $PORT (Render enruta al puerto que anuncie el servicio) ---
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# --- Inicializa el datadir de MariaDB la primera vez ---
if [ ! -d "$DATADIR/mysql" ]; then
    echo "[entrypoint] Inicializando MariaDB..."
    mariadb-install-db --user=mysql --datadir="$DATADIR" \
        --auth-root-authentication-method=normal >/dev/null 2>&1
fi
chown -R mysql:mysql "$DATADIR"

# --- Arranca MariaDB en segundo plano y espera a que responda ---
mysqld_safe --datadir="$DATADIR" --skip-networking=0 --bind-address=127.0.0.1 &
echo "[entrypoint] Esperando a MariaDB..."
for i in $(seq 1 60); do
    if mysqladmin ping --silent >/dev/null 2>&1; then break; fi
    sleep 1
done

# --- Crea BD + usuario de la app y siembra database.sql si aún no hay tablas ---
mysql <<'SQL'
CREATE DATABASE IF NOT EXISTS pos_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'pos'@'127.0.0.1' IDENTIFIED BY 'pos';
CREATE USER IF NOT EXISTS 'pos'@'localhost' IDENTIFIED BY 'pos';
GRANT ALL PRIVILEGES ON pos_db.* TO 'pos'@'127.0.0.1';
GRANT ALL PRIVILEGES ON pos_db.* TO 'pos'@'localhost';
FLUSH PRIVILEGES;
SQL

if ! mysql pos_db -e "SELECT 1 FROM usuarios LIMIT 1" >/dev/null 2>&1; then
    echo "[entrypoint] Sembrando database.sql..."
    # --default-character-set=utf8mb4: sin esto MariaDB interpreta el .sql (UTF-8)
    # como latin1 y los acentos quedan doble-codificados ("CÃ©sar" en vez de "César").
    mysql --default-character-set=utf8mb4 pos_db < /var/www/html/database.sql
    # Datos de demostración para que el dashboard NO se vea vacío (ventas, facturas).
    if [ -f /var/www/html/demo-seed.sql ]; then
        echo "[entrypoint] Aplicando datos de demostración..."
        mysql --default-character-set=utf8mb4 pos_db < /var/www/html/demo-seed.sql || true
    fi
fi

echo "[entrypoint] Levantando Apache en el puerto ${PORT}"
exec apache2-foreground
