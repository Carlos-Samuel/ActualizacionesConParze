CREATE TABLE almacenado_parametrizaciones(
    id_almacebamiento INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador unico de la parametrizacion',
	empresa VARCHAR(100) NOT NULL, COMMENT 'Empresa que realiza el reporte'
	bodega VARCHAR(100) NOT NULL, COMMENT 'Bodega de la empresa'
    descuento FLOAT DEFAULT 0 COMMENT 'Descuento otorgado al producto'
    Url_endpoint_api VARCHAR(255) NOT NULL COMMENT 'URL del endpoint del API'
    Llave_autorizacion VARCHAR(255) NOT NULL COMMENT 'Llave de autorizacion para el API'
    hora_cargue_diario_full TIME COMMENT 'Hora del dia en que se realiza el cargue diario full'
	frecuencia_cargue_periodico INT COMMENT 'Frecuencia en minutos para el cargue periodico'
	numero_intentos_a_realizar INT COMMENT 'Numero de intentos a realizar en caso de error'
    tiempo_entre_intentos INT COMMENT 'Tiempo en segundos entre cada intento'
)COMMENT='Tabla que almacena las parametrizaciones del sistema';
		