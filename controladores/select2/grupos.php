<?php

require_once '../Connection.php';
require_once realpath(__DIR__ . '/../../vendor/autoload.php');
header('Content-Type: application/json');

try {
    if (!isset($_POST['empresa_id']) || !is_array($_POST['empresa_id'])) {
        throw new Exception("Falta el parámetro 'empresa_id' o no es un arreglo válido");
    }

    $empresaIds = array_map('intval', $_POST['empresa_id']);
    $search = $_POST['search'] ?? '';

    if (empty($empresaIds)) {
        echo json_encode([]); // Sin empresas, sin resultados
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($empresaIds), '?'));

    $con = Connection::getInstance()->getConnection();

    $query = "
        SELECT 
            g.grpid AS id, 
            g.grpnom AS text,
            te.emprcod AS codigo
        FROM ingrupos g
        LEFT JOIN tbl_empresa te ON te.emprid = g.empid
        WHERE empid IN ($placeholders) AND grpnom LIKE CONCAT('%', ?, '%')
        ORDER BY grpnom
    ";

    $stmt = $con->prepare($query);

    // Construir tipos de parámetros
    $types = str_repeat('i', count($empresaIds)) . 's';
    $params = [...$empresaIds, $search];

    // Vincular dinámicamente
    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    $result = $stmt->get_result();

    $grupos = [];
    while ($row = $result->fetch_assoc()) {
        $grupos[] = $row;
    }

    echo json_encode($grupos);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
