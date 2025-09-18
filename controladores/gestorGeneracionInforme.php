<?php
declare(strict_types=1);

set_time_limit(0);
ignore_user_abort(true);

require_once 'functions/bitacoraFunctions.php';
require_once 'functions/generacionReporte.php';

$id = isset($_POST['id_bitacora']) ? (int)$_POST['id_bitacora'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo 'id_bitacora requerido';
    exit;
}

$conParam = ConnectionParametrizacion::getInstance()->getConnection();

try {
    registrar_paso($conParam, $id, 'Inicia el proceso de envío manual');

    generarReporteInventario($id, "DELTA");

    echo 'OK';
} catch (Throwable $e) {
    registrar_paso($conParam, $id, 'Fallo: ' . $e->getMessage());
    http_response_code(500);
    echo 'ERROR: ' . $e->getMessage();
}

