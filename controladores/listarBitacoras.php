<?php
header('Content-Type: application/json');

require_once 'ConnectionParametrizacion.php';

try {
    $con = ConnectionParametrizacion::getInstance()->getConnection();
    
    $fecha_ejecucion_ini = $_POST['fecha_ejecucion_ini'] ?? null;
    $fecha_ejecucion_fin = $_POST['fecha_ejecucion_fin'] ?? null;
    
    //Validación de los campos obligatorios
    //    if (!$fecha_ejecucion_ini || !$fecha_ejecucion_fin) { 
    //    http_response_code(400);
    //    echo json_encode([
    //        'statusCode' => 400,
    //        'mensaje' => 'Los campos Fecha Inicial y fecha final son  obligatorios.'
    //    ]);
    //    exit;
   // }
    
    
    $sql = ("SELECT 
                id_bitacora,
                tipo_de_cargue, 
                fecha_ejecucion, 
                hora_ejecucion, 
                origen_del_proceso, 
                cantidad_registros_enviados, 
                tamaño_del_archivo, 
                resultado_del_envio, 
                descripcion_error, 
                parametros_usados, 
                fecha_hora_de_inicio, 
                fecha_hora_de_fin, 
                satisfactorio, 
                ruta_archivo, 
                archivo_borrado 
    FROM bitacora 
    ORDER BY fecha_hora_de_inicio DESC");
    //WHERE (fecha_ejecución >= ? AND fecha_ejecion <= ?)
    $stmt = $con->prepare($sql);

    //$stmt->bind_param("ss", $fecha_ejecucion_ini, $fecha_ejecucion_fin); 
    $stmt->execute();
    $res = $stmt->get_result();

    $bitacoras = [];
    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $bitacoras[] = [
                'id_bitacora'           => $row['id_bitacora'],
                'tipo_de_cargue'        => $row['tipo_de_cargue'],
                'fecha_ejecucion'       => $row['fecha_ejecucion'],
                'hora_ejecucion'        => $row['hora_ejecucion'],
                'origen_del_proceso'    => $row['origen_del_proceso'],
                'cantidad_registros_enviados'  => $row['cantidad_registros_enviados'],
                'tamaño_del_archivo'    => $row['tamaño_del_archivo'],
                'resultado_del_envio'   => $row['resultado_del_envio'],
                'descripcion_error'     => $row['descripcion_error'],
                'parametros_usados'     => $row['parametros_usados'],
                'fecha_hora_de_inicio'  => $row['fecha_hora_de_inicio'],
                'fecha_hora_de_fin'     => $row['fecha_hora_de_fin'],
                'satisfactorio'         => $row['satisfactorio'],
                'ruta_archivo'          => $row['ruta_archivo'],
                'archivo_borrado'       => $row['archivo_borrado']
            ];
        }
    }
    

        http_response_code(200);
    echo json_encode([
        'statusCode' => 200,
        'mensaje' => 'Bitacoras listadas correctamente.'
        
    ]);

} catch (Exception $e) {
    if (isset($con) && $con->errno === 0) {
        $con->rollback();
    }
    http_response_code(500);
    echo json_encode([
        'statusCode' => 500,
        'mensaje' => 'Error general: ' . $e->getMessage()
    ]);
}