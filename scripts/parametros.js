
// VENDEDOR
$('#parametro').select2({
    placeholder: 'Seleccione uno o mas parametros',
    width: '100%',
    multiple: true,
    ajax: {
        url: 'controladores/select2/parametroscod.php',
        type: 'POST',
        dataType: 'json',
        delay: 250,
        data: function (params) {
            return {
                search: params.term || '' 
            };
        },
        processResults: function (data) {
            return {
                results: data.map(item => ({
                    id: item.id,
                    text: item.text
                }))
            };
        },
        cache: true
    }
});


// EMPRESA
$('#despar').select2({
    placeholder: 'Seleccione una o mas descripciones',
    width: '100%',
    multiple: true,
    ajax: {
        url: 'controladores/select2/parametros.php',
        type: 'POST',
        dataType: 'json',
        delay: 250,
        data: function (params) {
            return {
                search: params.term || ''
            };
        },
        processResults: function (data) {
            return {
                results: data.map(item => ({
                    id: item.id,
                    text: `${item.codigo} - ${item.text}`
                }))
            };
        },
        cache: true
    }
});

// MARCA
// PREFIJOS
// GRUPO

//$('#empresa').on('change', function () {
//    $('#grupo').val(null).trigger('change');
//});

//$('#grupo').on('change', function () {
//    $('#subgrupo').val(null).trigger('change');
//});

let tablaInicializada = false;

function cargarTabla(datosFiltro) {
    if (tablaInicializada) {
        $('#tablaInforme').DataTable().clear().destroy();
    }

    $('#contenedorTabla').show();

    $('#tablaInforme').DataTable({
        scrollX: true,
        scrollY: '60vh',
        scrollCollapse: true,
        scrollX: true,
        fixedHeader: true,
        dom: '<"row align-items-center mb-2"' +
                '<"col-sm-4"l>' +       // Mostrar X registros
                '<"col-sm-4 text-center"B>' + // Botones de exportar
                '<"col-sm-4 text-end"f>' +    // Filtro de búsqueda
            '>' +
            '<"row"<"col-sm-12"tr>>' +       // Tabla
            '<"row mt-2"' +
                '<"col-sm-6"i>' +       // Información ("Mostrando de X a Y")
                '<"col-sm-6 text-end"p>' +  // Paginación
            '>',
        ajax: {
            url: 'controladores/datatable/informeVentas.php',
            type: 'POST',
            data: datosFiltro
        },
        columns: [
            { data: 'Código Parametro' },
            { data: 'Descripción' },
            { data: 'Valor' },
            { data: 'fecha de creación' },
            { data: 'Vigente' }
        ],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
        },
        buttons: [
            {
                extend: 'excelHtml5',
                text: '<i class="fa fa-file-excel"></i> Exportar a Excel',
                title: null,
                className: 'btn btn-success',
                exportOptions: {
                columns: ':visible'
                },
                customize: function (xlsx) {
                    const sheet = xlsx.xl.worksheets['sheet1.xml'];
                    const sheetData = sheet.getElementsByTagName('sheetData')[0];
                    const doc = sheetData.ownerDocument;

                    const getMultipleTexts = (selector) => {
                        return $(selector).select2('data').map(item => item.text.trim()).join(', ') || 'Todos';
                    };
//  aqui voy RAPC0829
                    const info = [
                        `Prametro(s): ${getMultipleTexts('#parametro')}`,
                        `Descripción(s): ${getMultipleTexts('#despar')}`,
                        `Vigencia: ${getMultipleTexts('#videncia')}`
                    ];

                    // Desplaza filas existentes
                    const existingRows = sheetData.getElementsByTagName('row');
                    for (let i = 0; i < existingRows.length; i++) {
                        const row = existingRows[i];
                        const oldR = parseInt(row.getAttribute('r'), 10);
                        row.setAttribute('r', oldR + info.length);

                        const cells = row.getElementsByTagName('c');
                        for (let j = 0; j < cells.length; j++) {
                            const cell = cells[j];
                            const ref = cell.getAttribute('r');
                            if (ref) {
                                const col = ref.replace(/[0-9]/g, '');
                                cell.setAttribute('r', col + (oldR + info.length));
                            }
                        }
                    }

                    // Inserta nuevas filas
                    for (let i = info.length - 1; i >= 0; i--) {
                        const row = doc.createElement('row');
                        row.setAttribute('r', i + 1);

                        const cell = doc.createElement('c');
                        cell.setAttribute('t', 'inlineStr');
                        cell.setAttribute('r', 'A' + (i + 1));

                        const is = doc.createElement('is');
                        const t = doc.createElement('t');
                        t.textContent = info[i] || '';

                        is.appendChild(t);
                        cell.appendChild(is);
                        row.appendChild(cell);

                        sheetData.insertBefore(row, sheetData.firstChild);
                    }

                    // Ajusta la dimensión de la hoja
                    const dimension = sheet.getElementsByTagName('dimension')[0];
                    if (dimension) {
                        dimension.setAttribute('ref', `A1:Z${existingRows.length + info.length}`);
                    }
                }


            }
        ],
        drawCallback: function (settings) {
            let api = this.api();
            let data = api.rows({ page: 'all' }).data();

            let total = 0;
            for (let i = 0; i < data.length; i++) {
                let val = parseFloat(data[i].subtotal);
                if (!isNaN(val)) {
                    total += val;
                }
            }

            // Mostrar y formatear
            $('#subtotalVisual').show();
            $('#subtotalValor').text(total.toLocaleString('es-CO', { style: 'currency', currency: 'COP' }));
        }


    });

    tablaInicializada = true;
}



$('#formFechas').on('submit', function(e) {
    e.preventDefault();
    cargarTabla({
        prametro: $('#parametro').val(),
        descripción: $('#despar').val(),
        vigencia: $('#vigencia').val()
    });

});

$(document).ready(function () {
    const fechaActual = new Date();
    const primerDiaMes = new Date(fechaActual.getFullYear(), fechaActual.getMonth(), 1);

    function formatear(fecha) {
        const anio = fecha.getFullYear();
        const mes = (fecha.getMonth() + 1).toString().padStart(2, '0');
        const dia = fecha.getDate().toString().padStart(2, '0');
        return `${anio}-${mes}-${dia}`;
    }

    $('#fechaInicio').val(formatear(primerDiaMes));
    $('#fechaFin').val(formatear(fechaActual));
});