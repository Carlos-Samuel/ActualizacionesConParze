<!doctype html>
<html lang="es" data-bs-theme="auto">
    <head>
        <?php
            include('partes/head.php')
        ?>
        <style>
        </style>
    </head>
    <body>
        <div class="layout has-sidebar fixed-sidebar fixed-header">
            <?php
                include('partes/sidebar.php');
            ?>  
            <div id="overlay" class="overlay"></div>
            <div class="layout">
                <main class="content">
                
                    <h4 class="mb-4">Seleccione un rango de fechas</h4>
                    <div class="card p-4 shadow">
                        <form id="formFechas">
                        <div class="row mb-3">
                            <div class="col">
                            <label for="fechaInicio" class="form-label">Fecha inicio</label>
                            <input type="date" class="form-control" id="fechaInicio" name="fechaInicio" required>
                            </div>
                            <div class="col">
                            <label for="fechaFin" class="form-label">Fecha fin</label>
                            <input type="date" class="form-control" id="fechaFin" name="fechaFin" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Procesar</button>
                        </form>

                        <div id="cargando" class="mt-3 text-info" style="display: none;">Procesando, por favor espere...</div>
                        <div id="resultado" class="mt-3"></div>
                    </div>


                </main>
            </div>
        </div>
        <?php
            include('partes/foot.php')
        ?>  
        <script>

            $('#formFechas').on('submit', function(e) {
                e.preventDefault();
                $('#resultado').html('');
                $('#cargando').show();

                $.ajax({
                url: 'controladores/generar_informe.php',
                method: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response) {
                    $('#cargando').hide();
                    if (response.exito) {
                    $('#resultado').html(`<div class="alert alert-success">Archivo listo: <a href="descargas/${response.url}" class="btn btn-success btn-sm" download>Descargar</a></div>`);
                    } else {
                    $('#resultado').html(`<div class="alert alert-danger">${response.error}</div>`);
                    }
                },
                error: function() {
                    $('#cargando').hide();
                    $('#resultado').html(`<div class="alert alert-danger">Error al conectar con el servidor.</div>`);
                }
                });
            });

        </script>
    </body>
</html>