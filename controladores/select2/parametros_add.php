<?php
require_once '../ConnectionParametrizacion.php';
header('Content-Type: application/json');

$id          = $_POST['id']          ?? null;
$codigo      = $_POST['codigo']      ?? '';
$descripcion = $_POST['descripcion'] ?? '';
$valor       = $_POST['valor']       ?? '';
$vigente     = $_POST['vigente']     ?? true;

try {
    if (!$id || !$codigo || !$descripcion || !$valor) {
        throw new Exception("Todos los campos son obligatorios para insertar.");
    }

    $con = Connection::getInstance()->getConnection();

    // Validación opcional: evitar duplicados
    $check = $con->prepare("SELECT COUNT(*) FROM parametros WHERE id = ? AND codigo = ?");
    $check->bind_param('is', $id, $codigo);
    $check->execute();
    $check->bind_result($count);
    $check->fetch();
    $check->close();

    if ($count > 0) {
        throw new Exception("Ya existe un parámetro con ese ID y Código.");
    }

    $stmt = $con->prepare("
        INSERT INTO parametros (id, codigo, descripcion, valor, fecha_creacion, vigente)
        VALUES (?, ?, ?, ?, NOW(), ?)
    ");
    $stmt->bind_param('isssi', $id, $codigo, $descripcion, $valor, $vigente);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Parámetro insertado correctamente']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}