<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../ConnectionParametrizacion.php';
require_once __DIR__ . '/../Connection.php';
require_once 'bitacoraFunctions.php';
require_once __DIR__ . '/../bootstrap.php';

use XBase\TableReader;
mysqli_report(MYSQLI_REPORT_OFF);

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

function norm_code(string $s): string {
    // Reemplaza NBSP por espacio normal y trimea
    $s = str_replace("\xC2\xA0", ' ', $s);
    return trim($s);
}

function obtenerProductosPorCodigo(
    mysqli $con,
    array $codes,
    mysqli $conParam,
    int $id_bitacora,
    string $pEmp,
    string $pPre
): array {
    $codes = array_values(array_unique(array_filter(array_map(
        static fn($v) => norm_code((string)$v), $codes
    ), static fn($v) => $v !== '')));
    if (!$codes) {
        return [];
    }

    $empId = (int)$pEmp;
    $preId = (int)$pPre;

    $placeholders = implode(',', array_fill(0, count($codes), '?'));
    $sql = "SELECT p.ProCod as procod, p.SubId as subid, tpp.proprecio as proprecio
            FROM productos p
            JOIN tbl_prodprecio tpp ON tpp.proid = p.ProId
            WHERE p.empId = ? AND tpp.tabpreid = ? AND p.ProCod IN ($placeholders)";

    $st = $con->prepare($sql);
    if (!$st) {
        throw new RuntimeException("Error prepare productos: " . $con->error);
    }

    $types = 'ii' . str_repeat('s', count($codes));

    $params = [];
    $params[] = &$types;
    $params[] = &$empId;
    $params[] = &$preId;
    foreach ($codes as $k => $v) {
        $codes[$k] = norm_code((string)$v);
        $params[] = &$codes[$k];
    }

    if (!call_user_func_array([$st, 'bind_param'], $params)) {
        $err = $st->error;
        $st->close();
        throw new RuntimeException("Error bind_param productos: " . $err);
    }

    if (!$st->execute()) {
        $err = $st->error;
        $st->close();
        throw new RuntimeException("Error execute productos: " . $err);
    }

    $res = $st->get_result();
    $map = [];
    while ($row = $res->fetch_assoc()) {
        $code = norm_code((string)$row['procod']);
        $map[$code] = [
            'subid'    => isset($row['subid']) ? (int)$row['subid'] : null,
            'proprecio' => isset($row['proprecio']) ? (float)$row['proprecio'] : null,
        ];
    }
    $res->free();
    $st->close();

    return $map;
}


function ensure_export_dir(): void {
    if (!is_dir(EXPORT_DIR) && !mkdir(EXPORT_DIR, 0775, true) && !is_dir(EXPORT_DIR)) {
        throw new RuntimeException('No se pudo crear carpeta de exportación: ' . EXPORT_DIR);
    }
}

function leerDbfFiltrado(string $bodega): array {
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

        if ($bod === '' || $bodega != $bod) { 
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

        $cantidadPorProducto[$code] = $qty;
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

function fileSizeFormatted(string $path, int $decimales = 2): string
{
    $bytes = filesize($path);
    if ($bytes === false) {
        throw new RuntimeException("No se pudo obtener el tamaño de: $path");
    }

    if ($bytes < 1024 * 1024) {
        $kb = $bytes / 1024;
        return number_format($kb, $decimales) . ' KB';
    }

    $mb = $bytes / (1024 * 1024);
    return number_format($mb, $decimales) . ' MB';
}


function generarCsv(string $mode, array $rows, array $diffs, int &$noReg, string &$tamArchivo, string &$rutaArchivo): string {
    ensure_export_dir();
    $stamp = date('Ymd_His');
    $fname = "reporte_inventario_{$stamp}.csv";
    $path  = EXPORT_DIR . '/' . $fname;
    $fp = fopen($path, 'w');

    if ($fp === false) throw new RuntimeException("No se pudo crear CSV: $path");

    if ($mode === 'DELTA') {
        $rows = $diffs;
    }

    fputcsv($fp, ['ID_INTERNO','CANTIDAD','PRECIO_VENTA','PORCENTAJE_DESCUENTO'], CSV_DELIM);
    foreach ($rows as $code => $r) {
        if (!($r['costo'] === null || $r['costo'] === '' || !is_finite($r['costo']) || $r['costo'] == 0)) {
            fputcsv($fp, [
                $code,
                number_format((int)$r['qty'],0, '.', ''),
                number_format((int)$r['costo'], 0, '.', ''),
                number_format((int)$r['descuento'], 0, '.', ''),
            ], CSV_DELIM);
        }
    }

    fclose($fp);

    $noReg      = count($rows);
    $tamArchivo = fileSizeFormatted($path);
    $rutaArchivo = $fname;

    return $fname;
}


function generarReporteInventario(int $id_bitacora, string $mode): bool {

    $conParam = ConnectionParametrizacion::getInstance()->getConnection();
    $conParam->set_charset('utf8mb4');
    $conProd  = Connection::getInstance()->getConnection();
    $conProd->set_charset('utf8mb4');

    $noReg = 0;
    $tamArchivo = "";
    $rutaArchivo = "";

    registrar_paso($conParam, $id_bitacora, 'Llega a la función de generación de reporte');

    try {
        
        // 1) Parámetros
        $pEmp = getValorVigenteParametro($conParam, "EMPRESA");
        $pBod = getValorVigenteParametro($conParam, "BODEGA");
        $pPre = getValorVigenteParametro($conParam, "PRECIOS");
        $pSub = getValorVigenteParametro($conParam, "SUBGRUPOS_CON_DESCUENTO");
        $pUrl = getValorVigenteParametro($conParam, "URL");
        $pApikey = getValorVigenteParametro($conParam, "APIKEY");


        if (!$pEmp) throw new RuntimeException("No hay parámetro vigente para EMPRESA");
        //if (!$pSub) throw new RuntimeException("No hay parámetro vigente para SUBGRUPOS_CON_DESCUENTO");
        if (!$pPre) throw new RuntimeException("No hay parámetro vigente para PRECIOS");
        if (!$pUrl) throw new RuntimeException("No hay parámetro vigente para URL");
        if (!$pApikey) throw new RuntimeException("No hay parámetro vigente para APIKEY");


        $pEmp = (string)$pEmp['valor'];
        $pBod = (string)$pBod['valor'];
        $pPre = (string)$pPre['valor'];
        $pUrl = (string)$pUrl['valor'];
        $pApikey = (string)$pApikey['valor'];

        $subDescRaw = '';
        if (is_array($pSub) && array_key_exists('valor', $pSub)) {
            $subDescRaw = (string)$pSub['valor'];
        }

        $subDescMap = trim($subDescRaw) === '' ? [] : parse_subgrupos_descuento($subDescRaw);

        //$subDescMap = parse_subgrupos_descuento((string)$pSub['valor']);

        registrar_paso($conParam, $id_bitacora, 'Parametros obtenidos e inicia lectura DBF');

        // 2) DBF
        $dbfData = leerDbfFiltrado($pBod);
        $cantidadPorProducto = $dbfData['cantidadPorProducto'];
        $codes   = $dbfData['codes'];

        if (empty($codes)) {
            throw new RuntimeException("Archivo DBF no tiene filas en las bodegas seleccionadas.");
        }

        registrar_paso($conParam, $id_bitacora, 'Termina lectura DBF, inicia procesamiento inicial');

        // 3) Productos
        $productosMap = obtenerProductosPorCodigo($conProd, $codes, $conParam, $id_bitacora, $pEmp, $pPre);
        registrar_paso($conParam, $id_bitacora, 'Termina procesamiento inicial, inicia estado actual');

        // 4) Estado actual
        $current = [];
        foreach ($cantidadPorProducto as $code => $qty) {

            //registrar_paso($conParam, $id_bitacora, 'Procesando producto: ' . $code . ' con qty ' . $qty);

            $p      = $productosMap[$code] ?? null;
            $subId  = $p['subid'] ?? 0;
            $costo  = $p['proprecio'] ?? 0;
            //registrar_paso($conParam, $id_bitacora, 'Procesando producto: ' . $code . ' con costo ' . $costo . ' y subId ' . $subId);

            $desc = 0;
            if ($subId !== null && isset($subDescMap[(int)$subId])) {
                $desc = (float)$subDescMap[(int)$subId];
            }

            $current[$code] = [
                'qty'       => (int)$qty,
                'costo'     => $costo === null ? 0 : (int)$costo,
                'descuento' => (int)$desc,
            ];
            //registrar_paso($conParam, $id_bitacora, 'Procesando producto: ' . $code . ' con qty ' . $qty . ', costo ' . ($costo===null?'NULL':$costo) . ' y descuento ' . $desc);

        }

        registrar_paso($conParam, $id_bitacora, 'Termina estado actual, inicial cargar reporte previo');

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
        $csvFile = generarCsv($mode, $current, $diffs, $noReg, $tamArchivo, $rutaArchivo);

        registrar_paso($conParam, $id_bitacora, 'Termina generar csv con mode ' . $mode . ': ' . $csvFile . 
            " (total registros: " . count($current) . ", diffs: " . count($diffs) . ")");
        
        $up = $conParam->prepare(
            "UPDATE bitacora
            SET cantidad_registros_enviados = ?,
                tamaño_del_archivo          = ?,
                ruta_archivo                = ?
            WHERE id_bitacora = ?"
        );

        if ($up) {
            $up->bind_param('issi', $noReg, $tamArchivo, $rutaArchivo, $id_bitacora);
            $up->execute();
            $up->close();
        }

        // 8) Envío a API externa (cURL)
        registrar_paso($conParam, $id_bitacora, 'Inicia envío a API externa: ' . $pUrl);

        if (!is_file(EXPORT_DIR . '/' . $rutaArchivo)) {
            throw new RuntimeException("No existe el archivo para envío: " . EXPORT_DIR . "/" . $rutaArchivo);
        }

        $ch = curl_init();
        if ($ch === false) {
            throw new RuntimeException("No se pudo inicializar cURL");
        }

        // Archivo a enviar como multipart/form-data
        $cfile = new CURLFile(EXPORT_DIR . '/' . $rutaArchivo, 'text/csv', basename(EXPORT_DIR . '/' . $rutaArchivo));
        $postFields = ['file_inventory' => $cfile];

        curl_setopt_array($ch, [
            CURLOPT_URL            => $pUrl,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_HTTPHEADER     => [
                'Key: ' . $pApikey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HEADER         => false,
        ]);

        $raw = curl_exec($ch);
        $curlErrNo = curl_errno($ch);
        $curlErr   = curl_error($ch);
        $httpCode  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        registrar_paso($conParam, $id_bitacora, "Respuesta API HTTP=$httpCode; curlErrNo=$curlErrNo");

        if ($curlErrNo !== 0) {
            throw new RuntimeException("Error cURL ($curlErrNo): $curlErr");
        }

        if ($httpCode !== 200) {
            throw new RuntimeException("HTTP inesperado: $httpCode; body: " . mb_strimwidth((string)$raw, 0, 500, '...'));
        }

        $json = json_decode((string)$raw, true);
        if (!is_array($json)) {
            throw new RuntimeException("Respuesta no es JSON válido. Body: " . mb_strimwidth((string)$raw, 0, 500, '...'));
        }

        if (!array_key_exists('message', $json)) {
            throw new RuntimeException("JSON sin 'message'. Body: " . mb_strimwidth((string)$raw, 0, 500, '...'));
        }

        $expected = "El inventario de tu sitio se está cargando.";
        $message  = (string)$json['message'];

        registrar_paso($conParam, $id_bitacora, "Mensaje API: " . mb_strimwidth($message, 0, 200, '...'));

        if ($message !== $expected) {
            throw new RuntimeException("Mensaje inesperado: '$message' (se esperaba: '$expected')");
        }

        registrar_paso($conParam, $id_bitacora, 'Validación de respuesta API OK; guardando snapshot y actualizando bitácora');

        // 9) Persistir snapshot y actualizar bitácora como Exitoso
        guardarReportes($conParam, $id_bitacora, $current);

        $up = $conParam->prepare(
            "UPDATE bitacora
                resultado_del_envio         = 'Exitoso',
                fecha_hora_de_fin           = CURRENT_TIMESTAMP
            WHERE id_bitacora = ?"
        );

        if ($up) {
            $up->bind_param('i', $id_bitacora);
            $up->execute();
            $up->close();
        }

    } catch (Throwable $e) {
        registrar_paso($conParam, $id_bitacora, 'Error: ' . $e->getMessage());

        $up = $conParam->prepare(
            "UPDATE bitacora
            SET resultado_del_envio = 'Fallido',
                descripcion_error   = ?
            WHERE id_bitacora = ?"
        );

        if ($up) {
            $up->bind_param('si', $e->getMessage(), $id_bitacora);
            $up->execute();
            $up->close();
        }
        return false;
    }

    registrar_paso($conParam, $id_bitacora, 'Termina');
    return true;
}
