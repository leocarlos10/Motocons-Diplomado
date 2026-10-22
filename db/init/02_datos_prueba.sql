-- MOTOCONS - Datos de prueba (suficientes para demostrar el modulo de mantenimiento)
-- Las contrasenas son placeholders: cuando hagamos login se reemplazan por hashes de password_hash().

INSERT INTO marca (nombre) VALUES ('Yamaha'), ('Honda'), ('AKT'), ('Bajaj');

INSERT INTO categoria (nombre) VALUES ('Gama alta'), ('Gama media'), ('Gama baja'), ('Electrica');

INSERT INTO referencia_moto (nombre, tipo_propulsion, precio_base, ficha_tecnica, id_marca, id_categoria) VALUES
('MT-03', 'combustion', 28000000, '321cc, ABS', 1, 1),
('CB 190R', 'combustion', 11500000, '184cc', 2, 2),
('NKD 125', 'combustion', 6200000, '124cc', 3, 3),
('Pulsar NS 200', 'combustion', 12500000, '199cc', 4, 2);

INSERT INTO empleado (documento, nombre, rol, activo, password) VALUES
('1001', 'Carlos Admin', 'administrador', TRUE, 'cambiar'),
('1002', 'Mario Mecanico', 'mecanico', TRUE, 'cambiar'),
('1003', 'Vera Vendedora', 'vendedor', TRUE, 'cambiar');

INSERT INTO cliente (documento, nombre, telefono, correo, direccion, password) VALUES
('2001', 'Ana Perez', '3001112233', 'ana@correo.com', 'Calle 1 #2-3', 'cambiar'),
('2002', 'Luis Gomez', '3004445566', 'luis@correo.com', 'Carrera 4 #5-6', 'cambiar');

INSERT INTO proveedor (nit, nombre, contacto) VALUES ('900123456', 'Distribuidora Motos SAS', 'ventas@distri.com');

INSERT INTO orden_compra (fecha, estado, id_proveedor, id_empleado) VALUES (CURDATE(), 'recibida', 1, 1);

INSERT INTO motocicleta (placa, chasis, estado, fecha_ingreso, fecha_fin_garantia, id_referencia, id_orden) VALUES
('ABC12D', 'CH0001', 'vendida', '2026-01-10', '2027-01-10', 2, 1),
('XYZ98E', 'CH0002', 'vendida', '2026-02-15', '2027-02-15', 3, 1),
('JKL45F', 'CH0003', 'vendida', '2026-03-20', '2027-03-20', 1, 1);

INSERT INTO medio_pago (nombre) VALUES ('Contado'), ('Tarjeta'), ('ServiCredito'), ('Credito Brilla');

-- Una moto que ya tiene 2 mantenimientos de garantia usados (ABC12D) para probar la regla del limite de 3
INSERT INTO mantenimiento (numero_servicio, es_garantia, fecha, valor_mano_obra, total, id_moto, id_empleado) VALUES
(1, TRUE, '2026-04-10', 0, 45000, 1, 2),
(2, TRUE, '2026-07-12', 0, 60000, 1, 2);

INSERT INTO detalle_mantenimiento (tipo, descripcion, cantidad, valor_unitario, id_mantenimiento) VALUES
('insumo', 'Aceite 20W50', 1, 35000, 1),
('insumo', 'Filtro de aire', 1, 10000, 1),
('repuesto', 'Pastillas de freno', 1, 40000, 2),
('insumo', 'Aceite 20W50', 1, 20000, 2);
