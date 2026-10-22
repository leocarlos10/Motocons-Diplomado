-- MOTOCONS - Esquema completo (basado en el modelo relacional del documento)
-- Se ejecuta automaticamente la primera vez que se crea el volumen de MySQL.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------- Catalogo ----------
CREATE TABLE marca (
    id_marca INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE categoria (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE referencia_moto (
    id_referencia INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    tipo_propulsion VARCHAR(30) NOT NULL,          -- combustion / electrica
    precio_base DECIMAL(14,2) NOT NULL,
    ficha_tecnica TEXT,
    id_marca INT NOT NULL,
    id_categoria INT NOT NULL,
    FOREIGN KEY (id_marca) REFERENCES marca(id_marca),
    FOREIGN KEY (id_categoria) REFERENCES categoria(id_categoria)
) ENGINE=InnoDB;

-- ---------- Personas ----------
CREATE TABLE empleado (
    id_empleado INT AUTO_INCREMENT PRIMARY KEY,
    documento VARCHAR(20) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    rol VARCHAR(30) NOT NULL,                      -- vendedor / mecanico / administrador
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    password VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE cliente (
    id_cliente INT AUTO_INCREMENT PRIMARY KEY,
    documento VARCHAR(20) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    telefono VARCHAR(20),
    correo VARCHAR(100),
    direccion VARCHAR(150),
    password VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE proveedor (
    id_proveedor INT AUTO_INCREMENT PRIMARY KEY,
    nit VARCHAR(20) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    contacto VARCHAR(100)
) ENGINE=InnoDB;

-- ---------- Compras e inventario ----------
CREATE TABLE orden_compra (
    id_orden INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL,
    estado VARCHAR(20) NOT NULL,
    id_proveedor INT NOT NULL,
    id_empleado INT NOT NULL,
    FOREIGN KEY (id_proveedor) REFERENCES proveedor(id_proveedor),
    FOREIGN KEY (id_empleado) REFERENCES empleado(id_empleado)
) ENGINE=InnoDB;

CREATE TABLE detalle_orden_compra (
    id_orden INT NOT NULL,
    id_referencia INT NOT NULL,
    cantidad_solicitada INT NOT NULL,
    cantidad_recibida INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id_orden, id_referencia),
    FOREIGN KEY (id_orden) REFERENCES orden_compra(id_orden),
    FOREIGN KEY (id_referencia) REFERENCES referencia_moto(id_referencia)
) ENGINE=InnoDB;

CREATE TABLE motocicleta (
    id_moto INT AUTO_INCREMENT PRIMARY KEY,
    placa VARCHAR(10) UNIQUE,
    chasis VARCHAR(30) NOT NULL UNIQUE,
    estado VARCHAR(20) NOT NULL,                   -- disponible / vendida
    fecha_ingreso DATE NOT NULL,
    fecha_fin_garantia DATE,
    id_referencia INT NOT NULL,
    id_orden INT,
    FOREIGN KEY (id_referencia) REFERENCES referencia_moto(id_referencia),
    FOREIGN KEY (id_orden) REFERENCES orden_compra(id_orden)
) ENGINE=InnoDB;

-- ---------- Promociones ----------
CREATE TABLE promocion (
    id_promocion INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    tipo_descuento VARCHAR(20) NOT NULL,           -- porcentaje / valor
    valor DECIMAL(14,2) NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    id_empleado INT NOT NULL,
    id_categoria INT NULL,
    id_referencia INT NULL,
    FOREIGN KEY (id_empleado) REFERENCES empleado(id_empleado),
    FOREIGN KEY (id_categoria) REFERENCES categoria(id_categoria),
    FOREIGN KEY (id_referencia) REFERENCES referencia_moto(id_referencia)
) ENGINE=InnoDB;

-- ---------- Finanzas ----------
CREATE TABLE liquidacion_comision (
    id_liquidacion INT AUTO_INCREMENT PRIMARY KEY,
    periodo_inicio DATE NOT NULL,
    periodo_fin DATE NOT NULL,
    porcentaje_comision DECIMAL(5,2) NOT NULL,
    total_ventas DECIMAL(14,2) NOT NULL,
    valor_comision DECIMAL(14,2) NOT NULL,
    id_empleado INT NOT NULL,
    FOREIGN KEY (id_empleado) REFERENCES empleado(id_empleado)
) ENGINE=InnoDB;

CREATE TABLE cierre_financiero (
    id_cierre INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(20) NOT NULL,                     -- semanal / mensual
    periodo_inicio DATE NOT NULL,
    periodo_fin DATE NOT NULL,
    total_consolidado DECIMAL(14,2) NOT NULL,
    fecha_generacion DATE NOT NULL,
    id_empleado INT NOT NULL,
    FOREIGN KEY (id_empleado) REFERENCES empleado(id_empleado)
) ENGINE=InnoDB;

-- ---------- Ventas y pagos ----------
CREATE TABLE venta (
    id_venta INT AUTO_INCREMENT PRIMARY KEY,
    canal VARCHAR(20) NOT NULL,                    -- web / fisica
    fecha DATE NOT NULL,
    total DECIMAL(14,2) NOT NULL,
    estado VARCHAR(20) NOT NULL,
    id_cliente INT NOT NULL,
    id_empleado INT NOT NULL,
    id_promocion INT NULL,
    id_liquidacion INT NULL,
    id_cierre INT NULL,
    FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente),
    FOREIGN KEY (id_empleado) REFERENCES empleado(id_empleado),
    FOREIGN KEY (id_promocion) REFERENCES promocion(id_promocion),
    FOREIGN KEY (id_liquidacion) REFERENCES liquidacion_comision(id_liquidacion),
    FOREIGN KEY (id_cierre) REFERENCES cierre_financiero(id_cierre)
) ENGINE=InnoDB;

CREATE TABLE detalle_venta (
    id_moto INT NOT NULL,
    id_venta INT NOT NULL,
    precio_original DECIMAL(14,2) NOT NULL,
    descuento DECIMAL(14,2) NOT NULL DEFAULT 0,
    precio_final DECIMAL(14,2) NOT NULL,
    PRIMARY KEY (id_moto, id_venta),
    FOREIGN KEY (id_moto) REFERENCES motocicleta(id_moto),
    FOREIGN KEY (id_venta) REFERENCES venta(id_venta)
) ENGINE=InnoDB;

CREATE TABLE medio_pago (
    id_medio_pago INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE pago (
    id_pago INT AUTO_INCREMENT PRIMARY KEY,
    monto DECIMAL(14,2) NOT NULL,
    fecha DATE NOT NULL,
    estado VARCHAR(20) NOT NULL,
    id_medio_pago INT NOT NULL,
    id_venta INT NOT NULL,
    id_cierre INT NULL,
    FOREIGN KEY (id_medio_pago) REFERENCES medio_pago(id_medio_pago),
    FOREIGN KEY (id_venta) REFERENCES venta(id_venta),
    FOREIGN KEY (id_cierre) REFERENCES cierre_financiero(id_cierre)
) ENGINE=InnoDB;

CREATE TABLE requisito_credito (
    id_requisito INT AUTO_INCREMENT PRIMARY KEY,
    descripcion VARCHAR(200) NOT NULL,
    id_medio_pago INT NOT NULL,
    FOREIGN KEY (id_medio_pago) REFERENCES medio_pago(id_medio_pago)
) ENGINE=InnoDB;

CREATE TABLE pago_requisito (
    id_pago INT NOT NULL,
    id_requisito INT NOT NULL,
    cumplido BOOLEAN NOT NULL DEFAULT FALSE,
    soporte VARCHAR(200),
    PRIMARY KEY (id_pago, id_requisito),
    FOREIGN KEY (id_pago) REFERENCES pago(id_pago),
    FOREIGN KEY (id_requisito) REFERENCES requisito_credito(id_requisito)
) ENGINE=InnoDB;

-- ---------- Taller ----------
CREATE TABLE mantenimiento (
    id_mantenimiento INT AUTO_INCREMENT PRIMARY KEY,
    numero_servicio INT NOT NULL,
    es_garantia BOOLEAN NOT NULL,
    fecha DATE NOT NULL,
    valor_mano_obra DECIMAL(14,2) NOT NULL DEFAULT 0,
    total DECIMAL(14,2) NOT NULL DEFAULT 0,
    id_moto INT NOT NULL,
    id_empleado INT NOT NULL,
    FOREIGN KEY (id_moto) REFERENCES motocicleta(id_moto),
    FOREIGN KEY (id_empleado) REFERENCES empleado(id_empleado)
) ENGINE=InnoDB;

CREATE TABLE detalle_mantenimiento (
    id_detalle INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(20) NOT NULL,                     -- repuesto / insumo
    descripcion VARCHAR(200) NOT NULL,
    cantidad INT NOT NULL,
    valor_unitario DECIMAL(14,2) NOT NULL,
    id_mantenimiento INT NOT NULL,
    FOREIGN KEY (id_mantenimiento) REFERENCES mantenimiento(id_mantenimiento)
) ENGINE=InnoDB;

-- ---------- Auditoria ----------
CREATE TABLE auditoria (
    id_auditoria INT AUTO_INCREMENT PRIMARY KEY,
    tipo_origen VARCHAR(20) NOT NULL,
    fecha_evento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    accion VARCHAR(20) NOT NULL,
    tabla_afectada VARCHAR(100) NOT NULL,
    id_empleado INT NULL,
    id_cliente INT NULL,
    FOREIGN KEY (id_empleado) REFERENCES empleado(id_empleado),
    FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
