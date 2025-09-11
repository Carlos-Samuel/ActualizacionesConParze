<?php
header('Content-Type: application/json');

require_once 'ConnectionParametrizacion.php';

try {
    $con = ConnectionParametrizacion::getInstance()->getConnection();
    
    $tipo_de_cargue = $_POST['tipo_de_cargue'] ?? null;
    $fecha_ejecucion = $_POST['fecha_ejecucion'] ?? null;
    $hora_ejecucion = $_POST['hora_ejecucion'] ?? null;     
    $origen_del_proceso = $_POST['origen_del_proceso'] ?? null;
    $cantidad_registros_enviados = $_POST['cantidad_registros_enviados'] ?? null;
    $tamaño_del_archivo = $_POST['tamaño_del_archivo'] ?? null;
    $resultado_del_envio = $_POST['resultado_del_envio'] ?? null;
    $descripcion_error = $_POST['descripcion_error'] ?? null;    
    $parametros_usados = $_POST['parametros_usados'] ?? null;    
    $satisfactorio = $_POST['satisfactorio'] ?? null;
    $ruta_archivo = $_POST['ruta_archivo'] ?? null;
    $archivo_borrado = $_POST['archivo_borrado'] ?? null;
    
    //Validación de los campos obligatorios
    if (!$tipo_de_cargue || !$origen_del_proceso )  {
        http_response_code(400);
        echo json_encode([
            'statusCode' => 400,
            'mensaje' => 'Los campos Tipo de Cargue, Origen son  obligatorios.'
        ]);
        exit;
    }
    
    $con->begin_transaction();

    $stmt1 = $con->prepare("INSERT INTO bitacora (tipo_de_cargue, fecha_ejecucion, hora_ejecucion, origen_del_proceso, cantidad_registros_enviados, tamaño_del_archivo, resultado_del_envio, descripcion_error, parametros_usados, fecha_hora_de_inicio, fecha_hora_de_fin, satisfactorio, ruta_archivo, archivo_borrado) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(),NOW(), ?, ?, ?)");

    $stmt1->bind_param("ssssissssisi", $tipo_de_cargue, $fecha_ejecucion, $hora_ejecucion, $origen_del_proceso, $cantidad_registros_enviados, $tamaño_del_archivo, $resultado_del_envio, $descripcion_error, $parametros_usados, $satisfactorio, $ruta_archivo, $archivo_borrado); 
    $stmt1->execute();

    $id_bitacora = $con->insert_id;

    $con->commit();


    http_response_code(200);
    echo json_encode([
        'statusCode' => 200,
        'mensaje' => 'Bitacora Registrada correctamente.'
        
    ]);
    return $id_bitacora;

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