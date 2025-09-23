

let tablaInicializada = false;

function cargarTabla(datosFiltro) {
    if (tablaInicializada) {
        $('#tabla-bitacoras').DataTable().clear().destroy();
    }

    $('#contenedorTabla').show();

    $('#tabla-bitacoras').DataTable({
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
            url: 'controladores/listarBitacoras.php',
            type: 'POST',
            data: datosFiltro
        },
        columns: [
            { data: 'id_bitacora' },
            { data: 'tipo_de_cargue' },
            { data: 'fecha_ejecucion' },
            { data: 'hora_ejecucion' },
            { data: 'origen_del_proceso' },
            { data: 'cantidad_registros_enviados' },
            { data: 'tamaño_del_archivo' },
            { data: 'resultado_del_envio' },
            { data: 'descripcion_error' },
            { data: 'parametros_usados' },
            { data: 'fecha_hora_de_inicio' },
            { data: 'fecha_hora_de_fin' },
            { data: 'ruta_archivo' },
            { data: 'archivo_borrado' }
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

                    const info = [
                        `Fecha Inicial: ${$('#fechaInicio').val() || ''}`,
                        `Fecha Final: ${$('#fechaFin').val() || ''}`
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
           // $('#subtotalVisual').show();
           // $('#subtotalValor').text(total.toLocaleString('es-CO', { style: 'currency', currency: 'COP' }));
        }


    });

    tablaInicializada = true;

};

function setupParam(def) {
  const $input = $(def.input);
  const $btn   = $(def.button);

  let initial = '';

}

function safe(val) {
  if (val === null || val === undefined) return '';
  return escapeHtml(String(val));
}


function getSeleccionActualComoSet() {
  const set = new Set();
  $tablaGruposBody.find('tr').each(function () {
    const cod = $(this).data('cod');
    const val = $(this).find('select.grp-sel').val();
    if (val === 'SI') set.add(String(cod));
  });
  return set;

}



$('#formFechas').on('submit', function(e) {
    e.preventDefault();
    cargarTabla({
        fechaInicio: $('#fechaInicio').val(),
        fechaFin: $('#fechaFin').val()
    });

});


function renderTablaBitacoras(bitacoras) {
  $tablaBitacorasBody.empty();
   
  bitacoras.forEach(s => {

    const row = $(`
      <tr data-bitacora="${safe(s.id_bitacora)}">
        <td class="text-monospace">${safe(s.id_bitacora)}</td>
        <td>${safe(s.tipo_de_cargue)}</td>
        <td class="text-monospace">${safe(s.fecha_ejecucion)}</td>
        <td>${safe(s.hora_ejecucion)}</td>
        <td>${safe(s.origen_del_proceso)}</td>
        <td>${safe(s.cantidad_registros_enviados)}</td>
        <td>${safe(s.tamaño_del_archivo)}</td>
        <td>${safe(s.resultado_del_envio)}</td>
        <td>${safe(s.descripcion_error)}</td>
        <td>${safe(s.parametros_usados)}</td>
        <td>${safe(s.fecha_hora_de_inicio)}</td>
        <td>${safe(s.fecha_hora_de_fin)}</td>
        <td>${safe(s.ruta_archivo)}</td>
        <td>${s.archivo_borrado == 1 ? 'Sí' : 'No'}</td>
      </tr>
    `);


    $tablaBitacorasBody.append(row);
  });
}

function getSeleccionbitacorasActualComoSet() {
   
  const set = new Set();
  $tablaBitacorasBody.find('tr').each(function () {
    const cod = $(this).data('subid');
    const val = $(this).find('input.sub-des').val();
     
    if ( val > 0 && val <= 100) set.add(`${cod}:${val}`);
  });
   
  return set;
}


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