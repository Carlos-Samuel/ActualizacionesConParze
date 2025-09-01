<!doctype html>
<html lang="es" data-bs-theme="auto">
<head>
    <?php include('partes/head.php'); ?>
    <style>
        <?php include('styles/paramemetros.css'); ?>
    </style>
</head>
<body>
    <div class="layout has-sidebar fixed-sidebar fixed-header">
        <?php include('partes/sidebar.php'); ?>  
        <div id="overlay" class="overlay">
        </div>
        <div class="layout">
            <div class="banner-titulo">
                Parametrizacion de la aplicación
            </div>
            <main class="content">
                <div class="card p-4 shadow mt-1">
                    <form id="formFechas">
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label for="parametro" class="form-label">Parametro</label>
                                <select class="form-select" id="parametro" name="parametro[]" multiple required></select>
                            </div>
                            <div class="col-md-3">
                                <label for="despar" class="form-label">Descripción</label>
                                <select class="form-select" id="despar" name="despar[]" multiple></select>
                            </div>
                            <div class="col-md-3">
                                <label for="vigente" class="form-label">Vigente</label>
                                <select class="form-select" id="vigente" name="vigente[]" multiple></select>
                            </div>
                            
                        </div>
                        <button type="submit" class="btn btn-primary">Procesar</button>
                    </form>
                </div>
                <div id="subtotalVisual" class="mt-2 text-end pe-3 fs-5 text-primary fw-bold" style="display: none;">
                    Subtotal: <span id="subtotalValor">0</span>
                </div>
                <div id="contenedorTabla" class="mt-1" style="display: none;">
                    <table id="tablaInforme" class="table table-bordered table-striped" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Parametro</th>
                                <th>Descripción</th>
                                <th>Valor</th>
                                <th>fecha de creación</th>
                                <th>Videncia</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </main>
        </div>
    </div>

    <?php include('partes/foot.php'); ?>  
    <script src="scripts/parametros.js"></script>

</body>
</html>
