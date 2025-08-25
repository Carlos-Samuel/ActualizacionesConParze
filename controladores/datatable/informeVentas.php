<?php
require_once '../Connection.php';
header('Content-Type: application/json');

// Validar fechas obligatorias
$fechaInicio = $_POST['fechaInicio'] ?? null;
$fechaFin = $_POST['fechaFin'] ?? null;

if (!$fechaInicio || !$fechaFin) {
    echo json_encode([
        'data' => [],
        'error' => 'Fechas obligatorias'
    ]);
    exit;
}

// Obtener filtros como arrays
$vendedor = $_POST['vendedor'] ?? [];
$empresa = $_POST['empresa'] ?? [];
$grupo = $_POST['grupo'] ?? [];
$subgrupo = $_POST['subgrupo'] ?? [];
$marca = $_POST['marca'] ?? [];
$prefijo = $_POST['prefijo'] ?? [];

$con = Connection::getInstance()->getConnection();

$where = "WHERE ve.vtafec BETWEEN ? AND ?";
$params = [$fechaInicio, $fechaFin];
$types = "ss";

// Función para manejar filtros múltiples
function agregarFiltroMultiple(&$where, &$params, &$types, $nombreColumna, $valores) {
    if (is_array($valores) && count($valores) > 0) {
        $placeholders = implode(',', array_fill(0, count($valores), '?'));
        $where .= " AND $nombreColumna IN ($placeholders)";
        foreach ($valores as $valor) {
            $params[] = $valor;
            $types .= 's';
        }
    }
}

// Aplicar filtros
agregarFiltroMultiple($where, $params, $types, 'ven.venid', $vendedor);
agregarFiltroMultiple($where, $params, $types, 'ven.empid', $empresa);
agregarFiltroMultiple($where, $params, $types, 'prod.grpid', $grupo);
agregarFiltroMultiple($where, $params, $types, 'prod.subid', $subgrupo);
agregarFiltroMultiple($where, $params, $types, 'mar.marid', $marca);
agregarFiltroMultiple($where, $params, $types, 'pre.prfid', $prefijo);

$sql = 
    "SELECT 
        ve.vtafec AS fecha_factura,
        pre.prfcod AS prefijo,
        pre.prfnom AS prefijo_nombre,
        ve.vtanum AS numero_factura,
        ven.vencod AS codigo_vendedor,
        ven.vennom AS nombre_vendedor,
        ter.ternit AS tercero_nit,
        ter.ternom AS tercero_nombre,
        prod.procod AS codigo_producto,
        prod.pronom AS nombre_producto,
        vedet.vtacant AS cantidad,
        vedet.vtavlruni AS valor_unitario,
        vedet.vtacant * vedet.vtavlruni AS subtotal,
        gru.grpnom AS grupo,
        subgru.subnom AS subgrupo,
        mar.marcod AS codigo_marca,
        mar.marnom AS nombre_marca,
        cc.ctonom AS centro_costos
    FROM ventas AS ve 
    LEFT JOIN prefijo AS pre ON ve.prfid = pre.prfid
    LEFT JOIN ventasdet AS vedet ON ve.vtaid = vedet.vtaid
    LEFT JOIN productos AS prod ON vedet.proid = prod.proid
    LEFT JOIN vendedor AS ven ON ve.venid = ven.venid
    LEFT JOIN terceros AS ter ON ve.terid = ter.terid
    LEFT JOIN ingrupos AS gru ON prod.grpid = gru.grpid
    LEFT JOIN insubgrupo AS subgru ON prod.subid = subgru.subid
    LEFT JOIN inmarca AS mar ON prod.marid = mar.marid
    LEFT JOIN centrocosto AS cc ON ve.ctoid = cc.ctoid
    $where
    ORDER BY ve.vtafec, ve.vtahor";

//echo $sql;

$query = $con->prepare($sql);

$query->bind_param($types, ...$params);
$query->execute();
$result = $query->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode(['data' => $data]);
