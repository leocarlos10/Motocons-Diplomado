SET NAMES utf8mb4;

-- 1. Marcas y Categorías
INSERT INTO marca (id_marca, nombre) VALUES
(1, 'Honda'),
(2, 'AKT'),
(3, 'Yamaha');

INSERT INTO categoria (id_categoria, nombre) VALUES
(1, 'Gama Baja'),
(2, 'Gama Media'),
(3, 'Gama Alta'),
(4, 'Eléctrica');

-- 2. Referencias de Motos
INSERT INTO referencia_moto (id_referencia, nombre, tipo_propulsion, precio_base, ficha_tecnica, id_marca, id_categoria) VALUES
(1, 'CB 190R', 'Combustión', 12500000.00, '184cc, Inyección electrónica, Freno ABS delantero', 1, 2),
(2, 'NKD 125', 'Combustión', 5490000.00, '124cc, Carburador, Freno de disco delantero', 2, 1),
(3, 'MT-03', 'Combustión', 27500000.00, '321cc, Bicilíndrica, Refrigeración líquida, ABS', 3, 3),
(4, 'NIU NQi GTS', 'Eléctrica', 11800000.00, 'Motor Bosch 3000W, Doble batería extraíble', 2, 4);

-- 3. Empleados (Administrador, Mecánico, Vendedor)
INSERT INTO empleado (id_empleado, documento, nombre, rol, activo, password) VALUES
(1, '1065001001', 'Carlos Administrador', 'Administrador', 1, 'admin123'),
(2, '1065002002', 'Mario Mecánico', 'Mecanico', 1, 'meca123'),
(3, '1065003003', 'Laura Vendedora', 'Vendedor', 1, 'vend123'),
(4, '1065004004', 'Jorge Técnico Senior', 'Mecanico', 1, 'meca456');

-- 4. Clientes
INSERT INTO cliente (id_cliente, documento, nombre, telefono, correo, direccion, password) VALUES
(1, '1020485901', 'Ana Pérez', '3001112233', 'ana.perez@email.com', 'Calle 15 # 4-20, Lorica', 'cliente123'),
(2, '71390224', 'Luis Gómez', '3104445566', 'luis.gomez@email.com', 'Carrera 8 # 12-10, Montería', 'cliente123');

-- 5. Proveedor y Orden de Compra
INSERT INTO proveedor (id_proveedor, nit, nombre, contacto) VALUES
(1, '900123456-1', 'Distribuidora Nacional de Motos S.A.S.', '6047890000 / ventas@distrimotos.com');

INSERT INTO orden_compra (id_orden, fecha, estado, id_proveedor, id_empleado) VALUES
(1, '2026-01-15', 'Recibida', 1, 1);

INSERT INTO detalle_orden_compra (id_orden, id_referencia, cantidad_solicitada, cantidad_recibida) VALUES
(1, 1, 2, 2),
(1, 2, 2, 2),
(1, 3, 2, 2);

-- 6. Motocicletas (Vendidas)
INSERT INTO motocicleta (id_moto, placa, chasis, estado, fecha_ingreso, fecha_fin_garantia, id_referencia, id_orden) VALUES
(1, 'ABC12D', '9C2KC2200R000001', 'Vendida', '2026-01-20', '2027-01-20', 1, 1),
(2, 'XYZ98E', '9F1AK1250R000002', 'Vendida', '2026-01-20', '2027-01-20', 2, 1),
(3, 'JKL45F', '9C6MT0300R000003', 'Vendida', '2026-01-20', '2027-01-20', 3, 1);

-- 7. Medios de Pago
INSERT INTO medio_pago (id_medio_pago, nombre) VALUES
(1, 'Contado'),
(2, 'Tarjeta'),
(3, 'ServiCrédito'),
(4, 'Crédito Brilla');

-- 8. Ventas y Detalle de Ventas (Conecta Motocicleta -> Venta -> Cliente)
INSERT INTO venta (id_venta, canal, fecha, total, estado, id_cliente, id_empleado, id_promocion, id_liquidacion, id_cierre) VALUES
(1, 'Fisica', '2026-02-01', 12500000.00, 'Cerrada', 1, 3, NULL, NULL, NULL),
(2, 'Web',    '2026-02-10',  5490000.00, 'Cerrada', 2, 3, NULL, NULL, NULL),
(3, 'Fisica', '2026-02-15', 27500000.00, 'Cerrada', 1, 3, NULL, NULL, NULL);

INSERT INTO detalle_venta (id_venta, id_moto, precio_original, descuento, precio_final) VALUES
(1, 1, 12500000.00, 0.00, 12500000.00), -- ABC12D -> Ana Pérez
(2, 2,  5490000.00, 0.00,  5490000.00), -- XYZ98E -> Luis Gómez
(3, 3, 27500000.00, 0.00, 27500000.00); -- JKL45F -> Ana Pérez

INSERT INTO pago (id_pago, monto, fecha, estado, id_venta, id_medio_pago, id_cierre) VALUES
(1, 12500000.00, '2026-02-01', 'Aprobado', 1, 1, NULL),
(2,  5490000.00, '2026-02-10', 'Aprobado', 2, 2, NULL),
(3, 27500000.00, '2026-02-15', 'Aprobado', 3, 1, NULL);

-- 9. Casos de Prueba de Mantenimiento
-- Caso A: ABC12D tiene 2 mantenimientos (le queda 1 de garantía)
INSERT INTO mantenimiento (id_mantenimiento, numero_servicio, es_garantia, fecha, valor_mano_obra, total, id_moto, id_empleado) VALUES
(1, 1, 1, '2026-03-10', 0.00, 50000.00, 1, 2),
(2, 2, 1, '2026-04-14', 0.00, 69000.00, 1, 2);

INSERT INTO detalle_mantenimiento (id_detalle, tipo, descripcion, cantidad, valor_unitario, id_mantenimiento) VALUES
(1, 'Insumo',   'Aceite mineral 20W-50 4T',       1, 32000.00, 1),
(2, 'Repuesto', 'Filtro de aceite',               1, 18000.00, 1),
(3, 'Insumo',   'Aceite semi-sintético 10W-30',   1, 45000.00, 2),
(4, 'Repuesto', 'Bujía NGK CPR8EA',               1, 24000.00, 2);

-- Caso B: XYZ98E tiene 0 mantenimientos (3 disponibles)

-- Caso C: JKL45F tiene 3 mantenimientos (garantía agotada, el 4.º cobrará $30.000 de mano de obra)
INSERT INTO mantenimiento (id_mantenimiento, numero_servicio, es_garantia, fecha, valor_mano_obra, total, id_moto, id_empleado) VALUES
(3, 1, 1, '2026-03-01', 0.00, 65000.00, 3, 2),
(4, 2, 1, '2026-03-25', 0.00, 48000.00, 3, 4),
(5, 3, 1, '2026-04-18', 0.00, 87000.00, 3, 2);

INSERT INTO detalle_mantenimiento (id_detalle, tipo, descripcion, cantidad, valor_unitario, id_mantenimiento) VALUES
(5, 'Insumo',   'Aceite sintético 10W-40 (2L)',   2, 32500.00, 3),
(6, 'Insumo',   'Líquido refrigerante Yamacool',  1, 48000.00, 4),
(7, 'Insumo',   'Aceite sintético 10W-40',        1, 55000.00, 5),
(8, 'Repuesto', 'Filtro de aire original MT-03',  1, 32000.00, 5);
