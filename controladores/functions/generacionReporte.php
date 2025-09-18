<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../ConnectionParametrizacion.php';
require_once __DIR__ . '/../Connection.php';
require_once 'bitacoraFunctions.php';
use XBase\TableReader;
mysqli_report(MYSQLI_REPORT_OFF);

/* =======================
   CONFIG
   ======================= */


const DBF_PATH     = "C:\\Users\\csamu\\OneDrive\\Escritorio\\Parze\\PRODEXIS.DBF";
const DBF_ENCODING = 'CP1252';

const PARAM_BODEGAS   = 'BODEGAS_SELECCIONADAS';
const PARAM_SUBGRUPOS = 'SUBGRUPOS_CON_DESCUENTO';

const EXPORT_DIR = __DIR__ . '/../../exports';
const CSV_DELIM  = ';';


/* =======================
   HELPERS
   ======================= */

function getValorVigenteParametro(mysqli $con, string $codigo): ?array {
    $sql = "SELECT valor
            FROM parametros
            WHERE codigo = ? AND vigente = TRUE
            ORDER BY fecha_modificacion DESC
            LIMIT 1";
    $st = $con->prepare($sql);
    if (!$st) throw new RuntimeException("Error prepare param: " . $con->error);
    $st->bind_param('s', $codigo);
    $st->execute();
    $res = $st->get_result();
    $row = $res->fetch_assoc() ?: null;
    $st->close();
    return $row;
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
    foreach (preg_split('/[,\;\|\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) as $p) {
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
            if ($sid !== null && $sid !== '') $map[(int)$sid] = (float)$pct;
        }
        if (!empty($map)) return $map;
    }
    $pairs = preg_split('/[,\;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
    $hasPairs = false;
    foreach ($pairs as $p) {
        if (strpos($p, ':') !== false) {
            [$sid, $pct] = array_map('trim', explode(':', $p, 2));
            if ($sid !== '') { $map[(int)$sid] = (float)str_replace('%','',$pct); $hasPairs = true; }
        }
    }
    if ($hasPairs) return $map;
    foreach (preg_split('/[,\;\|\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) as $sid) {
        $map[(int)$sid] = 0.0;
    }
    return $map;
}

function obtenerProductosPorCodigo(mysqli $con, array $codes, mysqli $conParam, int $id_bitacora): array {
    if (empty($codes)){
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($codes), '?'));
    $sql  = "SELECT ProCod, SubId, ProCosto FROM productos WHERE ProCod IN ($placeholders)";

    $st = $con->prepare($sql);
    
    if (!$st) throw new RuntimeException("Error prepare productos: " . $con->error);

    $types = str_repeat('s', count($codes));
    $st->bind_param($types, ...array_values($codes));
    $st->execute();
    $res = $st->get_result();
    
    $map = [];
    
    while ($row = $res->fetch_assoc()) {
        $code = (string)$row['ProCod'];
        $map[$code] = [
            'SubId'    => isset($row['SubId']) ? (int)$row['SubId'] : null,
            'ProCosto' => isset($row['ProCosto']) ? (float)$row['ProCosto'] : null,
        ];
    }
    $st->close();
    return $map;
}

function ensure_export_dir(): void {
    if (!is_dir(EXPORT_DIR) && !mkdir(EXPORT_DIR, 0775, true) && !is_dir(EXPORT_DIR)) {
        throw new RuntimeException('No se pudo crear carpeta de exportación: ' . EXPORT_DIR);
    }
}

function leerDbfFiltrado(array $bodegasSet): array {
    if (!file_exists(DBF_PATH)) {
        throw new RuntimeException("DBF no existe en ruta: " . DBF_PATH);
    }
    if (!is_readable(DBF_PATH)) {
        throw new RuntimeException("DBF sin permisos de lectura: " . DBF_PATH);
    }

    $tabla = null;
    try {
        $tabla = new TableReader(DBF_PATH, ['encoding' => DBF_ENCODING]);
    } catch (Throwable $e) {
        throw new RuntimeException("Fallo al abrir DBF: " . $e->getMessage());
    }

    $colProcod   = 'procod';
    $colBodcod   = 'bodcod';
    $colCantidad = 'existen';

    $cantidadPorProducto = []; 
    $codesSeen = [];

    while ($rec = $tabla->nextRecord()) {
        $bod = trim((string)$rec->get($colBodcod));

        if ($bod === '' || !isset($bodegasSet[$bod])) { 
            //Aqui se filtran por los registros sin bodegas o que las podegas no estan entre las parametrizadas
            continue; 
        }

        $code = trim((string)$rec->get($colProcod));

        if ($code === '') { 
            continue; 
        }

        $rawQty = (string)$rec->get($colCantidad);
        $qty = (int)str_replace(',', '.', $rawQty);

        if (!is_finite($qty)) { 
            continue; 
        }

        if (!isset($cantidadPorProducto[$code])) $cantidadPorProducto[$code] = 0;
        $cantidadPorProducto[$code] += $qty;
        $codesSeen[$code] = true;
    }
    $tabla->close();

    return ['cantidadPorProducto' => $cantidadPorProducto, 'codes' => array_keys($codesSeen)];
}

function cargarReportePrevio(mysqli $con): array {
    $snap = [];
    $res = $con->query("SELECT id_interno, cantidad, precio_venta, descuento FROM reportes");
    if (!$res) throw new RuntimeException("Error leyendo snapshot: " . $con->error);
    while ($row = $res->fetch_assoc()) {
        $snap[$row['id_interno']] = [
            'qty'       => (int)$row['cantidad'],
            'costo'     => (int)$row['precio_venta'],
            'descuento' => (int)$row['descuento'],
        ];
    }
    $res->close();
    return $snap;
}

function guardarReportes(mysqli $con, int $id_bitacora, array $current): void
{
    $stDelete= null;
    $stInsert = null;

    $con->begin_transaction();
    try {
        $sqlUpdate = "DELETE FROM reportes";
        $stDelete = $con->prepare($sqlUpdate);
        if (!$stDelete) {
            throw new RuntimeException("PREPARE fallo (UPDATE reportes): {$con->errno} {$con->error}");
        }
        if (!$stDelete->execute()) {
            throw new RuntimeException("EXECUTE fallo (UPDATE reportes): {$stDelete->errno} {$stDelete->error}");
        }
        $stDelete->close();
        $stDelete = null;

        $sqlInsert = "INSERT INTO reportes (id_bitacora, id_interno, cantidad, precio_venta, descuento)
                      VALUES (?, ?, ?, ?, ?)";
        $stInsert = $con->prepare($sqlInsert);
        if (!$stInsert) {
            throw new RuntimeException("PREPARE fallo (INSERT reportes con precio): {$con->errno} {$con->error}");
        }

        foreach ($current as $code => $r) {
            $qty   = (float)($r['qty'] ?? 0);
            $costo = $r['costo'] ?? 0;
            $desc  = (float)($r['descuento'] ?? 0);

            $costo = (float)$costo;
            if (!$stInsert->bind_param('isddd', $id_bitacora, $code, $qty, $costo, $desc)) {
                throw new RuntimeException("BIND fallo (INSERT con precio) code={$code}: {$stInsert->errno} {$stInsert->error}");
            }
            if (!$stInsert->execute()) {
                throw new RuntimeException("EXECUTE fallo (INSERT con precio) code={$code}: {$stInsert->errno} {$stInsert->error}");
            }
        }

        $con->commit();

        if ($stDelete !== null)     { $stDelete->close(); }
        if ($stInsert !== null)     { $stInsert->close(); }
    } catch (Throwable $e) {
        $con->rollback();
        throw new RuntimeException("guardarReportes(id_bitacora={$id_bitacora}) falló: " . $e->getMessage());
    } finally {

    }
}

function generarCsv(string $mode, array $rows, array $diffs): string {
    ensure_export_dir();
    $stamp = date('Ymd_His');
    $fname = "reporte_inventario_{$stamp}.csv";
    $path  = EXPORT_DIR . '/' . $fname;
    $fp = fopen($path, 'w');

    if ($fp === false) throw new RuntimeException("No se pudo crear CSV: $path");

    if ($mode === 'DELTA') {
        $rows = $diffs;
    }

    fputcsv($fp, ['code','qty','costo','descuento'], CSV_DELIM);
    foreach ($rows as $code => $r) {
        // En modo diff, todo. En modo completo, solo los que tienen qty > 0
        if ($mode === 'DELTA' || $r['qty'] != 0) {
            fputcsv($fp, [
                $code,
                number_format((float)$r['qty'],      0, '.', ''),
                $r['costo'] === number_format((int)$r['costo'], 0, '.', ''),
                number_format((float)$r['descuento'], 0, '.', ''),
            ], CSV_DELIM);
        }
    }

    fclose($fp);
    return $fname;
}


function generarReporteInventario(int $id_bitacora, string $mode): void {

    $conParam = ConnectionParametrizacion::getInstance()->getConnection();
    $conParam->set_charset('utf8mb4');
    $conProd  = Connection::getInstance()->getConnection();
    $conProd->set_charset('utf8mb4');

    registrar_paso($conParam, $id_bitacora, 'Llega a la función de generación de reporte');

    try {
        
        // 1) Parámetros
        $pBod = getValorVigenteParametro($conParam, PARAM_BODEGAS);
        $pSub = getValorVigenteParametro($conParam, PARAM_SUBGRUPOS);

        if (!$pBod) throw new RuntimeException("No hay parámetro vigente para ".PARAM_BODEGAS);
        if (!$pSub) throw new RuntimeException("No hay parámetro vigente para ".PARAM_SUBGRUPOS);

        $bodegasSet = parse_bodegas((string)$pBod['valor']);
        $subDescMap = parse_subgrupos_descuento((string)$pSub['valor']);

        if (empty($bodegasSet)) throw new RuntimeException("PARÁMETRO BODEGAS vacío → no habrá registros del DBF.");

        registrar_paso($conParam, $id_bitacora, 'Parametros obtenidos e inicia lectura DBF');

        // 2) DBF
        $dbfData = leerDbfFiltrado($bodegasSet);
        $cantidadPorProducto = $dbfData['cantidadPorProducto'];
        $codes   = $dbfData['codes'];
        if (empty($codes)) {
            throw new RuntimeException("Archivo DBF no tiene filas en las bodegas seleccionadas.");
        }

        registrar_paso($conParam, $id_bitacora, 'Termina lectura DBF, inicia procesamiento');

        // 3) Productos
        $productosMap = obtenerProductosPorCodigo($conProd, $codes, $conParam, $id_bitacora);
        $missingProducts = array_values(array_diff($codes, array_keys($productosMap)));


        // 4) Estado actual
        $current = [];
        foreach ($cantidadPorProducto as $code => $qty) {
            $p      = $productosMap[$code] ?? null;
            $subId  = $p['SubId']    ?? null;
            $costo  = $p['ProCosto'] ?? null;

            $desc = 0;
            if ($subId !== null && isset($subDescMap[(int)$subId])) {
                $desc = (float)$subDescMap[(int)$subId];
            }

            $current[$code] = [
                'qty'       => (int)$qty,
                'costo'     => $costo === null ? 0 : (int)$costo,
                'descuento' => (int)$desc,
            ];
        }

        registrar_paso($conParam, $id_bitacora, 'Termina obtener productos');

        // 5) Reporte previo
        $snapshot = cargarReportePrevio($conParam);

        registrar_paso($conParam, $id_bitacora, 'Termina cargar reporte previo');

        // 6) Diferentes
        $diffs = [];

        $i = 0;

        foreach ($current as $code => $r) {
            $prev = $snapshot[$code];

            // Normalizamos valores previos y actuales
            $prevQty   = (int)($prev['qty'] ?? 0);
            $currQty   = (int)($r['qty']   ?? 0);

            $prevCosto = $prev['costo'] ?? null; // puede ser NULL
            $currCosto = $r['costo']    ?? null; // puede ser NULL

            $prevDesc  = (int)($prev['descuento'] ?? 0);
            $currDesc  = (int)($r['descuento']    ?? 0);

            // Cambios con tolerancia por redondeo
            $qtyChanged   = round($prevQty,   0)   !== round($currQty,   0);
            $descChanged  = round($prevDesc,  0)  !== round($currDesc,  0);

            // Costo: cambia si varía la nulidad o el valor (con tolerancia)
            $costoChanged = (($prevCosto === null) xor ($currCosto === null)) ||
                            ($prevCosto !== null && $currCosto !== null &&
                            round((int)$prevCosto, 0) !== round((int)$currCosto, 0));

            if ($qtyChanged || $costoChanged || $descChanged) {
                if ($i == 0){
                    $i = 1;
                    //registrar_paso($conParam, $id_bitacora, 'Lo diferente fue: ' . 
                    //    "qtyChanged={$qtyChanged} (prev={$prevQty} curr={$currQty}), " .
                    //    "costoChanged={$costoChanged} (prev=" . ($prevCosto===null?'NULL':$prevCosto) . " curr=" . ($currCosto===null?'NULL':$currCosto) . "), " .
                    //    "descChanged={$descChanged} (prev={$prevDesc} curr={$currDesc})"
                    //);
                }
                $diffs[$code] = $r;
            }
        }

        registrar_paso($conParam, $id_bitacora, 'Termina diferencias');

        // 7) CSV + persistencia
        $csvFile = generarCsv($mode, $current, $diffs);

        registrar_paso($conParam, $id_bitacora, 'Termina generar csv con mode ' . $mode . ': ' . $csvFile . 
            " (total registros: " . count($current) . ", diffs: " . count($diffs) . ")");
        
        guardarReportes($conParam, $id_bitacora, $current);

    } catch (Throwable $e) {
        registrar_paso($conParam, $id_bitacora, 'Error: ' . $e->getMessage());
    }

    registrar_paso($conParam, $id_bitacora, 'termina');

    $up = $conParam->prepare("UPDATE bitacora SET fecha_hora_de_fin = CURRENT_TIMESTAMP WHERE id_bitacora = ?");
    if ($up) {
        $up->bind_param('i', $id_bitacora);
        $up->execute();
        $up->close();
    }

}
