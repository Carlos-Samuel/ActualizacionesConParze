<?php
date_default_timezone_set('America/Mexico_City');
require_once 'validaBitacora.php';
require_once 'functions/generacionReporte.php';

$code_horaDiaFull = 'HORA_CARGUE_FULL';
$code_cadaHoras = 'FRECUENCIA_CARGUE_HORAS';
$code_numeroReintentos = 'REINTENTOS_API';
$code_minutosReintento = 'TIEMPO_ENTRE_REINTENTOS';


// 1. Obtener fecha y hora del sistema
$fechaActual = date('Y-m-d');
$horaActual = date('H') . ':00:00'; // Solo la hora, sin minutos ni segundos


// 2. Constantes de configuración
$horaDiaFull       = obtenerParametro($code_horaDiaFull);        // Ejemplo: proceso FULL a las 3 AM
$cadaHoras         = obtenerParametro($code_cadaHoras);          // Cada cuántas horas se corre el DELTA
$minutosReintento  = obtenerParametro($code_minutosReintento);   // Minutos entre reintentos
$numeroReintentos  = obtenerParametro($code_numeroReintentos);   // Máximo de reintentos
$reintentos        = 0;          // Contador de reintentos
echo $horaDiaFull ;

function validaBitacora($fechaActual, $horaActual, $tipoDeCargue) {
    // Aquí iría la lógica para validar la bitácora
    return obtenerBitacora($fechaActual, $horaActual, $tipoDeCargue);

}   
// 3. Determinar si se debe correr FULL o DELTA
function debeEjecutarFull($horaActual, $horaDiaFull) {
    return $horaActual === $horaDiaFull;
}

function debeEjecutarDelta($horaActual, $horaDiaFull, $cadaHoras) {
    $horaActualInt   = (int)substr($horaActual, 0, 2);
    $horaFullInt     = (int)substr($horaDiaFull, 0, 2);
    $diferenciaHoras = $horaActualInt - $horaFullInt;

    return $diferenciaHoras > 0 && ($diferenciaHoras % $cadaHoras === 0);
}

// 4. Simulación de ejecución del proceso
function ejecutarProceso($mode, $id) {
    // echo "Ejecutando proceso $tipo...\n";
    
    return generarReporteInventario($id, $mode);
}

// 5. Control de ejecución y reintentos
function controlarEjecucion($tipoProceso, $minutosReintento, $numeroReintentos) {
    global $reintentos;

    
    do {
        $id_bitacora = registrarBitacora($tipoProceso, $fechaActual, $horaActual, $reintento );
        $exito = ejecutarProceso($tipoProceso,$id_bitacora);

        if ($exito) {
            // "✅ Proceso $tipoProceso terminado correctamente.\n";
            return;
        }

        $reintentos++;
        // "❌ Error en proceso $tipoProceso. Reintento #$reintentos...\n";

        if ($reintentos >= $numeroReintentos) {
            // "⚠️ Se alcanzó el número máximo de reintentos. Terminando programa.\n";
            return;
        }

        sleep($minutosReintento * 60); // Esperar antes del siguiente intento

    } while (!$exito);
}

// 6. Decisión final
if (debeEjecutarFull($horaActual, $horaDiaFull)) {
    controlarEjecucion('FULL', $minutosReintento, $numeroReintentos);
} elseif (debeEjecutarDelta($horaActual, $horaDiaFull, $cadaHoras)) {
    controlarEjecucion('DELTA', $minutosReintento, $numeroReintentos);
} else {
    //echo "⏹️ No es momento de ejecutar ningún proceso. Terminando.\n";
}
?>