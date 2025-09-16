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

  // Ya vienen ordenadas por empresa desde el backend, pero si quieres:
  // bodegas.sort((a,b)=> a.emprnom.localeCompare(b.emprnom) || a.bodnom.localeCompare(b.bodnom));

   console.log('Renderizando tabla de bitacoras:', bitacoras);
   
  bitacoras.forEach(s => {
    
    //const subgrupo = String(s.subid);
    //const resultado = Array.from(seleccionSetS).find(item => item.startsWith(subgrupo + ":"));

   // if (resultado){
   //     descuento = resultado.split(':')[1];
   // }

    const row = $(`
      <tr data-bitacora="${escapeHtml(String(s.id_bitacora))}">
        <td class="text-monospace">${escapeHtml(String(s.id_bitacora))}</td>
        <td>${escapeHtml(String(s.tipo_de_cargue))}</td>
        <td class="text-monospace">${escapeHtml(String(s.fecha_ejecucion))}</td>
        <td>${escapeHtml(String(s.hora_ejecucion))}</td>
        <td>${escapeHtml(String(s.origen_del_proceso))}</td>
        <td>${escapeHtml(String(s.cantidad_registros_enviados))}</td>
        <td>${escapeHtml(String(s.tamaño_del_archivo))}</td>
        <td>${escapeHtml(String(s.resultado_del_envio))}</td>
        <td>${escapeHtml(String(s.descripcion_error))}</td>
        <td>${escapeHtml(String(s.parametros_usados))}</td>
        <td>${escapeHtml(String(s.fecha_hora_de_inicio))}</td>
        <td>${escapeHtml(String(s.fecha_hora_de_fin))}</td>
         
        <td>${(s.satisfactorio == 1) ? 'Sí' : 'No'}</td>
        <td>${escapeHtml(String(s.ruta_archivo))}</td>
        
        <td>${(s.archivo_borrado == 1) ? 'Sí' : 'No'}</td>
      </tr>
    `);
    //row.find('input.sub-des').val(selected ? 100 : 0);
    //<td>${(s.archivo_borrado == 1) ? 'Sí' : 'No'}</td>
    

    $tablaBitacorasBody.append(row);
  });
}

function getSeleccionbitacorasActualComoSet() {
   
  const set = new Set();
  $tablaBitacorasBody.find('tr').each(function () {
    const cod = $(this).data('subid');
    const val = $(this).find('input.sub-des').val();
     
    //aqui se debe cambiar la intruccion ser.add String(cod+':'String.valueOf(val)) RAPC  //`${cod}:${val}`
    if ( val > 0 && val <= 100) set.add(`${cod}:${val}`);
  });
   
  return set;
}



// Notificaciones
function ok(msg)   { if (window.Swal) Swal.fire('Éxito',       msg, 'success'); else alert(msg); }
function info(msg) { if (window.Swal) Swal.fire('Información', msg, 'info');    else alert(msg); }
function warn(msg) { if (window.Swal) Swal.fire('Atención',    msg, 'warning'); else alert(msg); }
function error(msg){ if (window.Swal) Swal.fire('Error',       msg, 'error');   else alert(msg); }
