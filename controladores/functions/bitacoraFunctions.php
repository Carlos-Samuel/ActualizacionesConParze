<?php
declare(strict_types=1);

require_once __DIR__ . '/../ConnectionParametrizacion.php';

function conn(): mysqli {
    return ConnectionParametrizacion::getInstance()->getConnection();
}

function crear_bitacora(mysqli $cx, $tipo_de_cargue, $origen): int {
    $resultado     = 'Iniciado el proceso';
    $satisfactorio = 0;

    $sql = "INSERT INTO bitacora (
                tipo_de_cargue, fecha_ejecucion, hora_ejecucion, origen_del_proceso,
                resultado_del_envio, satisfactorio
            ) VALUES (
                ?, CURDATE(), CURTIME(), ?, ?, ?
            )";

    $stmt = $cx->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException("Error prepare bitacora: " . $cx->error);
    }

    $stmt->bind_param('sssi', $tipo_de_cargue, $origen, $resultado, $satisfactorio);
    if (!$stmt->execute()) {
        throw new RuntimeException("Error insert bitacora: " . $stmt->error);
    }
    $id = $cx->insert_id;
    $stmt->close();
    return (int)$id;
}

/** Función separada: registra un paso en bitacora_log */
function registrar_paso(mysqli $cx, int $id_bitacora, string $descripcion): void {
    $sql = "INSERT INTO bitacora_log (id_bitacora, descripcion_paso) VALUES (?, ?)";
    $stmt = $cx->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException("Error prepare bitacora_log: " . $cx->error);
    }
    $stmt->bind_param('is', $id_bitacora, $descripcion);
    if (!$stmt->execute()) {
        throw new RuntimeException("Error insert bitacora_log: " . $stmt->error);
    }
    $stmt->close();
}
