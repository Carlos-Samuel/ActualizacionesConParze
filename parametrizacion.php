<!doctype html>
<html lang="es" data-bs-theme="auto">
<head>
    <?php include('partes/head.php'); ?>
    <style>
        <?php include('styles/parametrizacion.css'); ?>
    </style>
</head>
<body>
    <div class="layout has-sidebar fixed-sidebar fixed-header">
        <?php include('partes/sidebar.php'); ?>  
        <div id="overlay" class="overlay">
        </div>
        <div class="layout">
            <div class="banner-titulo">
                Parametrización
            </div>
            <main class="content">
                <div class="card p-4 shadow mt-1">
                    <!-- URL -->
                    <div class="mb-3">
                        <label for="param-url" class="form-label fw-semibold">URL del servicio</label>
                        <div class="input-group">
                        <input type="url" class="form-control" id="param-url" placeholder="https://tuservidor/api" />
                        <button id="btn-guardar-url" class="btn btn-primary" disabled>Guardar</button>
                        </div>
                        <div class="form-text">URL de consumo de API.</div>
                    </div>

                    <!-- Hora cargue diario FULL -->
                    <div class="mb-3">
                        <label for="param-hora-full" class="form-label fw-semibold">Hora del cargue diario FULL</label>
                        <div class="input-group">
                        <input type="time" class="form-control" id="param-hora-full" step="60" />
                        <button id="btn-guardar-hora-full" class="btn btn-primary" disabled>Guardar</button>
                        </div>
                        <div class="form-text">Formato 24h. Ej: 02:30</div>
                    </div>

                    <!-- Frecuencia periódica en horas -->
                    <div class="mb-3">
                        <label for="param-frecuencia" class="form-label fw-semibold">Frecuencia del cargue periódico (horas)</label>
                        <div class="input-group">
                        <input type="number" class="form-control" id="param-frecuencia" min="3" step="1" placeholder="Mínimo 3" />
                        <button id="btn-guardar-frecuencia" class="btn btn-primary" disabled>Guardar</button>
                        </div>
                        <div class="form-text">Debe ser un entero ≥ 3.</div>
                    </div>

                    <!-- API Key -->
                    <div class="mb-3">
                        <label for="param-apikey" class="form-label fw-semibold">API Key</label>
                        <div class="input-group">
                        <input type="text" class="form-control" id="param-apikey" placeholder="Tu API Key" />
                        <button id="btn-guardar-apikey" class="btn btn-primary" disabled>Guardar</button>
                        </div>
                    </div>

                    <!-- Reintentos automáticos -->
                    <div class="mb-1">
                        <label for="param-reintentos" class="form-label fw-semibold">Número de reintentos automáticos</label>
                        <div class="input-group">
                        <input type="number" class="form-control" id="param-reintentos" min="0" step="1" placeholder="0 a 10 recomendado" />
                        <button id="btn-guardar-reintentos" class="btn btn-primary" disabled>Guardar</button>
                        </div>
                        <div class="form-text">Entero ≥ 0 (recomendado ≤ 10).</div>
                    </div>

                    <!-- Empresas seleccionadas (tabla con select Sí/No) -->
                    <div class="mb-2">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <label class="form-label fw-semibold m-0">Empresas habilitadas para proceso</label>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle" id="tabla-empresas">
                                <thead class="table-light">
                                    <tr>
                                    <th style="width:120px;">Código</th>
                                    <th>Empresa</th>
                                    <th style="width:160px;">Seleccionada</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>

                        <div class="text-end mt-2">
                            <button id="btn-guardar-empresas" class="btn btn-primary" disabled>Guardar selección de empresas</button>
                        </div>
                    </div>
                    <br>
                    <!-- Bodegas seleccionadas (tabla con select Sí/No) -->
                    <div class="mb-2">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <label class="form-label fw-semibold m-0">Bodegas habilitadas</label>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle" id="tabla-bodegas">
                            <thead class="table-light">
                                <tr>
                                <th style="width:240px;">Empresa</th>
                                <th style="width:120px;">BodCod</th>
                                <th>Bodega</th>
                                <th style="width:160px;">Seleccionada</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                            </table>
                        </div>

                        <div class="text-end mt-2">
                            <button id="btn-guardar-bodegas" class="btn btn-primary" disabled>Guardar selección de bodegas</button>
                        </div>
                    </div>



                </div>
            </main>
        </div>
    </div>

    <?php include('partes/foot.php'); ?>  
    <script src="scripts/parametrizacion.js"></script>
</body>
</html>
