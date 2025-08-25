<?php

require_once '../Connection.php';
require_once realpath(__DIR__ . '/../../vendor/autoload.php');
header('Content-Type: application/json');

try {
    $search = $_POST['search'] ?? '';

    $con = Connection::getInstance()->getConnection();

    $stmt = $con->prepare("
        SELECT 
            te.emprid AS id, 
            te.emprnom AS text,
            te.emprcod AS codigo
        FROM tbl_empresa te
        WHERE te.emprnom LIKE CONCAT('%', ?, '%')
        ORDER BY te.emprnom
    ");
    $stmt->bind_param('s', $search);
    $stmt->execute();

    $result = $stmt->get_result();

    $empresas = [];
    while ($row = $result->fetch_assoc()) {
        $empresas[] = $row;
    }

    echo json_encode($empresas);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
