CREATE TABLE parametros (
    id_parametro INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador unico del parametro',
    codigo VARCHAR(100) NOT NULL UNIQUE COMMENT 'Codigo del parametro',
    descripcion VARCHAR(255) COMMENT 'Descripcion del parametro a configurar',
    valor TEXT COMMENT 'Valor asignado al parametro',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de ultima modificacion del parametro',
    vigente BOOLEAN DEFAULT TRUE COMMENT 'TRUE = parametro vigente, FALSE = historico'
)COMMENT='Tabla que almacena los parametros configurables del sistema';
