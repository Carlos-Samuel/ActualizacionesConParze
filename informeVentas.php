<!doctype html>
<html lang="es" data-bs-theme="auto">
<head>
    <?php include('partes/head.php'); ?>
    <style>
        <?php include('styles/informeVentas.css'); ?>
    </style>
</head>
<body>
    <div class="layout has-sidebar fixed-sidebar fixed-header">
        <?php include('partes/sidebar.php'); ?>  
        <div id="overlay" class="overlay">
        </div>
        <div class="layout">
            <div class="banner-titulo">
                Informe por Rango de Fechas
            </div>
            <main class="content">
                <div class="card p-4 shadow mt-1">
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
                            <div class="col-md-3">
                                <label for="vendedor" class="form-label">Vendedor</label>
                                <select id="vendedor" name="vendedor[]" multiple></select>
                            </div>
                            <div class="col-md-3">
                                <label for="empresa" class="form-label">Empresa</label>
                                <select class="form-select" id="empresa" name="empresa[]" multiple required></select>
                            </div>
                            <div class="col-md-3">
                                <label for="marca" class="form-label">Marca</label>
                                <select class="form-select" id="marca" name="marca[]" multiple></select>
                            </div>
                            <div class="col-md-3">
                                <label for="prefijo" class="form-label">Prefijo</label>
                                <select class="form-select" id="prefijo" name="prefijo[]" multiple></select>
                            </div>
                            <div class="col-md-3">
                                <label for="grupo" class="form-label">Grupo</label>
                                <select class="form-select" id="grupo" name="grupo[]" multiple></select>
                            </div>
                            <div class="col-md-3">
                                <label for="subgrupo" class="form-label">Subgrupo</label>
                                <select class="form-select" id="subgrupo" name="subgrupo[]" multiple></select>
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
                                <th>Fecha Factura</th>
                                <th>Prefijo</th>
                                <th>Prefijo</th>
                                <th>#Fac</th>
                                <th>#Ven</th>
                                <th>Vendedor</th>
                                <th>Ter Nit</th>
                                <th>Tercero</th>
                                <th>Cod Prod</th>
                                <th>Producto</th>
                                <th>Cant</th>
                                <th>Vlr Uni</th>
                                <th>Subtotal</th>
                                <th>Grupo</th>
                                <th>SubGrupo</th>
                                <th>Codigo Marca</th>
                                <th>Marca</th>
                                <th>Centro de costos</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </main>
        </div>
    </div>

    <?php include('partes/foot.php'); ?>  
    <script src="scripts/informeVentas.js"></script>

</body>
</html>
