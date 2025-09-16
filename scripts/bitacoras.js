// scripts/parametrizacion.js
// Requiere jQuery y opcionalmente SweetAlert2 (Swal)

const ENDPOINT_LISTAR_BITACORAS = 'controladores/listarBitacoras.php';

// Definición de parámetros y reglas  SE QUITAN TODOS 
const PARAMS = [];

let bitacoraSeleccionInicialNorm = '';
let bitacoraCache = []; 
let $tablaBitacoraBody = null;

$(document).ready(function () {
    PARAMS.forEach(setupParam);
   
    $tablaBitacoraBody = $('#tabla-bitacoras tbody');

    
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

function cargarGruposYSeleccion() {
 
    //  Listar bitacoars
    $.ajax({
      url: ENDPOINT_LISTAR_BITACORAS,
      method: 'POST',
      dataType: 'json'
    })
    .done(function (resp) {
      if (resp.statusCode === 200 && Array.isArray(resp.grupos)) {
        bitacorasCache = resp.bitacoras ; // [{grpcod, grpnom, emprcod, emprnom}]
        renderTablaGrupos(gruposCache, seleccionSet);
        $('#btn-guardar-bitacoras').prop('disabled', true);
      } else {
        error(resp.mensaje || 'No se pudieron cargar las bitacoras.');
      }
    })
    .fail(function () {
      error('Fallo al comunicarse con el servidor al listar bitacoras.');
    });

}

function renderTablaGrupos(grupos, seleccionSet) {
  $tablaGruposBody.empty();

  grupos.forEach(e => {
    const selected = seleccionSet.has(String(e.grpcod));
    const row = $(`
      <tr data-cod="${escapeHtml(String(e.grpcod))}">
        <td class="text-monospace">${escapeHtml(String(e.grpcod))}</td>
        <td>${escapeHtml(String(e.grpnom))}</td>
        <td>
          <select class="form-select form-select-sm grp-sel">
            <option value="NO">No</option>
            <option value="SI">Sí</option>
          </select>
        </td>
      </tr>
    `);
    row.find('select.grp-sel').val(selected ? 'SI' : 'NO');

    // Escuchar cambios para habilitar botón guardar si difiere de la selección inicial
    row.find('select.grp-sel').on('change', function () {
      const norm = normalizeSeleccion(getSeleccionActualComoSet());
      $('#btn-guardar-grupos').prop('disabled', norm === seleccionInicialNorm);
    });

    $tablaGruposBody.append(row);
  });
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

function guardarGruposSeleccionadas() {
  const seleccionSet = getSeleccionActualComoSet();
  // Convertir a string "cod1;cod2;cod3"
  const valor = Array.from(seleccionSet).sort().join(';');

  $.ajax({
    url: ENDPOINT_SAVE,
    method: 'POST',
    dataType: 'json',
    data: {
      codigo: PARAM_GRUPOS,
      descripcion: 'Grupos seleccioano(grpcod separados por ;)',
      valor: valor
    }
  })
  .done(function (resp) {
    if (resp.statusCode === 200) {
      ok('Selección de grupos guardada correctamente.');
      // Actualizar baseline
      seleccionInicialNorm = normalizeSeleccion(seleccionSet);
      $('#btn-guardar-bitacoras').prop('disabled', true); 
       cargarBitacorasYSeleccion();
    } else {
      error(resp.mensaje || 'No se pudo guardar la selección de grupos.');
    }
  })
  .fail(function () {
    error('Fallo al comunicarse con el servidor al guardar la selección de grupos.');
  });
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
      dataType: 'json'
      
    })
    .done(function (resp) {
      // borrar console
      console.log('Respuesta del listado de bitacoras:', resp);
      if (resp.statusCode === 200 && Array.isArray(resp.bitacoras)) {

        bitacorasCache = resp.bitacoras; // [{subcod, subnom, grpcod, grpnom}]
        renderTablabitacoras(bitacorasCache, seleccionSetS);
        $('#btn-guardar-descuentos').prop('disabled', true);
      } else {
        error(resp.mensaje || 'No se pudieron cargar los bitacoras.');
      }
    })
    .fail(function () {
      error('Fallo al comunicarse con el servidor al listar bitacoras.');
    });

  }  

function renderTablabitacoras(bitacoras, seleccionSetS) {
  $tablabitacorasBody.empty();

  // Ya vienen ordenadas por empresa desde el backend, pero si quieres:
  // bodegas.sort((a,b)=> a.emprnom.localeCompare(b.emprnom) || a.bodnom.localeCompare(b.bodnom));

   console.log('Renderizando tabla de bitacoras:', bitacoras);
   console.log('Renderizando tabla de selección:', seleccionSetS);
  bitacoras.forEach(s => {
    let descuento = 0 ;
    const subgrupo = String(s.subid);
    const resultado = Array.from(seleccionSetS).find(item => item.startsWith(subgrupo + ":"));

    if (resultado){
        descuento = resultado.split(':')[1];
    }

    const row = $(`
      <tr data-subid="${escapeHtml(String(s.subid))}">
        <td>${escapeHtml(String(s.empnom))}</td>
        <td class="text-monospace">${escapeHtml(String(s.grpnom))}</td>
        <td>${escapeHtml(String(s.subnom))}</td>
        <td>
          <input type="number" class="form-control form-control-sm sub-des" 
             min="0" max="100" placeholder="0–100" value="${escapeHtml(String(descuento))}">  
        </td>
      </tr>
    `);
    //row.find('input.sub-des').val(selected ? 100 : 0);
    row.find('input.sub-des').val()
    
    row.find('input.sub-des').on('change', function () {
      const norm = normalizeSeleccion(getSeleccionbitacorasActualComoSet());
      
      $('#btn-guardar-descuentos').prop('disabled', norm === bitacorasSeleccionInicialNorm);
    });

    $tablabitacorasBody.append(row);
  });
}

function getSeleccionbitacorasActualComoSet() {
   
  const set = new Set();
  $tablabitacorasBody.find('tr').each(function () {
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
