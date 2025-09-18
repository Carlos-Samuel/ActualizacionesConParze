<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/controladores/ConnectionParametrizacion.php';
require_once __DIR__ . '/controladores/Connection.php';

use XBase\TableReader;

/* =======================
   CONFIG
   ======================= */

const DBF_PATH     = "C:\\Users\\csamu\\OneDrive\\Escritorio\\Parze\\PRODEXIS.DBF";
const DBF_ENCODING = 'CP1252';

const PRODUCTS_TABLE = 'productos';
const COL_CODE       = 'ProCod';
const COL_SUBID      = 'SubId';
const COL_COSTO      = 'ProCosto';

const PARAM_BODEGAS   = 'BODEGAS_SELECCIONADAS';
const PARAM_SUBGRUPOS = 'SUBGRUPOS_CON_DESCUENTO';

const EXPORT_DIR = __DIR__ . '/../exports';
const CSV_DELIM  = ';';

/* =======================
   HELPERS
   ======================= */

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

function row_hash(float $qty, ?float $costo, float $desc): string {
    $qtyS   = number_format($qty,   3, '.', '');
    $costoS = $costo === null ? 'NULL' : number_format($costo, 2, '.', '');
    $descS  = number_format($desc,  3, '.', '');
    return hash('sha256', $qtyS.'|'.$costoS.'|'.$descS);
}

function normalize_mode(?string $modo): string {
    $m = strtolower(trim((string)$modo));
    if (in_array($m, ['cambios', 'reciente', 'recientes', 'diff', 'delta', 'diferencias'], true)) return 'diff';
    return 'full';
}

function ensure_export_dir(): void {
    if (!is_dir(EXPORT_DIR) && !mkdir(EXPORT_DIR, 0775, true) && !is_dir(EXPORT_DIR)) {
        throw new RuntimeException('No se pudo crear carpeta de exportación: ' . EXPORT_DIR);
    }
}

function ensure_report_tables(mysqli $con): void {
    $sqls = [
        "CREATE TABLE IF NOT EXISTS inventario_snapshot (
            code        VARCHAR(64) PRIMARY KEY,
            qty         DECIMAL(18,3) NOT NULL,
            costo       DECIMAL(18,2) NULL,
            descuento   DECIMAL(6,3)  NOT NULL DEFAULT 0,
            hash        CHAR(64)      NOT NULL,
            updated_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS inventario_report_runs (
            id           BIGINT AUTO_INCREMENT PRIMARY KEY,
            run_type     ENUM('full','diff') NOT NULL,
            created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            total_rows   INT NOT NULL,
            diff_rows    INT NOT NULL DEFAULT 0,
            csv_filename VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS inventario_report_diffs (
            id             BIGINT AUTO_INCREMENT PRIMARY KEY,
            run_id         BIGINT NOT NULL,
            code           VARCHAR(64) NOT NULL,
            change_type    ENUM('NEW','UPDATED','DELETED') NOT NULL,
            prev_qty       DECIMAL(18,3) NULL,
            new_qty        DECIMAL(18,3) NULL,
            prev_costo     DECIMAL(18,2) NULL,
            new_costo      DECIMAL(18,2) NULL,
            prev_descuento DECIMAL(6,3)  NULL,
            new_descuento  DECIMAL(6,3)  NULL,
            CONSTRAINT fk_runs FOREIGN KEY (run_id) REFERENCES inventario_report_runs(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
    foreach ($sqls as $sql) {
        if (!$con->query($sql)) throw new RuntimeException("Error creando tablas de reporte: " . $con->error);
    }
}

function leer_dbf_filtrado(array $bodegasSet): array {
    $tabla = new TableReader(DBF_PATH, ['encoding' => DBF_ENCODING]);
    $colProcod   = 'procod';
    $colBodcod   = 'bodcod';
    $colCantidad = 'existen';

    $agg = []; $codesSeen = [];
    while ($rec = $tabla->nextRecord()) {
        $bod = trim((string)$rec->get($colBodcod));
        if ($bod === '' || !isset($bodegasSet[$bod])) continue;

        $code = trim((string)$rec->get($colProcod));
        if ($code === '') continue;

        $qty = (float)str_replace(',', '.', (string)$rec->get($colCantidad));
        if (!isset($agg[$code])) $agg[$code] = 0.0;
        $agg[$code] += $qty;
        $codesSeen[$code] = true;
    }
    $tabla->close();

    return ['agg' => $agg, 'codes' => array_keys($codesSeen)];
}

function load_snapshot(mysqli $con): array {
    $snap = [];
    $res = $con->query("SELECT code, qty, costo, descuento, hash FROM inventario_snapshot");
    if (!$res) throw new RuntimeException("Error leyendo snapshot: " . $con->error);
    while ($row = $res->fetch_assoc()) {
        $snap[$row['code']] = [
            'qty'       => (float)$row['qty'],
            'costo'     => $row['costo'] !== null ? (float)$row['costo'] : null,
            'descuento' => (float)$row['descuento'],
            'hash'      => (string)$row['hash'],
        ];
    }
    $res->close();
    return $snap;
}

function save_snapshot(mysqli $con, array $current): void {
    if (!$con->query("TRUNCATE TABLE inventario_snapshot")) {
        throw new RuntimeException("Error truncando snapshot: " . $con->error);
    }
    $sql = "INSERT INTO inventario_snapshot (code, qty, costo, descuento, hash) VALUES (?, ?, ?, ?, ?)";
    $st = $con->prepare($sql);
    if (!$st) throw new RuntimeException("Error prepare snapshot: " . $con->error);

    foreach ($current as $code => $r) {
        $qty   = $r['qty'];
        $costo = $r['costo'];
        $desc  = $r['descuento'];
        $hash  = $r['hash'];
        $st->bind_param('sddds', $code, $qty, $costo, $desc, $hash);
        if (!$st->execute()) throw new RuntimeException("Error insert snapshot($code): " . $st->error);
    }
    $st->close();
}

function insert_run(mysqli $con, string $type, int $totalRows, int $diffRows, string $csv): int {
    $sql = "INSERT INTO inventario_report_runs (run_type, total_rows, diff_rows, csv_filename) VALUES (?, ?, ?, ?)";
    $st = $con->prepare($sql);
    if (!$st) throw new RuntimeException("Error prepare run: " . $con->error);
    $st->bind_param('siis', $type, $totalRows, $diffRows, $csv);
    if (!$st->execute()) throw new RuntimeException("Error insert run: " . $st->error);
    $id = (int)$st->insert_id;
    $st->close();
    return $id;
}

function insert_diffs(mysqli $con, int $runId, array $diffs): void {
    if (empty($diffs)) return;
    $sql = "INSERT INTO inventario_report_diffs
            (run_id, code, change_type, prev_qty, new_qty, prev_costo, new_costo, prev_descuento, new_descuento)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $st = $con->prepare($sql);
    if (!$st) throw new RuntimeException("Error prepare diffs: " . $con->error);
    foreach ($diffs as $d) {
        $st->bind_param(
            'issdddddd',
            $runId,
            $d['code'],
            $d['type'],
            $d['prev_qty'],
            $d['new_qty'],
            $d['prev_costo'],
            $d['new_costo'],
            $d['prev_descuento'],
            $d['new_descuento'],
        );
        if (!$st->execute()) throw new RuntimeException("Error insert diff({$d['code']}): " . $st->error);
    }
    $st->close();
}

function write_csv(string $mode, array $rows, array $diffs): string {
    ensure_export_dir();
    $stamp = date('Ymd_His');
    $fname = ($mode === 'diff' ? "reporte_inventario_diff_{$stamp}.csv"
                               : "reporte_inventario_full_{$stamp}.csv");
    $path  = EXPORT_DIR . DIRECTORY_SEPARATOR . $fname;
    $fp = fopen($path, 'w');
    if ($fp === false) throw new RuntimeException("No se pudo crear CSV: $path");

    if ($mode === 'diff') {
        fputcsv($fp, ['change_type','code','prev_qty','new_qty','prev_costo','new_costo','prev_descuento','new_descuento'], CSV_DELIM);
        foreach ($diffs as $d) {
            fputcsv($fp, [
                $d['type'],
                $d['code'],
                $d['prev_qty']       === null ? '' : number_format((float)$d['prev_qty'], 3, '.', ''),
                $d['new_qty']        === null ? '' : number_format((float)$d['new_qty'],  3, '.', ''),
                $d['prev_costo']     === null ? '' : number_format((float)$d['prev_costo'], 2, '.', ''),
                $d['new_costo']      === null ? '' : number_format((float)$d['new_costo'],  2, '.', ''),
                $d['prev_descuento'] === null ? '' : number_format((float)$d['prev_descuento'], 3, '.', ''),
                $d['new_descuento']  === null ? '' : number_format((float)$d['new_descuento'],  3, '.', ''),
            ], CSV_DELIM);
        }
    } else {
        fputcsv($fp, ['code','qty','costo','descuento'], CSV_DELIM);
        foreach ($rows as $code => $r) {
            fputcsv($fp, [
                $code,
                number_format((float)$r['qty'],      3, '.', ''),
                $r['costo'] === null ? '' : number_format((float)$r['costo'], 2, '.', ''),
                number_format((float)$r['descuento'], 3, '.', ''),
            ], CSV_DELIM);
        }
    }

    fclose($fp);
    return $fname;
}

/* =======================
   FUNCIÓN PÚBLICA
   ======================= */

/**
 * Genera y persiste el reporte.
 * @param string $modo 'completo'|'cambios' (se normaliza a 'full'|'diff')
 * @return array { ok: bool, mode, filename?, rows, diff_rows, error? }
 */
function generarReporteInventario(string $modo = 'completo'): array {
    $mode = normalize_mode($modo);

    $conParam = ConnectionParametrizacion::getInstance()->getConnection();
    $conParam->set_charset('utf8mb4');
    $conProd  = Connection::getInstance()->getConnection();
    $conProd->set_charset('utf8mb4');

    try {
        ensure_report_tables($conParam);

        $pBod = get_param_vigente($conParam, PARAM_BODEGAS);
        $pSub = get_param_vigente($conParam, PARAM_SUBGRUPOS);
        if (!$pBod) throw new RuntimeException("No hay parámetro vigente para ".PARAM_BODEGAS);
        if (!$pSub) throw new RuntimeException("No hay parámetro vigente para ".PARAM_SUBGRUPOS);

        $bodegasSet = parse_bodegas((string)$pBod['valor']);
        $subDescMap = parse_subgrupos_descuento((string)$pSub['valor']);

        $dbfData = leer_dbf_filtrado($bodegasSet);
        $agg     = $dbfData['agg'];
        $codes   = $dbfData['codes'];

        $productosMap = fetch_products_by_codes($conProd, PRODUCTS_TABLE, $codes, COL_CODE, COL_SUBID, COL_COSTO);

        $current = [];
        foreach ($agg as $code => $qty) {
            $p      = $productosMap[$code] ?? null;
            $subId  = $p['SubId']    ?? null;
            $costo  = $p['ProCosto'] ?? null;

            $desc = 0.0;
            if ($subId !== null && isset($subDescMap[(int)$subId])) $desc = (float)$subDescMap[(int)$subId];

            $current[$code] = [
                'qty'       => (float)$qty,
                'costo'     => $costo === null ? null : (float)$costo,
                'descuento' => (float)$desc,
            ];
            $current[$code]['hash'] = row_hash($current[$code]['qty'], $current[$code]['costo'], $current[$code]['descuento']);
        }

        $snapshot = load_snapshot($conParam);

        $diffs = [];
        foreach ($current as $code => $r) {
            if (!isset($snapshot[$code])) {
                $diffs[] = [
                    'code' => $code, 'type' => 'NEW',
                    'prev_qty'=>null,'new_qty'=>$r['qty'],
                    'prev_costo'=>null,'new_costo'=>$r['costo'],
                    'prev_descuento'=>null,'new_descuento'=>$r['descuento'],
                ];
            } else {
                $prev = $snapshot[$code];
                if ($prev['hash'] !== $r['hash']) {
                    $diffs[] = [
                        'code' => $code, 'type' => 'UPDATED',
                        'prev_qty'=>$prev['qty'], 'new_qty'=>$r['qty'],
                        'prev_costo'=>$prev['costo'], 'new_costo'=>$r['costo'],
                        'prev_descuento'=>$prev['descuento'], 'new_descuento'=>$r['descuento'],
                    ];
                }
            }
        }
        foreach ($snapshot as $code => $prev) {
            if (!isset($current[$code])) {
                $diffs[] = [
                    'code' => $code, 'type' => 'DELETED',
                    'prev_qty'=>$prev['qty'], 'new_qty'=>null,
                    'prev_costo'=>$prev['costo'], 'new_costo'=>null,
                    'prev_descuento'=>$prev['descuento'], 'new_descuento'=>null,
                ];
            }
        }

        $csvFile   = write_csv($mode, $current, $diffs);
        $totalRows = count($current);
        $diffRows  = count($diffs);

        $conParam->begin_transaction();
        try {
            $runId = insert_run($conParam, $mode === 'diff' ? 'diff' : 'full', $totalRows, $diffRows, $csvFile);
            insert_diffs($conParam, $runId, $diffs);
            save_snapshot($conParam, $current);
            $conParam->commit();
        } catch (Throwable $txe) {
            $conParam->rollback();
            throw $txe;
        }

        return [
            'ok'        => true,
            'mode'      => $mode,
            'filename'  => 'exports/' . $csvFile,
            'rows'      => $totalRows,
            'diff_rows' => $diffRows,
        ];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}
