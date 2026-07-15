-- ============================================================
-- SISTEMA POS PROFESIONAL - Base de Datos
-- Compatible con MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

CREATE DATABASE IF NOT EXISTS pos_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pos_db;

-- ============================================================
-- USUARIOS Y ROLES
-- ============================================================
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    descripcion TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rol_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    usuario VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(150),
    telefono VARCHAR(20),
    activo TINYINT(1) DEFAULT 1,
    ultimo_acceso DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rol_id) REFERENCES roles(id)
);

-- ============================================================
-- CONFIGURACIÓN DEL NEGOCIO
-- ============================================================
CREATE TABLE configuracion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(100) UNIQUE NOT NULL,
    valor TEXT,
    descripcion VARCHAR(255)
);

-- ============================================================
-- MESAS
-- ============================================================
CREATE TABLE zonas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    activo TINYINT(1) DEFAULT 1
);

CREATE TABLE mesas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zona_id INT,
    numero VARCHAR(10) NOT NULL,
    nombre VARCHAR(50),
    capacidad INT DEFAULT 4,
    estado ENUM('disponible','ocupada','reservada','mantenimiento') DEFAULT 'disponible',
    posicion_x INT DEFAULT 0,
    posicion_y INT DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,
    FOREIGN KEY (zona_id) REFERENCES zonas(id)
);

-- ============================================================
-- INVENTARIO / PRODUCTOS
-- ============================================================
CREATE TABLE categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    icono VARCHAR(50) DEFAULT 'fa-tag',
    color VARCHAR(20) DEFAULT '#3498db',
    activo TINYINT(1) DEFAULT 1,
    orden INT DEFAULT 0
);

CREATE TABLE unidades_medida (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    abreviatura VARCHAR(10) NOT NULL
);

CREATE TABLE productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT NOT NULL,
    unidad_id INT,
    codigo VARCHAR(50) UNIQUE,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    precio_costo DECIMAL(12,2) DEFAULT 0,
    precio_venta DECIMAL(12,2) NOT NULL,
    stock_actual DECIMAL(12,3) DEFAULT 0,
    stock_minimo DECIMAL(12,3) DEFAULT 0,
    tiene_stock TINYINT(1) DEFAULT 1,
    imagen VARCHAR(255),
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id),
    FOREIGN KEY (unidad_id) REFERENCES unidades_medida(id)
);

CREATE TABLE movimientos_inventario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT NOT NULL,
    usuario_id INT NOT NULL,
    tipo ENUM('entrada','salida','ajuste','venta','devolucion') NOT NULL,
    cantidad DECIMAL(12,3) NOT NULL,
    stock_anterior DECIMAL(12,3),
    stock_nuevo DECIMAL(12,3),
    costo_unitario DECIMAL(12,2),
    referencia VARCHAR(100),
    nota TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (producto_id) REFERENCES productos(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- ============================================================
-- CLIENTES
-- ============================================================
CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_doc ENUM('CC','NIT','CE','PPN','NIT_EXT') DEFAULT 'CC',
    num_doc VARCHAR(20) UNIQUE,
    nombre VARCHAR(150) NOT NULL,
    apellido VARCHAR(100),
    email VARCHAR(150),
    telefono VARCHAR(20),
    direccion TEXT,
    ciudad VARCHAR(100),
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- TURNOS DE CAJA
-- ============================================================
CREATE TABLE turnos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    caja_id INT DEFAULT 1,
    fecha_apertura DATETIME NOT NULL,
    fecha_cierre DATETIME,
    monto_inicial DECIMAL(12,2) DEFAULT 0,
    monto_final DECIMAL(12,2),
    total_ventas DECIMAL(12,2) DEFAULT 0,
    total_efectivo DECIMAL(12,2) DEFAULT 0,
    total_tarjeta DECIMAL(12,2) DEFAULT 0,
    total_transferencia DECIMAL(12,2) DEFAULT 0,
    total_descuentos DECIMAL(12,2) DEFAULT 0,
    diferencia DECIMAL(12,2),
    estado ENUM('abierto','cerrado','revisado') DEFAULT 'abierto',
    notas TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- ============================================================
-- VENTAS / FACTURAS
-- ============================================================
CREATE TABLE facturas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    turno_id INT,
    mesa_id INT,
    cliente_id INT,
    cajero_id INT NOT NULL,
    mesero_id INT,
    numero_factura VARCHAR(20) UNIQUE NOT NULL,
    prefijo VARCHAR(10) DEFAULT 'FAC',
    fecha DATETIME NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    descuento_porcentaje DECIMAL(5,2) DEFAULT 0,
    descuento_valor DECIMAL(12,2) DEFAULT 0,
    impuesto_porcentaje DECIMAL(5,2) DEFAULT 0,
    impuesto_valor DECIMAL(12,2) DEFAULT 0,
    propina DECIMAL(12,2) DEFAULT 0,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    metodo_pago ENUM('efectivo','tarjeta','transferencia','mixto') DEFAULT 'efectivo',
    pago_efectivo DECIMAL(12,2) DEFAULT 0,
    pago_tarjeta DECIMAL(12,2) DEFAULT 0,
    pago_transferencia DECIMAL(12,2) DEFAULT 0,
    cambio DECIMAL(12,2) DEFAULT 0,
    estado ENUM('abierta','pagada','anulada','credito') DEFAULT 'pagada',
    tipo ENUM('venta','devolucion') DEFAULT 'venta',
    factura_ref INT,
    notas TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (turno_id) REFERENCES turnos(id),
    FOREIGN KEY (mesa_id) REFERENCES mesas(id),
    FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    FOREIGN KEY (cajero_id) REFERENCES usuarios(id),
    FOREIGN KEY (mesero_id) REFERENCES usuarios(id)
);

CREATE TABLE factura_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    factura_id INT NOT NULL,
    producto_id INT NOT NULL,
    nombre_producto VARCHAR(150) NOT NULL,
    cantidad DECIMAL(12,3) NOT NULL,
    precio_unitario DECIMAL(12,2) NOT NULL,
    descuento_porcentaje DECIMAL(5,2) DEFAULT 0,
    descuento_valor DECIMAL(12,2) DEFAULT 0,
    subtotal DECIMAL(12,2) NOT NULL,
    notas TEXT,
    FOREIGN KEY (factura_id) REFERENCES facturas(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id)
);

-- ============================================================
-- ÓRDENES DE MESA (Comandas)
-- ============================================================
CREATE TABLE ordenes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mesa_id INT NOT NULL,
    mesero_id INT NOT NULL,
    estado ENUM('abierta','en_proceso','lista','cobrada','cancelada') DEFAULT 'abierta',
    num_personas INT DEFAULT 1,
    notas TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (mesa_id) REFERENCES mesas(id),
    FOREIGN KEY (mesero_id) REFERENCES usuarios(id)
);

CREATE TABLE orden_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orden_id INT NOT NULL,
    producto_id INT NOT NULL,
    cantidad DECIMAL(12,3) NOT NULL,
    precio_unitario DECIMAL(12,2) NOT NULL,
    estado ENUM('pendiente','en_cocina','listo','entregado','cancelado') DEFAULT 'pendiente',
    notas TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (orden_id) REFERENCES ordenes(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id)
);

-- ============================================================
-- DATOS INICIALES
-- ============================================================
INSERT INTO roles (nombre, descripcion) VALUES 
('Administrador', 'Acceso total al sistema'),
('Cajero', 'Manejo de ventas y caja'),
('Mesero', 'Toma de órdenes y mesas');

INSERT INTO configuracion (clave, valor, descripcion) VALUES
('empresa_nombre', 'Mi Restaurante', 'Nombre del negocio'),
('empresa_nit', '900123456-7', 'NIT o documento del negocio'),
('empresa_direccion', 'Calle 123 #45-67', 'Dirección'),
('empresa_telefono', '(601) 123-4567', 'Teléfono'),
('empresa_ciudad', 'Bogotá, Colombia', 'Ciudad'),
('empresa_email', 'info@mirestaurante.com', 'Email'),
('factura_prefijo', 'FAC', 'Prefijo de facturas'),
('factura_consecutivo', '1', 'Número de factura actual'),
('impuesto_nombre', 'IVA', 'Nombre del impuesto'),
('impuesto_porcentaje', '0', 'Porcentaje de impuesto (0 = sin impuesto)'),
('moneda_simbolo', '$', 'Símbolo de moneda'),
('moneda_nombre', 'COP', 'Código de moneda'),
('propina_sugerida', '10', 'Propina sugerida en %'),
('logo_path', '', 'Ruta del logo');

INSERT INTO unidades_medida (nombre, abreviatura) VALUES
('Unidad', 'und'),
('Kilogramo', 'kg'),
('Gramo', 'g'),
('Litro', 'lt'),
('Mililitro', 'ml'),
('Porción', 'por'),
('Botella', 'bot'),
('Copa', 'cop');

INSERT INTO zonas (nombre) VALUES ('Salón Principal'), ('Terraza'), ('Bar'), ('Privado');

INSERT INTO mesas (zona_id, numero, nombre, capacidad) VALUES
(1,'1','Mesa 1',4),(1,'2','Mesa 2',4),(1,'3','Mesa 3',6),
(1,'4','Mesa 4',4),(1,'5','Mesa 5',2),(1,'6','Mesa 6',8),
(2,'T1','Terraza 1',4),(2,'T2','Terraza 2',4),
(3,'B1','Barra 1',2),(3,'B2','Barra 2',2);

INSERT INTO categorias (nombre, icono, color, orden) VALUES
('Entradas','fa-utensils','#e74c3c',1),
('Platos Fuertes','fa-hamburger','#e67e22',2),
('Bebidas','fa-glass-martini-alt','#3498db',3),
('Postres','fa-ice-cream','#9b59b6',4),
('Adicionales','fa-plus-circle','#2ecc71',5);

INSERT INTO productos (categoria_id, unidad_id, codigo, nombre, precio_venta, stock_actual, tiene_stock) VALUES
(1,1,'ENT001','Ensalada César',18000,100,1),
(1,1,'ENT002','Sopa del Día',12000,100,1),
(2,6,'PF001','Bandeja Paisa',28000,50,1),
(2,6,'PF002','Pollo a la Plancha',24000,50,1),
(2,6,'PF003','Filete de Res',38000,30,1),
(3,1,'BEB001','Agua Mineral',3500,200,1),
(3,1,'BEB002','Gaseosa',4500,150,1),
(3,4,'BEB003','Jugo Natural',6000,100,1),
(3,4,'BEB004','Cerveza',7000,100,1),
(4,6,'POS001','Postre del Día',9000,50,1);

-- Contraseña para todos los usuarios de demostración: password
INSERT INTO usuarios (rol_id, nombre, apellido, usuario, password, email) VALUES
(1,'Administrador','Sistema','admin','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin@pos.com'),
(2,'Juan','Cajero','cajero1','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','cajero@pos.com'),
(3,'María','Mesero','mesero1','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','mesero@pos.com');

INSERT INTO clientes (tipo_doc, num_doc, nombre, apellido) VALUES
('CC','000001','Cliente','General');
