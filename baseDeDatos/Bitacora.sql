CREATE TABLE bitacora_de_ejecuciones ()
		id_bitacora INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador unico de la bitacora',
		tipo_de_cargue ENUM('FULL', 'DELTA') COMMENT 'tipo de cargue del envio realizado'
		fecha_ejecucion DATE COMMENT 'Fecha en que se realiza el proceso'
		hora_ejecucion TIME COMMENT 'Hora en que se realiza el proceso' 
		origen_del_proceso ENUM('Manual', 'Automatico', 'Reenvio') COMMENT'Origen del proceso de envio'
		cantidad_registros_enviados INTEGER COMMENT 'Cantidad de registros que se enviaron en el proceso'
		tamaño_del_archivo VARCHAR COMMENT 'Tamaño del archivo enviado en el proceso' 
        resultado_del_envio ENUM('Exitoso', 'Fallido') COMMENT 'resultado del envio exitoso o fallido'
        descripcion_error VARCHAR COMMENT 'Descripcion del error en caso de fallo'
        parametros_usados VARCHAR COMMENT 'Parametros usados en el proceso'
		Fecha_hora_de_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora en que se registro la ejecucion' 
        satisfactorio BOOLEAN COMMENT 'TRUE = ejecucion satisfactoria, FALSE = ejecucion con errores'
		ruta_archivo VARCHAR COMMENT 'Ruta donde se almacena el archivo generado'
		Archivo_borrado BOOLEAN comment 'TRUE = archivo borrado despues de enviarse, FALSE = archivo no borrado'
)COMMENT='Tabla que almacena la bitacora de ejecuciones del sistema';