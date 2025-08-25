CREATE TABLE ParametrosDelSistema (
    ID INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del parámetro',
    CODIGO VARCHAR(100) NOT NULL UNIQUE COMMENT 'Código del parámetro',
    DESCRIPCION VARCHAR(255) COMMENT 'Descripción del parámetro a configurar',
    VALOR TEXT COMMENT 'Valor asignado al parámetro',
    FECHA_CREACION TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación del parámetro',
    VIGENTE BOOLEAN DEFAULT TRUE COMMENT 'TRUE = parámetro vigente, FALSE = histórico'
);
