-- ============================================================
-- Datos de DEMOSTRACIÓN (solo para el sandbox público).
-- Genera ventas de los últimos 7 días para que el dashboard muestre
-- gráficas, top de productos, facturas recientes, mesas ocupadas y stock bajo.
-- Las fechas son relativas a NOW(), así la demo siempre se ve "de hoy".
-- No forma parte del sistema real: lo aplica el entrypoint tras database.sql.
-- ============================================================
DELIMITER $$
DROP PROCEDURE IF EXISTS seed_demo$$
CREATE PROCEDURE seed_demo()
BEGIN
    DECLARE d INT DEFAULT 6;
    DECLARE n INT; DECLARE i INT; DECLARE fid INT;
    DECLARE pnum INT; DECLARE pprice DECIMAL(12,2); DECLARE pname VARCHAR(150);
    DECLARE qty INT; DECLARE sub DECIMAL(12,2); DECLARE j INT;

    -- Turno de caja abierto para el administrador
    INSERT INTO turnos (usuario_id, fecha_apertura, monto_inicial, estado)
        VALUES (1, DATE_SUB(NOW(), INTERVAL 6 DAY), 200000, 'abierto');

    WHILE d >= 0 DO
        SET n = 4 + FLOOR(RAND()*6);           -- 4..9 facturas por día
        SET i = 0;
        WHILE i < n DO
            INSERT INTO facturas
                (turno_id, cajero_id, numero_factura, fecha, subtotal, total,
                 metodo_pago, pago_efectivo, estado, tipo)
            VALUES
                (1, 1,
                 CONCAT('FAC-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL d DAY), '%m%d'),
                        '-', LPAD(i+1, 3, '0')),
                 DATE_SUB(NOW(), INTERVAL d DAY) - INTERVAL FLOOR(RAND()*8) HOUR,
                 0, 0,
                 ELT(1 + FLOOR(RAND()*3), 'efectivo', 'tarjeta', 'transferencia'),
                 0, 'pagada', 'venta');
            SET fid = LAST_INSERT_ID();
            SET sub = 0;
            SET j = 0;
            WHILE j < 1 + FLOOR(RAND()*3) DO    -- 1..3 renglones por factura
                SET pnum = 1 + FLOOR(RAND()*10);
                SET qty  = 1 + FLOOR(RAND()*3);
                SELECT nombre, precio_venta INTO pname, pprice FROM productos WHERE id = pnum;
                INSERT INTO factura_items
                    (factura_id, producto_id, nombre_producto, cantidad, precio_unitario, subtotal)
                VALUES (fid, pnum, pname, qty, pprice, qty * pprice);
                SET sub = sub + qty * pprice;
                SET j = j + 1;
            END WHILE;
            UPDATE facturas SET subtotal = sub, total = sub, pago_efectivo = sub WHERE id = fid;
            SET i = i + 1;
        END WHILE;
        SET d = d - 1;
    END WHILE;

    -- Totales del turno
    UPDATE turnos t SET
        total_ventas = (SELECT COALESCE(SUM(total),0) FROM facturas WHERE turno_id = 1),
        total_efectivo = (SELECT COALESCE(SUM(total),0) FROM facturas WHERE turno_id = 1)
    WHERE t.id = 1;

    -- Ambiente vivo: mesas ocupadas y un producto en stock bajo
    UPDATE mesas SET estado = 'ocupada' WHERE id IN (1, 3, 6);
    UPDATE productos SET stock_actual = 3, stock_minimo = 15 WHERE id = 5;
END$$
DELIMITER ;
CALL seed_demo();
DROP PROCEDURE seed_demo;
