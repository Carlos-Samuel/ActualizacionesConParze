<!doctype html>
<html lang="es" data-bs-theme="auto">
<head>
    <?php include('partes/head.php'); ?>
    <style>
        <?php include('styles/bitacoras.css'); ?>
    </style>
</head>
<body>
    <div class="layout has-sidebar fixed-sidebar fixed-header">
        <?php include('partes/sidebar.php'); ?>  
        <div id="overlay" class="overlay">
        </div>
        <div class="layout">
            <div class="banner-titulo">
                Consulta de Bitacoras
            </div>
            <main class="content">
                <div class="card p-4 shadow mt-1">
                    <div class=card p-4 shadow mt-1">
                        <form id="formFechas">
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label for="fechaInicio" class="form-label">Fecha inicio</label>
                                    <input type="date" class="form-control" id="fechaInicio" name="fechaInicio" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="fechaFin" class="form-label">Fecha fin</label>
                                    <input type="date" class="form-control" id="fechaFin" name="fechaFin" required>
                                </div>
                            </div>    
                            <button type="submit" class="btn btn-primary">Procesar</button>
                        </form>    
                    </div>    
                    <div id="subtotalVisual" class="mt-2 text-end pe-3 fs-5 text-primary fw-bold" style="display: none;">
                        Subtotal: <span id="subtotalValor">0</span>
                    </div>
                    <div id="contenedorTabla" class="mt-1" style="display: none;">
                        <table id="tabla-bitacoras" class="table table-bordered table-striped" style="width: 100%;">                        
                        
                            <thead>
                                <tr>
                                    <th>ID</th>   
                                    <th>Tipo</th>
                                    <th style="width:160px;">Fecha</th>
                                    <th>Hora</th>
                                    <th style="width:100px;">Origen</th>
                                    <th>No.Reg.</th>
                                    <th style="width:200px;">Tamaño</th>
                                    <th style="width:200px;">Resultado</th>
                                    <th style="width:300px;">Mensaje de Error </th>
                                    <th >Parametros</th>
                                    <th style="width:200px;">Time Inicio</th>
                                    <th style="width:200px;">Time Fin</th>
                                    <th style="width:100px;">Satisfactorio</th>
                                    <th style="width:300px;">Ruta Archivo</th>
                                    <th style="width:100px;">Archivo Borrado</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </main>
        </div>
    </div>

    <?php include('partes/foot.php'); ?>  
    <script src="scripts/bitacoras.js"></script>
</body>
</html>
