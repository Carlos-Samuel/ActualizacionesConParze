<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=UTF-8');

require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/controladores/ConnectionParametrizacion.php';
require_once __DIR__ . '/controladores/Connection.php';

use XBase\TableReader;

/* ============================================================
   CONFIGURACIÓN
   ============================================================ */

// Ruta al DBF (ajusta si aplica)
$DBF_PATH = "C:\\Users\\csamu\\OneDrive\\Escritorio\\Parze\\PRODEXIS.DBF";

// Codificación del DBF
$DBF_ENCODING = 'CP1252';

// Nombre de la tabla de productos en MySQL:
$PRODUCTS_TABLE = 'productos';

// Columnas esperadas en productos:
$COL_CODE  = 'ProCod';
$COL_SUBID = 'SubId';
$COL_COSTO = 'ProCosto';

// Códigos de parámetros
$PARAM_BODEGAS  = 'BODEGAS_SELECCIONADAS';
$PARAM_SUBGRUPS = 'SUBGRUPOS_CON_DESCUENTO';


/* ============================================================
   HELPERS
   ============================================================ */

function get_param_vigente(mysqli $con, string $codigo): ?array {
    $sql = "
        SELECT codigo, descripcion, valor, vigente, fecha_creacion, fecha_modificacion
        FROM parametros
        WHERE codigo = ? AND vigente = TRUE
        ORDER BY fecha_modificacion DESC
        LIMIT 1
    ";
    $st = $con->prepare($sql);
    if (!$st) throw new RuntimeException("Error prepare param: " . $con->error);
    $st->bind_param('s', $codigo);
    $st->execute();
    $res = $st->get_result();
    $row = $res->fetch_assoc();
    $st->close();
    return $row ?: null;
}

function parse_bodegas(string $raw): array {
    $out = [];
    $json = json_decode($raw, true);
    if (is_array($json)) {
        foreach ($json as $v) {
            if ($v === null) continue;
            $b = trim((string)(is_array($v) ? ($v['bod'] ?? $v['bodega'] ?? $v['id'] ?? '') : $v));
            if ($b !== '') $out[$b] = true;
        }
        if (!empty($out)) return $out;
    }
    $parts = preg_split('/[,\;\|\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($parts as $p) {
        $b = trim($p);
        if ($b !== '') $out[$b] = true;
    }
    return $out;
}

function parse_subgrupos_descuento(string $raw): array {
    $map = [];
    $json = json_decode($raw, true);
    if (is_array($json)) {
        foreach ($json as $it) {
            if (!is_array($it)) continue;
            $sid = $it['subId'] ?? $it['subgrupo'] ?? $it['SubId'] ?? null;
            $pct = $it['descuento'] ?? $it['porcentaje'] ?? 0;
            if ($sid !== null && $sid !== '') {
                $map[(int)$sid] = (float)$pct;
            }
        }
        if (!empty($map)) return $map;
    }
    $pairs = preg_split('/[,\;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
    $foundPairs = false;
    foreach ($pairs as $p) {
        if (strpos($p, ':') !== false) {
            [$sid, $pct] = array_map('trim', explode(':', $p, 2));
            if ($sid !== '') {
                $map[(int)$sid] = (float)str_replace('%','',$pct);
                $foundPairs = true;
            }
        }
    }
    if ($foundPairs) return $map;
    $ids = preg_split('/[,\;\|\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($ids as $sid) $map[(int)$sid] = 0.0;
    return $map;
}

/**
 * Carga productos por lote de códigos (IN (...)).
 * Devuelve: code => [SubId, ProCosto]
 */
function fetch_products_by_codes(mysqli $con, string $table, array $codes, string $colCode, string $colSub, string $colCosto): array {
    if (empty($codes)) return [];
    $placeholders = implode(',', array_fill(0, count($codes), '?'));
    $cols = "`$colCode`, `$colSub`, `$colCosto`";
    $sql  = "SELECT {$cols} FROM `{$table}` WHERE `$colCode` IN ($placeholders)";

    $st = $con->prepare($sql);
    if (!$st) throw new RuntimeException("Error prepare productos: " . $con->error);

    $types = str_repeat('s', count($codes));
    $st->bind_param($types, ...array_values($codes));
    $st->execute();
    $res = $st->get_result();

    $map = [];
    while ($row = $res->fetch_assoc()) {
        $code = (string)$row[$colCode];
        $map[$code] = [
            'SubId'    => isset($row[$colSub]) ? (int)$row[$colSub] : null,
            'ProCosto' => isset($row[$colCosto]) ? (float)$row[$colCosto] : null,
        ];
    }
    $st->close();
    return $map;
}


/* ============================================================
   EJECUCIÓN
   ============================================================ */

try {
    $con         = ConnectionParametrizacion::getInstance()->getConnection();
    $con->set_charset('utf8mb4');

    
    $conGeneral  = Connection::getInstance()->getConnection();
    $conGeneral->set_charset('utf8mb4');


    $pBod = get_param_vigente($con, $PARAM_BODEGAS);
    $pSub = get_param_vigente($con, $PARAM_SUBGRUPS);
    if (!$pBod) throw new RuntimeException("No hay parámetro vigente para {$PARAM_BODEGAS}");
    if (!$pSub) throw new RuntimeException("No hay parámetro vigente para {$PARAM_SUBGRUPS}");


    $bodegasSet = parse_bodegas((string)$pBod['valor']); 
    $subDescMap = parse_subgrupos_descuento((string)$pSub['valor']); 


    $tabla = new TableReader($DBF_PATH, ['encoding' => $DBF_ENCODING]);
    $colProcod   = 'procod';
    $colBodcod   = 'bodcod';
    $colCantidad = 'existen';

    $agg = [];    
    $codesSeen = [];

    while ($rec = $tabla->nextRecord()) {
        $bod = trim((string)$rec->get($colBodcod));
        if ($bod === '' || !isset($bodegasSet[$bod])) continue;

        $code = trim((string)$rec->get($colProcod));
        if ($code === '') continue;

        $cantidad = (float)str_replace(',', '.', (string)$rec->get($colCantidad));
        //echo "Código: $code | Bodega: $bod | Cantidad: $cantidad<br>";
        if (!isset($agg[$code])) $agg[$code] = 0;
        $agg[$code] += $cantidad;
        $codesSeen[$code] = true;
    }
    $tabla->close();

    // Info de productos
    $codes = array_keys($codesSeen);
    $productosMap = fetch_products_by_codes($conGeneral, $PRODUCTS_TABLE, $codes, $COL_CODE, $COL_SUBID, $COL_COSTO);

} catch (Throwable $e) {
    http_response_code(500);
    echo "<h2>Error</h2>";
    echo "<pre>" . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</pre>";
    exit;
}


/* ============================================================
   RENDER
   ============================================================ */
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8" />
<title>Listado híbrido DBF + Parametrización + Productos</title>
<style>
  body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin: 20px; }
  h1 { margin: 0 0 12px; }
  table { border-collapse: collapse; width: 100%; }
  th, td { border: 1px solid #ddd; padding: 8px; font-size: 14px; }
  th { background: #f6f8fa; text-align: left; }
  .muted { color:#6c757d; }
  .pill { display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; }
  .pill-warn { background:#fff3cd; color:#664d03; border:1px solid #ffecb5; }
</style>
</head>
<body>
<h1>Productos filtrados por bodegas parametrizadas</h1>
<table>
  <thead>
    <tr>
      <th>Código producto</th>
      <th>Cantidad (DBF)</th>
      <th>Costo</th>
      <th>Descuento (%)</th>
    </tr>
  </thead>
  <tbody>
    <?php
    if (empty($agg)) {
        echo '<tr><td colspan="5" class="muted">No se encontraron registros del DBF para las bodegas parametrizadas.</td></tr>';
    } else {
        foreach ($agg as $code => $qty) {
            $p      = $productosMap[$code] ?? null;
            $subId  = $p['SubId']    ?? null;
            $costo  = $p['ProCosto'] ?? null;

            $descuento = 0.0;
            if ($subId !== null && isset($subDescMap[(int)$subId])) {
                $descuento = (float)$subDescMap[(int)$subId];
            }

            echo '<tr>';
            echo '<td>' . ($code) . '</td>';
            echo '<td>' . number_format((float)$qty, 0, ',', '.') . '</td>';
            echo '<td>' . ($costo !== null ? number_format((float)$costo, 0, ',', '.') : '<span class="muted">—</span>') . '</td>';
            echo '<td>' . number_format((float)$descuento, 0, ',', '.') . '</td>';
            echo '</tr>';
        }
    }
    ?>
  </tbody>
</table>
</body>
</html>
