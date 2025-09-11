<?php
header('Content-Type: application/json');

require_once 'ConnectionParametrizacion.php';

try {
    $con = ConnectionParametrizacion::getInstance()->getConnection();

    //$codigo = $_POST['codigo'] ?? null;
    //$descripcion = $_POST['descripcion'] ?? null;
    //$valor = $_POST['valor'] ?? null;
    $id_bitacora = $_POST['id_bitacora'] ?? null;
    $tipo_actualización = $_POST['tipo_actualización'] ?? null;

    $tipoCargue = $_POST['tipoCargue'] ?? null;
    $origen = $_POST['origen'] ?? null;
    $cantidadRegistrosEnviados = $_POST['cantidadRegistrosEnviados'] ?? null;
    $tamañoArchivo = $_POST['tamañoArchivo'] ?? null;
    $resultadoDelEnvio = $_POST['resultadoDelEnvio'] ?? null;
    $descripcionDelError = $_POST['descripcionDelError'] ?? null;    
    $parametrosUsados = $_POST['parametrosUsados'] ?? null;    
    $satisfactorio = $_POST['satisfactorio'] ?? null;
    $rutaDeArchivo = $_POST['rutaDeArchivo'] ?? null;
    $archivoBorrado = $_POST['archivoBorrado'] ?? null;

    //Validación de los campos obligatorios
    if (!$id_bitacora ) {
        http_response_code(400);
        echo json_encode([
            'statusCode' => 400,
            'mensaje' => 'El campo id_bitacora es  obligatorio.'
        ]);
        exit;
    }
    
    $con->begin_transaction();

    $stmt1 = $con->prepare("UPDATE bitacora  SET tipo_de_cargue, fecha_ejecucion, hora_ejecucion, origen_del_proceso, cantidad_registros_enviados, tamaño_del_archivo, resultado_del_envio, descripcion_error, parametros_usados, fecha_hora_de_inicio, fecha_hora_de_fin, satisfactorio, ruta_archivo, archivo_borrado) WHERE id_bitacora = ? ");

    $stmt1->bind_param("ssiisssis", $tipoCargue, $origen, $cantidadRegistrosEnviados, $tamañoArchivo, $resultadoDelEnvio, $descripcionDelError, $parametrosUsados, $satisfactorio, $rutaDeArchivo ); 
    $stmt1->execute();

    $con->commit();

    http_response_code(200);
    echo json_encode([
        'statusCode' => 200,
        'mensaje' => 'Bitacora Registradad correctamente.'
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
