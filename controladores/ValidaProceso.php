<?php
date_default_timezone_set('America/Mexico_City');
require_once 'validaBitacora.php';
require_once 'functions/generacionReporte.php';

$code_horaDiaFull       = 'HORA_CARGUE_FULL';
$code_cadaHoras         = 'FRECUENCIA_CARGUE_HORAS';
$code_numeroReintentos  = 'REINTENTOS_API';
$code_minutosReintento  = 'TIEMPO_ENTRE_REINTENTOS';

$tipoDeCargue; // Tipo de cargue a validar en la bitácora

// 1. Obtener fecha y hora del sistema
$fechaActual = date('Y-m-d') ;
$horaActual = date('H') . ':00:00'; // Solo la hora, sin minutos ni segundos

// 2. Constantes de configuración
$horaDiaFull       = obtenerParametro($code_horaDiaFull);        // Ejemplo: proceso FULL a las 3 AM
$cadaHoras         = obtenerParametro($code_cadaHoras);          // Cada cuántas horas se corre el DELTA
$minutosReintento  = obtenerParametro($code_minutosReintento);   // Minutos entre reintentos
$numeroReintentos  = obtenerParametro($code_numeroReintentos);   // Máximo de reintentos
$reintento        = 0;          // Contador de reintentos

echo $fechaActual, $horaActual, $horaDiaFull ;

function existeBitacora($fechaActual, $horaActual):bool {
    // Aquí iría la lógica para validar la bitácora
    return obtenerBitacora($fechaActual, $horaActual);

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
     echo "Ejecutando proceso $mode...\n";
    
    return generarReporteInventario($id, $mode);
}

// 5. Control de ejecución y reintentos
function controlarEjecucion($tipoProceso, $minutosReintento, $numeroReintentos) {
    global $reintento;
    global $fechaActual ;
    global $horaActual ;

    
    do {
        $id_bitacora = registraBitacora($tipoProceso, $fechaActual, $horaActual, $reintento );
        $exito = ejecutarProceso($tipoProceso,$id_bitacora);

        if ($exito) {
            echo "✅ Proceso $tipoProceso terminado correctamente.\n";
            return;
        }

        echo "❌ Error en proceso $tipoProceso. Reintento #$reintento...\n";
        $reintento++;

        if ($reintento >= $numeroReintentos) {
            echo "⚠️ Se alcanzó el número máximo de reintentos. Terminando programa.\n";
            return;
        }
        echo "⏳ Esperando $minutosReintento minutos antes del siguiente intento...\n";
        sleep($minutosReintento * 60); // Esperar antes del siguiente intento

    } while (!$exito);
}

// 6. Rutina principal

if (existeBitacora($fechaActual, $horaActual)){
    echo "⏹️ Ya se ejecutó el proceso a las $horaActual del día $fechaActual. Terminando.\n";
} else{
    if (debeEjecutarFull($horaActual, $horaDiaFull)) {
        echo "🔄 Es momento de ejecutar el proceso FULL.\n";
        controlarEjecucion('FULL', $minutosReintento, $numeroReintentos);
    } elseif (debeEjecutarDelta($horaActual, $horaDiaFull, $cadaHoras)) {
        echo "🔄 Es momento de ejecutar el proceso DELTA.\n";
        controlarEjecucion('DELTA', $minutosReintento, $numeroReintentos);
    } else {
        echo "⏹️ No es momento de ejecutar ningún proceso. Terminando.\n";
    }
}    
?>