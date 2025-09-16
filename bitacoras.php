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
                Bitacoras
            </div>
            <main class="content">
                <div class="card p-4 shadow mt-1">
                    <!-- URL -->
                    <div class="mb-2">
                        
                    <br>
                  
                    <div class="mb-2">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <label class="form-label fw-semibold m-0">Bitacoras disponibles</label>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle" id="tabla-bitacoras">
                            <thead class="table-light">
                                <tr>
                                <th style="width:100px;">ID</th>   
                                <th style="width:100px;">Tipo</th>
                                 <th>Fecha</th>
                                <th style="width:160px;">Hora</th>
                                <th style="width:200px;">Origen</th>
                                <th style="width:200px;">No. Reg.</th>
                                <th style="width:200px;">Tamaño</th>
                                <th style="width:200px;">Resultado</th>
                                <th style="width:300px;">Error</th>
                                <th >Parametros</th>
                                <th style="width:200px;">Time Inicio</th>
                                <th style="width:200px;">Time Fin</th>
                                <th style="width:100px;">Satisfactorio</th>
                                <th style="width:300px;">Ruta Archivo</th>
                                <th style="width:100px;">Archivo Borrado</th>
                            </tr>
                            </thead>
                            <tbody>
                            </tbody>
                            </table>
                        </div>

                        <div class="text-end mt-2">
                            <button id="btn-guardar-bitacoras" class="btn btn-primary" disabled>Guardar bitacoras</button>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <?php include('partes/foot.php'); ?>  
    <script src="scripts/bitacoras.js"></script>
</body>
</html>
