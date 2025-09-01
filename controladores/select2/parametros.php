<?php

require_once '../ConnectionParametrizacion.php';
require_once realpath(__DIR__ . '/../../vendor/autoload.php');
header('Content-Type: application/json');

try {
    $search = $_POST['search'] ?? '';

    $con = Connection::getInstance()->getConnection();

    $stmt = $con->prepare("
        SELECT 
            parm.id AS id, 
            parm.codigo AS codigo,
            parm.descripcion AS descripcion,
            parm.valor AS valor,
            parm.fecha_creacion AS fecha_de_creacion,
            parm.fecha_modificacion AS fecha_de_modificacion,
            parm.vigente AS vigente
        FROM parametros parm
        WHERE parm.descripcion LIKE CONCAT('%', ?, '%')
        ORDER BY parm.descripcion
    ");
    $stmt->bind_param('s', $search);
    $stmt->execute();

    $result = $stmt->get_result();

    $parametros = [];
    while ($row = $result->fetch_assoc()) {
        $parametros[] = $row;
    }

    echo json_encode($empresas);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
