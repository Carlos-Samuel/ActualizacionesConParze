<!doctype html>
<html lang="es" data-bs-theme="auto">
<head>
    <?php include('partes/head.php'); ?>
    <style>
        <?php include('styles/descuentos.css'); ?>
    </style>
</head>
<body>
    <div class="layout has-sidebar fixed-sidebar fixed-header">
        <?php include('partes/sidebar.php'); ?>  
        <div id="overlay" class="overlay">
        </div>
        <div class="layout">
            <div class="banner-titulo">
                Descuentos
            </div>
            <main class="content">
                <div class="card p-4 shadow mt-1">
                    <div class="mb-2">
                        
                    <br>
                        <h1 class="h4 mb-3">Demo: Bitácora y Log de pasos</h1>

                        <div class="mb-3 d-flex align-items-center">
                            <button id="btnActualizar" class="btn btn-primary">Actualizar API</button>
                            <span id="status" class="ms-3 text-muted"></span>
                        </div>

                        <div class="mb-2">
                            <strong>ID Bitácora:</strong> <span id="idBitacora" class="mono">–</span>
                        </div>

                        <table class="table table-sm table-striped">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Descripción</th>
                                <th>Momento</th>
                            </tr>
                            </thead>
                            <tbody id="logsBody">
                            <tr><td colspan="3" class="text-muted">Sin registros…</td></tr>
                            </tbody>
                        </table>

                </div>
            </main>
        </div>
    </div>

    <?php include('partes/foot.php'); ?>  
    
    <script src="scripts/envioManual.js"></script>
</body>
</html>
