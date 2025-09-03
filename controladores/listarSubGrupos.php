<?php
header('Content-Type: application/json');

require_once 'Connection.php';
require_once 'ConnectionParametrizacion.php';

try {
    $con = Connection::getInstance()->getConnection();
    $connParametrizacion = ConnectionParametrizacion::getInstance()->getConnection();

    // 1) Obtener el parámetro EMPRESAS_SELECCIONADAS (emprcod separados por ;)
    $codigoParam = 'GRUPOS_SELECCIONADOS';
    $stmtParam = $connParametrizacion->prepare("SELECT valor 
                                FROM parametros 
                                WHERE codigo = ? AND vigente = TRUE 
                                ORDER BY fecha_modificacion DESC 
                                LIMIT 1");
    $stmtParam->bind_param("s", $codigoParam);
    $stmtParam->execute();
    $resParam = $stmtParam->get_result();

    $gruposCod = [];
    if ($resParam && $row = $resParam->fetch_assoc()) {
        $valor = trim((string)$row['valor']);
        if ($valor !== '') {
            $gruposCod = array_values(array_filter(array_map('trim', explode(';', $valor)), function($v) {
                return $v !== '';
            }));
        }
    }

    // Si no hay grupos parametrizadas, devolver lista vacía (status 200)
    if (count($empresasCod) === 0) {
        http_response_code(200);
        echo json_encode([
            'statusCode' => 200,
            'mensaje'    => 'No hay grupos parametrizadas vigentes para filtrar grupos.',
            'subgrupos'  => []
        ]);
        exit;
    }

    // 2) Armar consulta con IN dinámico sobre e.emprcod (los códigos parametrizados)
    // Nota: el JOIN se mantiene por empid/emprid.
    $placeholders = implode(',', array_fill(0, count($gruposCod), '?'));
    $sql = "SELECT 
                b.grpcod   AS grpcod,
                b.grpnom   AS grpnom,
                e.emprcod  AS emprcod,
                e.emprnom  AS emprnom
            FROM insubgrupos s
            INNER JOIN ingrupos g ON g.grpid = s.subid
            WHERE s.empid IS NOT NULL
              AND g.grpcod IN ($placeholders)
            ORDER BY g.grpnom ASC, s.subnom ASC";

    $stmt = $con->prepare($sql);

    // bind_param dinámico (todos como string)
    $types = str_repeat('s', count($gruposCod));
    $params = [];
    $params[] = & $types;
    foreach ($gruposCod as $k => $v) {
        $params[] = & $gruposCod[$k];
    }
    call_user_func_array([$stmt, 'bind_param'], $params);

    $stmt->execute();
    $res = $stmt->get_result();

    //$bodegas = [];
    $subgrupos = [];
    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $grupos[] = [
                'subcod'  => $row['subcod'],
                'subnom'  => $row['subnom'],
                'grpcod'  => $row['grpcod'],
                'grpnom'  => $row['grpnom'],
            ];
        }
    }

    http_response_code(200);
    echo json_encode([
        'statusCode' => 200,
        'mensaje'    => 'Subgrupos listados correctamente.',
        'subgrupos'    => $subgrupos
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'statusCode' => 500,
        'mensaje'    => 'Error al listar sub grupos: ' . $e->getMessage()
    ]);
}
