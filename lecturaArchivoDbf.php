<?php
require 'vendor/autoload.php';

use XBase\TableReader;

try {
    // Ruta al archivo DBF
    $ruta = "C:\\Users\\rapar\\OneDrive\\Emprendimiento\\PROYECTOS\\ActualizacioninventarioAPI\\Parze\\PRODEXIS.DBF";
    
    // Abrir la tabla (ajusta la codificación si es necesario)
    $tabla = new TableReader($ruta, [
        'encoding' => 'CP1252'
    ]);

    // Encabezados
    $columnas = [];
    foreach ($tabla->getColumns() as $col) {
        $columnas[] = $col->getName();
    }

    // Imprime encabezados
    echo implode(" | ", $columnas) . PHP_EOL;
    echo "<br>";

    // Imprime cada registro en una línea
    while ($registro = $tabla->nextRecord()) {
        $valores = [];
        //if ($registro->get('procod') == '9010002') {
            foreach ($columnas as $columna) {
                $valores[] = $registro->get($columna);
            }
            echo implode(" | ", $valores) . PHP_EOL;
            echo "<br>";
        //}

    }

    $tabla->close();

} catch (Exception $e) {
    echo "Error al leer el archivo DBF: " . $e->getMessage();
}
