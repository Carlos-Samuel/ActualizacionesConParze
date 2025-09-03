// scripts/parametrizacion.js
// Requiere jQuery y opcionalmente SweetAlert2 (Swal)

const ENDPOINT_GET  = 'controladores/obtenerParametro.php';
const ENDPOINT_SAVE = 'controladores/guardarParametro.php';
const PARAM_EMPRESAS = 'EMPRESAS_SELECCIONADAS';
const ENDPOINT_LISTAR_EMPRESAS = 'controladores/listarEmpresas.php';
const PARAM_GRUPOS = 'GRUPOS_SELECCIONADOS';
const ENDPOINT_LISTAR_GRUPOS = 'controladores/listarGrupos.php';
const PARAM_SUBGRUPOS = 'SUBGRUPOS_CON_DESCUENTO';
const ENDPOINT_LISTAR_SUBGRUPOS = 'controladores/listarSubGrupos.php';

// Definición de parámetros y reglas  SE QUITAN TODOS 
const PARAMS = [];

let empresasCache = [];
let seleccionInicialNorm = '';
let $tablaEmpresasBody = null;
//let bodegasCache = []; 
//let bodegasSeleccionInicialNorm = '';
//let $tablaBodegasBody = null;
let gruposCache = []; 
let gruposSeleccionInicialNorm = '';
let $tablaGruposBody = null;
let subGruposCache = []; 
let SubGruposConDescuentoInicialNorm = '';
let $tablaSubGruposBody = null;

$(document).ready(function () {
  //  PARAMS.forEach(setupParam);
    $tablaGruposBody = $('#tabla-grupos tbody');
    $tablaSubGruposBody = $('#tabla-subgrupos tbody');

    cargarGruposYSeleccion();
    cargarSubGruposYSeleccion();

    // Guardar
    $('#btn-guardar-empresas').on('click', function () {
        guardarEmpresasSeleccionadas();
    });

    $('#btn-guardar-bodegas').on('click', function () {
        guardarBodegasSeleccionadas();
    });

});

function setupParam(def) {
  const $input = $(def.input);
  const $btn   = $(def.button);

  let initial = '';

  // Carga
  $.ajax({
    url: ENDPOINT_GET,
    method: 'POST',
    dataType: 'json',
    data: { codigo: def.code }
  })
  .done(function (resp) {
    if (resp.statusCode === 200 && resp.parametro) {
      const val = def.loadTransform(resp.parametro.valor);
      $input.val(val);
      initial = val;
      $btn.prop('disabled', true);
    } else if (resp.statusCode === 404) {
      // no vigente encontrado: queda vacío
      initial = '';
      $btn.prop('disabled', true);
      // opcional: info(`No hay valor vigente para ${def.code}, puedes registrarlo.`);
    } else if (resp.statusCode === 409) {
      error(`Existen múltiples vigentes para ${def.code}. Corrige la inconsistencia.`);
    } else {
      error(resp.mensaje || `No se pudo cargar el parámetro ${def.code}.`);
    }
  })
  .fail(function () {
    error(`Fallo al obtener el parámetro ${def.code}.`);
  });

  // Habilitar botón solo si hay cambios
  $input.on('input change', function () {
    const current = $input.val().trim();
    $btn.prop('disabled', current === initial);
  });

  // Guardar
  $btn.on('click', function () {
    const raw = $input.val();
    const msg = def.validate(raw);
    if (msg) { warn(msg); return; }

    const toSave = def.saveTransform(raw);

    $.ajax({
      url: ENDPOINT_SAVE,
      method: 'POST',
      dataType: 'json',
      data: {
        codigo: def.code,
        descripcion: def.desc,
        valor: toSave
      }
    })
    .done(function (resp) {
      if (resp.statusCode === 200) {
        ok('Parámetro guardado correctamente.');
        initial = (def.loadTransform === undefined) ? toSave : def.loadTransform(toSave);
        $btn.prop('disabled', true);
      } else {
        error(resp.mensaje || `No se pudo guardar ${def.code}.`);
      }
    })
    .fail(function () {
      error(`Fallo al guardar el parámetro ${def.code}.`);
    });
  });
}


function cargarGruposYSeleccion() {
  // 1) Obtener parámetro vigente (lista emprcod separados por ;)
  $.ajax({
    url: ENDPOINT_GET,
    method: 'POST',
    dataType: 'json',
    data: { codigo: PARAM_GRUPOS }
  })
  .done(function (respParam) {
    let seleccionSet = new Set();
    if (respParam.statusCode === 200 && respParam.parametro && respParam.parametro.valor) {
      seleccionSet = parseSeleccionToSet(respParam.parametro.valor);
      seleccionInicialNorm = normalizeSeleccion(seleccionSet);
    } else if (respParam.statusCode === 404) {
      // No hay selección vigente
      seleccionSet = new Set();
      seleccionInicialNorm = '';
    } else if (respParam.statusCode === 409) {
      error('Existen múltiples parámetros vigentes para GRUPOS_SELECCIONADAS. Corrige la inconsistencia.');
      return;
    } else if (respParam.statusCode && respParam.statusCode !== 200) {
      error(respParam.mensaje || 'No se pudo cargar la selección de grupos.');
      return;
    }

    // 2) Listar empresas
    $.ajax({
      url: ENDPOINT_LISTAR_GRUPOS,
      method: 'POST',
      dataType: 'json'
    })
    .done(function (respEmp) {
      if (respEmp.statusCode === 200 && Array.isArray(respEmp.empresas)) {
        gruposCache = respEmp.grupos ; // [{grpcod, grpnom, emprcod, emprnom}]
        renderTablaGrupos(gruposCache, seleccionSet);
        $('#btn-guardar-grupos').prop('disabled', true);
      } else {
        error(respEmp.mensaje || 'No se pudieron cargar las grupos.');
      }
    })
    .fail(function () {
      error('Fallo al comunicarse con el servidor al listar grupos.');
    });

  })
  .fail(function () {
    error('Fallo al obtener el parámetro GRUPOS_SELECCIONADAS.');
  });
}



function renderTablagrupos(grupos, seleccionSet) {
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
      $('#btn-guardar-grupos').prop('disabled', true); 
       cargarSubGruposYSeleccion();
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


function cargarSubGruposYSeleccion() {
  // 1) Obtener selección vigente de parámetro
  $.ajax({
    url: ENDPOINT_GET,
    method: 'POST',
    dataType: 'json',
    data: { codigo: PARAM_SUBGRUPOS }
  })
  .done(function (respParam) {
    let seleccionSet = new Set();
    if (respParam.statusCode === 200 && respParam.parametro && respParam.parametro.valor) {
      seleccionSet = parseSeleccionToSet(respParam.parametro.valor);
      bodegasSeleccionInicialNorm = normalizeSeleccion(seleccionSet);
    } else if (respParam.statusCode === 404) {
      seleccionSet = new Set();
      subgruposSeleccionInicialNorm = '';
    } else if (respParam.statusCode === 409) {
      error('Existen múltiples parámetros vigentes para SUBRUPOS_SELECCIONADAS. Corrige la inconsistencia.');
      return;
    } else if (respParam.statusCode && respParam.statusCode !== 200) {
      error(respParam.mensaje || 'No se pudo cargar la selección de subrupos.');
      return;
    }

    // 2) Listar bodegas asignadas a empresa
    $.ajax({
      url: ENDPOINT_LISTAR_SUBGRUPOS,
      method: 'POST',
      dataType: 'json'
    })
    .done(function (respSub) {
      if (respBod.statusCode === 200 && Array.isArray(respBod.bodegas)) {
        subgruposCache = respSub.subgrupos; // [{subcod, subnom, grpcod, grpnom}]
        renderTablaSubGrupos(subGruposCache, seleccionSet);
        $('#btn-guardar-subgrupos').prop('disabled', true);
      } else {
        error(respBod.mensaje || 'No se pudieron cargar los subgrupos.');
      }
    })
    .fail(function () {
      error('Fallo al comunicarse con el servidor al listar subrupos.');
    });

  })
  .fail(function () {
    error('Fallo al obtener el parámetro SUBGRUPOS_SELECCIONADOS.');
  });
}

function renderTablaSubGrupos(subgrupos, seleccionSet) {
  $tablaSubGruposBody.empty();

  // Ya vienen ordenadas por empresa desde el backend, pero si quieres:
  // bodegas.sort((a,b)=> a.emprnom.localeCompare(b.emprnom) || a.bodnom.localeCompare(b.bodnom));

  subrupos.forEach(b => {
    const selected = seleccionSet.has(String(b.subcod));
    const row = $(`
      <tr data-bod="${escapeHtml(String(b.subcod))}">
        <td>${escapeHtml(String(b.grpnom))}</td>
        <td class="text-monospace">${escapeHtml(String(b.subcod))}</td>
        <td>${escapeHtml(String(b.subnom))}</td>
        <td>
          <select class="form-select form-select-sm sub-sel">
            <option value="NO">No</option>
            <option value="SI">Sí</option>
          </select>
        </td>
      </tr>
    `);
    row.find('select.sub-sel').val(selected ? 'SI' : 'NO');

    row.find('select.sub-sel').on('change', function () {
      const norm = normalizeSeleccion(getSeleccionSubGruposActualComoSet());
      $('#btn-guardar-bodegas').prop('disabled', norm === bodegasSeleccionInicialNorm);
    });

    $tablaSubGruposBody.append(row);
  });
}

function getSeleccionSubGruposActualComoSet() {
  const set = new Set();
  $tablaSubGruposBody.find('tr').each(function () {
    const cod = $(this).data('sub');
    const val = $(this).find('select.sun-sel').val();
    if (val === 'SI') set.add(String(cod));
  });
  return set;
}

function guardarSubGruposSeleccionadas() {
  const seleccionSet = getSeleccionSubgruposActualComoSet();
  const valor = Array.from(seleccionSet).sort().join(';');

  $.ajax({
    url: ENDPOINT_SAVE,
    method: 'POST',
    dataType: 'json',
    data: {
      codigo: PARAM_SUBGRUPOS,
      descripcion: 'Subgrupos con descuentos (BodCod:descuento(0-99) separados por ;)',
      valor: valor
    }
  })
  .done(function (resp) {
    if (resp.statusCode === 200) {
      ok('Descuentos de subBodegas guardadas correctamente.');
      subGruposSeleccionInicialNorm = normalizeSeleccion(seleccionSet);
      $('#btn-guardar-subgrupos').prop('disabled', true);
    } else {
      error(resp.mensaje || 'No se pudo guardar los subrgrupos con descuento.');
    }
  })
  .fail(function () {
    error('Fallo al comunicarse con el servidor al guardar los subrgupos con descuento.');
  });
}

// Notificaciones
function ok(msg)   { if (window.Swal) Swal.fire('Éxito',       msg, 'success'); else alert(msg); }
function info(msg) { if (window.Swal) Swal.fire('Información', msg, 'info');    else alert(msg); }
function warn(msg) { if (window.Swal) Swal.fire('Atención',    msg, 'warning'); else alert(msg); }
function error(msg){ if (window.Swal) Swal.fire('Error',       msg, 'error');   else alert(msg); }
