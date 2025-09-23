// scripts/parametrizacion.js
// Requiere jQuery y opcionalmente SweetAlert2 (Swal)

const ENDPOINT_LISTAR_BITACORAS = 'controladores/listarBitacoras.php';

// Definición de parámetros y reglas  SE QUITAN TODOS 
const PARAMS = [];

let bitacoraSeleccionInicialNorm = '';
let bitacoraCache = []; 
let $tablaBitacorasBody = null;

$(document).ready(function () {
    PARAMS.forEach(setupParam);
   
    $tablaBitacorasBody = $('#tabla-bitacoras tbody');

    
    cargarBitacorasYSeleccion();

    $('#btn-guardar-bitacoras').on('click', function () {
        guardarbitacorasSeleccionadas();
    });

});

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

// ===== utilidades selección =====
function parseSeleccionToSet(valorStr) {
  return new Set(
    String(valorStr)
      .split(';')
      .map(s => s.trim())
      .filter(s => s.length > 0)
  );
}
function normalizeSeleccion(set) {
  
  return Array.from(set).sort().join(';'); // normaliza para comparar cambios
}
function escapeHtml(s) {
  return s.replace(/[&<>"'`=\/]/g, function (c) {
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'}[c];
  });
}


function cargarBitacorasYSeleccion() {
    // 2) Listar bodegas asignadas a empresa
    $.ajax({
      url: ENDPOINT_LISTAR_BITACORAS,
      method: 'POST',
      dataType: 'json', 
      data: { fecha_ejecucion_ini: "2025-01-01",
              fecha_ejecucion_fin: Date()
       }
      
    })
    .done(function (resp) {
      // borrar console
      console.log('Respuesta del listado de bitacoras:', resp);
      if (resp.statusCode === 200 && Array.isArray(resp.bitacoras)) {

        bitacorasCache = resp.bitacoras; // [{subcod, subnom, grpcod, grpnom}]
        renderTablaBitacoras(bitacorasCache);
        $('#btn-guardar-descuentos').prop('disabled', true);
      } else {
        error(resp.mensaje || 'No se pudieron cargar los bitacoras.');
      }
    })
    .fail(function () {
      error('Fallo al comunicarse con el servidor al listar bitacoras.');
    });

  }  

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



// Notificaciones
function ok(msg)   { if (window.Swal) Swal.fire('Éxito',       msg, 'success'); else alert(msg); }
function info(msg) { if (window.Swal) Swal.fire('Información', msg, 'info');    else alert(msg); }
function warn(msg) { if (window.Swal) Swal.fire('Atención',    msg, 'warning'); else alert(msg); }
function error(msg){ if (window.Swal) Swal.fire('Error',       msg, 'error');   else alert(msg); }
