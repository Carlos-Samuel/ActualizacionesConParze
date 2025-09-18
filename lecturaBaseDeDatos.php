<?php

header('Content-Type: text/html; charset=UTF-8');

require_once 'controladores/ConnectionParametrizacion.php';

$CODES = [
  'URL',
  'HORA_CARGUE_FULL',
  'FRECUENCIA_CARGUE_HORAS',
  'APIKEY',
  'REINTENTOS_API',
  'EMPRESAS_SELECCIONADAS',
  'BODEGAS_SELECCIONADAS',
  'SUBGRUPOS_CON_DESCUENTO'
];


function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

try {
    $con = ConnectionParametrizacion::getInstance()->getConnection();

    // --- Consulta de vigentes por código ---
    $vigStmt = $con->prepare("
        SELECT codigo, descripcion, valor, vigente, fecha_creacion, fecha_modificacion
        FROM parametros
        WHERE codigo = ? AND vigente = TRUE
        ORDER BY fecha_modificacion DESC
        LIMIT 1
    ");

    // --- Consulta de historial por código ---
    $histStmt = $con->prepare("
        SELECT codigo, descripcion, valor, vigente, fecha_creacion, fecha_modificacion
        FROM parametros
        WHERE codigo = ?
        ORDER BY fecha_modificacion DESC, fecha_creacion DESC
    ");

    $vigentes = [];
    $historial = [];
    $codesSinVigente = [];

    foreach ($CODES as $code) {
        // Vigente
        $vigStmt->bind_param("s", $code);
        $vigStmt->execute();
        $res = $vigStmt->get_result();
        $row = $res->fetch_assoc();
        if ($row) {
            $vigentes[$code] = $row;
        } else {
            $codesSinVigente[] = $code;
        }

        // Historial
        $histStmt->bind_param("s", $code);
        $histStmt->execute();
        $resH = $histStmt->get_result();
        $historial[$code] = $resH->fetch_all(MYSQLI_ASSOC);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo "<h2>Error</h2>";
    echo "<pre>" . h($e->getMessage()) . "</pre>";
    exit;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Parámetros – Vigentes e Historial</title>
<style>
  body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin: 20px; }
  h1, h2 { margin: 0.2rem 0; }
  .warn { background: #fff3cd; color: #664d03; border: 1px solid #ffecb5; padding: 10px; border-radius: 6px; margin: 10px 0; }
  .ok   { background: #e7f7ee; color: #0f5132; border: 1px solid #badbcc; padding: 10px; border-radius: 6px; margin: 10px 0; }
  table { border-collapse: collapse; width: 100%; margin: 10px 0 24px; }
  th, td { border: 1px solid #ddd; padding: 8px; font-size: 14px; }
  th { background: #f6f8fa; text-align: left; }
  code { background: #f6f8fa; padding: 2px 4px; border-radius: 4px; }
  .pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; }
  .pill-true { background:#d1e7dd; color:#0f5132; border:1px solid #badbcc; }
  .pill-false{ background:#f8d7da; color:#842029; border:1px solid #f5c2c7; }
  .muted { color:#6c757d; }
</style>
</head>
<body>

<h1>Parámetros configurados</h1>
<p class="muted">Códigos: 
  <?php foreach ($CODES as $c) echo "<code>".h($c)."</code> "; ?>
</p>

<?php if (count($vigentes) === 0): ?>
  <div class="warn">
    ⚠️ No hay <strong>ningún</strong> valor vigente para los parámetros listados. Registra al menos uno.
  </div>
<?php endif; ?>

<?php if (!empty($codesSinVigente)): ?>
  <div class="warn">
    ⚠️ Sin valor vigente para: 
    <?php foreach ($codesSinVigente as $c) echo "<code>".h($c)."</code> "; ?>
  </div>
<?php else: ?>
  <div class="ok">✅ Todos los parámetros tienen un valor vigente.</div>
<?php endif; ?>

<h2>Valores vigentes</h2>
<table>
  <thead>
    <tr>
      <th>Código</th>
      <th>Descripción</th>
      <th>Valor</th>
      <th>Vigente</th>
      <th>Fecha creación</th>
      <th>Fecha modificación</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($CODES as $code): ?>
      <?php if (isset($vigentes[$code])): 
        $v = $vigentes[$code]; ?>
        <tr>
          <td><?= h($v['codigo']) ?></td>
          <td><?= h($v['descripcion']) ?></td>
          <td><?= nl2br(h($v['valor'])) ?></td>
          <td><span class="pill pill-true">TRUE</span></td>
          <td><?= h($v['fecha_creacion']) ?></td>
          <td><?= h($v['fecha_modificacion']) ?></td>
        </tr>
      <?php else: ?>
        <tr>
          <td><?= h($code) ?></td>
          <td class="muted" colspan="4">—</td>
          <td><span class="pill pill-false">SIN VIGENTE</span></td>
        </tr>
      <?php endif; ?>
    <?php endforeach; ?>
  </tbody>
</table>

<h2>Historial (todas las versiones guardadas)</h2>

<?php foreach ($CODES as $code): ?>
  <h3><code><?= h($code) ?></code></h3>
  <?php if (!empty($historial[$code])): ?>
    <table>
      <thead>
        <tr>
          <th>Descripción</th>
          <th>Valor</th>
          <th>Vigente</th>
          <th>Fecha creación</th>
          <th>Fecha modificación</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($historial[$code] as $row): ?>
          <tr>
            <td><?= h($row['descripcion']) ?></td>
            <td><?= nl2br(h($row['valor'])) ?></td>
            <td>
              <?php if ($row['vigente']): ?>
                <span class="pill pill-true">TRUE</span>
              <?php else: ?>
                <span class="pill pill-false">FALSE</span>
              <?php endif; ?>
            </td>
            <td><?= h($row['fecha_creacion']) ?></td>
            <td><?= h($row['fecha_modificacion']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="muted">Sin registros para este código.</div>
  <?php endif; ?>
<?php endforeach; ?>

</body>
</html>
