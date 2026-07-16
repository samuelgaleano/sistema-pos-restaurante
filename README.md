# 🛒 Sistema POS Profesional — PHP & MySQL

Sistema de punto de venta completo con facturación, control de inventario, gestión de mesas, roles de usuario (Administrador / Cajero / Mesero) y dashboard administrativo con control de ventas, turnos y cierre de caja.

Diseñado para funcionar en hosting compartido con **cPanel** (PHP 7.4+ / MySQL o MariaDB) y para desplegarse **en línea** como demo autocontenida (Docker) con un clic.

## 🚀 Desplegar la demo en línea (Render)

[![Deploy to Render](https://render.com/images/deploy-to-render-button.svg)](https://render.com/deploy?repo=https://github.com/samuelgaleano/sistema-pos-restaurante)

Un solo contenedor **Apache + PHP + MariaDB** (ver `render.yaml` y `Dockerfile`): no requiere base de datos externa, la siembra sola al arrancar y se resetea limpio. Plan free de Render. Login de un clic en la pantalla de acceso.

---

## 📦 Características

- **3 Roles de usuario**: Administrador, Cajero, Mesero — cada uno con su propia vista y permisos.
- **Punto de Venta (POS)**: interfaz táctil rápida, carrito, descuentos, propina, múltiples métodos de pago, cálculo de cambio.
- **Facturación**: numeración automática consecutiva, impresión de recibo formato ticket 80mm, anulación de facturas.
- **Inventario**: control de stock por producto, alertas de stock bajo, entradas y ajustes con trazabilidad (kardex).
- **Control de Mesas**: mapa de mesas por zona, estados (disponible/ocupada/reservada), comandas para cocina.
- **Turnos de Caja**: apertura y cierre de turno, conteo de efectivo, cálculo automático de diferencias.
- **Dashboard Administrativo**: ventas del día, comparativas, top productos, gráficas, reportes por rango de fechas, exportación CSV.
- **Gestión completa**: usuarios, clientes, categorías, configuración del negocio (nombre, NIT, moneda, impuestos).

---

## 🗂️ Estructura del Proyecto

```
pos/
├── ajax/                  # Endpoints AJAX (ventas, turnos, mesas, órdenes...)
├── assets/
│   ├── css/main.css       # Estilos del sistema
│   └── js/main.js         # JavaScript global
├── config/
│   └── database.php       # ⚙️ Configuración de conexión a BD (editar aquí)
├── includes/
│   ├── auth.php           # Autenticación, sesiones, helpers
│   ├── header.php         # Sidebar + topbar (layout)
│   └── footer.php
├── modules/
│   ├── admin/             # Dashboard, productos, inventario, facturas, turnos,
│   │                         clientes, usuarios, reportes, configuración
│   ├── cajero/             # POS, turno, facturas, imprimir
│   └── mesero/             # Mesas, órdenes/comandas
├── database.sql           # Script SQL completo (estructura + datos iniciales)
├── install.php            # 🛠️ Asistente de instalación (úsalo una vez y elimínalo)
├── index.php              # Login
├── dashboard.php          # Redirección según rol
└── logout.php
```

---

## 🚀 Instalación en cPanel

### Opción A — Instalador automático (recomendado)

1. **Sube los archivos**: comprime la carpeta `pos` en un .zip y súbela a tu cPanel (Administrador de Archivos → `public_html` o una subcarpeta), luego descomprime ahí.
2. **Crea la base de datos** en cPanel:
   - Ve a **MySQL® Databases**.
   - Crea una nueva base de datos (ej: `usuario_posdb`).
   - Crea un usuario MySQL y asígnale **todos los privilegios** sobre esa base.
   - Anota: host (normalmente `localhost`), nombre de la base, usuario y contraseña.
3. **Ejecuta el instalador**: visita `https://tudominio.com/pos/install.php` desde el navegador.
   - Sigue el asistente, ingresa los datos de tu base de datos y la URL base de tu sistema.
   - El instalador creará automáticamente todas las tablas y los datos iniciales.
4. **Elimina `install.php` y `database.sql`** del servidor por seguridad una vez completada la instalación.
5. Ingresa a `https://tudominio.com/pos/` y entra con los usuarios de prueba.

### Opción B — Instalación manual

1. Sube los archivos por FTP/Administrador de Archivos.
2. Crea la base de datos y el usuario MySQL desde cPanel (igual que arriba).
3. Ve a **phpMyAdmin**, selecciona tu base de datos, pestaña **Importar**, y sube el archivo `database.sql`.
4. Edita `config/database.php` con tus credenciales:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'usuario_posdb');
   define('DB_USER', 'usuario_dbuser');
   define('DB_PASS', 'tu_contraseña');
   define('BASE_URL', 'https://tudominio.com/pos');
   ```
5. Visita `https://tudominio.com/pos/` para entrar al sistema.

---

## 👤 Usuarios de Demostración

| Usuario    | Contraseña | Rol            |
|------------|------------|----------------|
| `admin`    | `password` | Administrador  |
| `cajero1`  | `password` | Cajero         |
| `mesero1`  | `password` | Mesero         |

> ⚠️ **Cambia estas contraseñas** desde el módulo de Usuarios (rol Administrador) antes de usar el sistema en producción.

---

## ⚙️ Configuración Inicial Recomendada

Una vez dentro como **Administrador**, ve a:

1. **Configuración** → coloca el nombre real de tu negocio, NIT, dirección, impuesto (IVA), moneda.
2. **Productos** → edita o crea tus productos reales, categorías, precios y stock.
3. **Usuarios** → crea las cuentas reales de tus cajeros y meseros, desactiva o elimina las de prueba.
4. **Clientes** → registra tus clientes frecuentes (opcional, ya existe "Cliente General").

---

## 🔄 Flujo de Trabajo del Sistema

1. **Mesero**: abre una mesa → crea una orden/comanda → agrega productos → actualiza estados (pendiente → en cocina → listo → entregado).
2. **Cajero**: abre su turno de caja con un monto inicial → realiza ventas desde el POS (directas o cobrando una mesa) → al final del día cierra el turno contando el efectivo físico.
3. **Administrador**: supervisa todo desde el Dashboard — ventas del día, turnos activos, stock bajo, reportes por rango de fechas, y puede gestionar todos los módulos.

---

## 🛡️ Seguridad

- Las contraseñas se almacenan con `password_hash()` (bcrypt).
- Las consultas usan **PDO con prepared statements** (protección contra SQL injection).
- El archivo `.htaccess` bloquea el acceso directo a `.sql`, `.env`, `.log` y a la carpeta `config/`.
- **Recomendado**: activa SSL/HTTPS en cPanel (Let's Encrypt gratuito) y descomenta la sección de redirección HTTPS en `.htaccess`.

---

## 🖨️ Impresión de Facturas

El sistema genera un recibo en formato térmico de 80mm (`modules/cajero/imprimir.php`), compatible con la mayoría de impresoras de tickets POS conectadas por USB que funcionan como impresora de Windows/navegador. Al imprimir desde el navegador, selecciona el tamaño de papel "80mm" o "Recibo" en el diálogo de impresión.

---

## 📋 Requisitos del Servidor

- PHP 7.4 o superior (recomendado 8.0+)
- MySQL 5.7+ o MariaDB 10.3+
- Extensión PDO MySQL habilitada (estándar en cPanel)
- Extensión `mbstring` habilitada (estándar en cPanel)

---

## 🆘 Solución de Problemas

**Error de conexión a la base de datos**: verifica que los datos en `config/database.php` coincidan exactamente con los de tu panel de MySQL Databases en cPanel (en cPanel el nombre de usuario y base de datos suelen tener el prefijo de tu cuenta, ej: `cpaneluser_posdb`).

**Página en blanco**: activa temporalmente errores en PHP agregando al inicio de `index.php`:
```php
error_reporting(E_ALL); ini_set('display_errors', 1);
```

**Las imágenes/iconos no cargan**: el sistema usa Font Awesome y Chart.js desde CDN (cdnjs.cloudflare.com), asegúrate que tu servidor permita conexiones salientes o que el navegador del cliente tenga acceso a internet.

---

Desarrollado como sistema base profesional — personalízalo según las necesidades específicas de tu negocio.

---

## 🐳 Demo con Docker

Para levantar el sistema completo (app + MySQL) en un solo paso, sin instalar PHP ni MySQL localmente:

```bash
docker compose up --build
```

Luego abre **http://localhost:8080/** — la base de datos se crea e inicializa sola en el primer arranque (esquema + datos de ejemplo desde `database.sql`).

**Usuarios de demostración** (contraseña para los tres: `password`):

| Usuario    | Rol            |
|------------|----------------|
| `admin`    | Administrador  |
| `cajero1`  | Cajero         |
| `mesero1`  | Mesero         |

Notas de esta configuración Docker:

- Las credenciales de MySQL definidas en `docker-compose.yml` (`pos_root_pw`, `pos_user` / `pos_pass`) son **solo para esta demo local**; no se usan en un hosting real y no deben reutilizarse.
- `install.php` no está disponible dentro del contenedor (excluido vía `.dockerignore`) porque la base de datos ya queda lista automáticamente.
- El prefijo de URL pública de la app (usado para enlaces y redirects) se controla con la variable de entorno `POS_BASE_PATH` (por defecto `/pos-demo` en el `docker-compose.yml`, pensado para servir esta demo embebida same-origin, vía proxy inverso, desde otro sitio). Sin Docker, `config/database.php` sigue funcionando igual que siempre, con rutas relativas a la raíz.
- Para detener y borrar los contenedores (conservando los datos de MySQL en el volumen): `docker compose down`. Para borrar también los datos: `docker compose down -v`.
