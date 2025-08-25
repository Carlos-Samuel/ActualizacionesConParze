<?php
    header('Content-Type: application/json');

    require_once 'Connection.php';
    require_once realpath(__DIR__ . '/../vendor/autoload.php');

    use PhpOffice\PhpSpreadsheet\Spreadsheet;
    use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
    use PhpOffice\PhpSpreadsheet\Style\Border;
    use PhpOffice\PhpSpreadsheet\Style\Fill;
    use PhpOffice\PhpSpreadsheet\Style\Alignment;
    use PhpOffice\PhpSpreadsheet\Style\Color;

    $fechaInicio = $_POST['fechaInicio'] ?? null;
    $fechaFin = $_POST['fechaFin'] ?? null;

    if (!$fechaInicio || !$fechaFin) {
        echo json_encode([
            'exito' => false,
            'error' => 'Ambas fechas son obligatorias.'
        ]);
        exit;
    }

    if ($fechaInicio > $fechaFin) {
        echo json_encode([
            'exito' => false,
            'error' => 'La fecha de inicio no puede ser mayor a la fecha de fin.'
        ]);
        exit;
    }


    try {

        $con = Connection::getInstance()->getConnection();

        $consultaBusqueda1 = 
            "SELECT 
                ve.vtafec,
                REPLACE(vdet.vtacant, ',', '.') AS vtacant,
                ve.vtanum,
                ter.ternit,
                ter.ternom,
                ter.terdir
            FROM
                ventasdet AS vdet
            LEFT JOIN ventas AS ve ON ve.vtaid = vdet.vtaid
            LEFT JOIN terceros AS ter ON ter.terid = ve.TerId
            WHERE 
                vdet.proid = 112
                AND ve.vtafec >= '".$fechaInicio."'  
                AND ve.vtafec <= '".$fechaFin."' 
            ;";

            
        $quer = $con->query($consultaBusqueda1);

        $resultados = array();


        if ($quer->num_rows > 0) {
            while ($fila = $quer->fetch_assoc()) {
                $resultados[] = $fila;
            }
        
            // Crear el archivo Excel
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
        
            // Encabezados personalizados
            $sheet->setCellValue('A1', 'Fecha_Movimiento');
            $sheet->setCellValue('B1', 'Cantidad_Movimiento');
            $sheet->setCellValue('C1', 'Nro_Doc_Soporte');
            $sheet->setCellValue('D1', 'Nro_Documento_Receptor');
            $sheet->setCellValue('E1', 'Nombre_Receptor');
            $sheet->setCellValue('F1', 'Direccion_Receptor');

            // Aplicar negrilla al encabezado
            $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        
            // Insertar datos desde la segunda fila
            $filaExcel = 2;
            foreach ($resultados as $fila) {
                $sheet->setCellValue('A' . $filaExcel, $fila['vtafec']);
                $valor = str_replace(',', '.', $fila['vtacant']);
                $sheet->setCellValue('B' . $filaExcel, $valor);
                $sheet->setCellValue('C' . $filaExcel, $fila['vtanum']);
                $sheet->setCellValue('D' . $filaExcel, $fila['ternit']);
                $sheet->setCellValue('E' . $filaExcel, $fila['ternom']);
                $sheet->setCellValue('F' . $filaExcel, $fila['terdir']);
                $filaExcel++;
            }

            // Aplicar bordes a toda la tabla
            $rango = 'A1:F' . ($filaExcel - 1);
            $sheet->getStyle($rango)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            // Ajustar ancho automático de columnas
            foreach (range('A', 'F') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        
            // Guardar el archivo
            $nombreArchivo = 'reporte_' . date('Ymd_His') . '.xlsx';
            $rutaCarpeta = '../descargas/';
            if (!file_exists($rutaCarpeta)) {
                mkdir($rutaCarpeta, 0777, true);
            }
            $rutaArchivo = $rutaCarpeta . $nombreArchivo;
        
            $writer = new Xlsx($spreadsheet);
            $writer->save($rutaArchivo);
        
            echo json_encode([
                'exito' => true,
                'url' => $rutaArchivo
            ]);
            exit;
        }
        else{
            echo json_encode([
                'exito' => false,
                'error' => 'No se encontraron ventas de este producto en el rango de fecha.'
            ]);
            exit;
        }


    } catch (Exception $e) {
        echo json_encode([
            'exito' => false,
            'error' => "Error general: " . $e->getMessage()
        ]);
    }

?>