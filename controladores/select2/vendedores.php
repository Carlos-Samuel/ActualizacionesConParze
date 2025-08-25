<?php
require_once '../Connection.php';
require_once realpath(__DIR__ . '/../../vendor/autoload.php');
header('Content-Type: application/json');

$search = $_POST['search'] ?? '';

try {
    $con = Connection::getInstance()->getConnection();

    $stmt = $con->prepare("
        SELECT venid AS id, vennom AS text
        FROM vendedor
        WHERE vennom LIKE CONCAT('%', ?, '%')
        ORDER BY vennom
    ");
    $stmt->bind_param('s', $search);
    $stmt->execute();
    $result = $stmt->get_result();

    $vendedores = [];
    while ($row = $result->fetch_assoc()) {
        $vendedores[] = $row;
    }

    echo json_encode($vendedores);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
