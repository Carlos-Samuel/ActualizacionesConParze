// scripts/parametrizacion.js
// Requiere jQuery y opcionalmente SweetAlert2 (Swal)

const ENDPOINT_GET  = 'controladores/obtenerParametro.php';
const ENDPOINT_SAVE = 'controladores/guardarParametro.php';
const PARAM_EMPRESAS = 'EMPRESAS_SELECCIONADAS';
const ENDPOINT_LISTAR_EMPRESAS = 'controladores/listarEmpresas.php';
const PARAM_BODEGAS = 'BODEGAS_SELECCIONADAS';
const ENDPOINT_LISTAR_BODEGAS = 'controladores/listarBodegas.php';

// Definición de parámetros y reglas
const PARAMS = [
  {
    code: 'URL',
    input: '#param-url',
    button: '#btn-guardar-url',
    desc: 'URL del servicio',
    loadTransform: v => (v ?? '').trim(),
    saveTransform: v => v.trim(),
    validate: (val) => {
      if (!val) return 'Debes ingresar una URL.';
      try {
        const u = new URL(val);
        if (!/^https?:$/.test(u.protocol)) return 'La URL debe iniciar con http:// o https://';
      } catch (e) {
        return 'La URL no es válida.';
      }
      return null;
    }
  },
  {
    code: 'HORA_CARGUE_FULL',
    input: '#param-hora-full',
    button: '#btn-guardar-hora-full',
    desc: 'Hora del cargue diario FULL',
    loadTransform: v => {
      // Normaliza a HH:MM para <input type="time">
      const raw = (v ?? '').trim();
      if (!raw) return '';
      // Acepta HH:MM o HH:MM:SS
      const m = raw.match(/^(\d{2}):(\d{2})(?::\d{2})?$/);
      return m ? `${m[1]}:${m[2]}` : '';
    },
    saveTransform: v => {
      // Guarda en HH:MM:00
      const t = v.trim();
      if (!t) return '';
      const m = t.match(/^(\d{2}):(\d{2})$/);
      return m ? `${m[1]}:${m[2]}:00` : t;
    },
    validate: (val) => {
      if (!val) return 'Debes seleccionar una hora.';
      if (!/^\d{2}:\d{2}$/.test(val)) return 'Formato de hora inválido.';
      return null;
    }
  },
  {
    code: 'FRECUENCIA_CARGUE_HORAS',
    input: '#param-frecuencia',
    button: '#btn-guardar-frecuencia',
    desc: 'Frecuencia del cargue periódico en horas',
    loadTransform: v => (v ?? '').toString().trim(),
    saveTransform: v => v.trim(),
    validate: (val) => {
      if (val === '') return 'Debes ingresar la frecuencia en horas.';
      const n = Number(val);
      if (!Number.isInteger(n)) return 'La frecuencia debe ser un entero.';
      if (n < 3) return 'La frecuencia mínima es de 3 horas.';
      return null;
    }
  },
  {
    code: 'APIKEY',
    input: '#param-apikey',
    button: '#btn-guardar-apikey',
    desc: 'API Key',
    loadTransform: v => (v ?? '').trim(),
    saveTransform: v => v.trim(),
    validate: (val) => {
      if (!val) return 'Debes ingresar la API Key.';
      return null;
    }
  },
  {
    code: 'REINTENTOS_API',
    input: '#param-reintentos',
    button: '#btn-guardar-reintentos',
    desc: 'Número de reintentos automáticos',
    loadTransform: v => (v ?? '').toString().trim(),
    saveTransform: v => v.trim(),
    validate: (val) => {
      if (val === '') return 'Debes ingresar el número de reintentos.';
      const n = Number(val);
      if (!Number.isInteger(n) || n < 0) return 'Los reintentos deben ser un entero ≥ 0.';
      // recomendado <= 10 (no bloquea)
      return null;
    }
  }
];

let empresasCache = [];
let seleccionInicialNorm = '';
let $tablaEmpresasBody = null;
let bodegasCache = []; 
let bodegasSeleccionInicialNorm = '';
let $tablaBodegasBody = null;

$(document).ready(function () {
    PARAMS.forEach(setupParam);
    $tablaEmpresasBody = $('#tabla-empresas tbody');
    $tablaBodegasBody = $('#tabla-bodegas tbody');

    cargarEmpresasYSeleccion();
    cargarBodegasYSeleccion();

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


function cargarEmpresasYSeleccion() {
  // 1) Obtener parámetro vigente (lista emprcod separados por ;)
  $.ajax({
    url: ENDPOINT_GET,
    method: 'POST',
    dataType: 'json',
    data: { codigo: PARAM_EMPRESAS }
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
      error('Existen múltiples parámetros vigentes para EMPRESAS_SELECCIONADAS. Corrige la inconsistencia.');
      return;
    } else if (respParam.statusCode && respParam.statusCode !== 200) {
      error(respParam.mensaje || 'No se pudo cargar la selección de empresas.');
      return;
    }

    // 2) Listar empresas
    $.ajax({
      url: ENDPOINT_LISTAR_EMPRESAS,
      method: 'POST',
      dataType: 'json'
    })
    .done(function (respEmp) {
      if (respEmp.statusCode === 200 && Array.isArray(respEmp.empresas)) {
        empresasCache = respEmp.empresas; // [{emprcod, emprnom}]
        renderTablaEmpresas(empresasCache, seleccionSet);
        $('#btn-guardar-empresas').prop('disabled', true);
      } else {
        error(respEmp.mensaje || 'No se pudieron cargar las empresas.');
      }
    })
    .fail(function () {
      error('Fallo al comunicarse con el servidor al listar empresas.');
    });

  })
  .fail(function () {
    error('Fallo al obtener el parámetro EMPRESAS_SELECCIONADAS.');
  });
}

function renderTablaEmpresas(empresas, seleccionSet) {
  $tablaEmpresasBody.empty();

  empresas.forEach(e => {
    const selected = seleccionSet.has(String(e.emprcod));
    const row = $(`
      <tr data-cod="${escapeHtml(String(e.emprcod))}">
        <td class="text-monospace">${escapeHtml(String(e.emprcod))}</td>
        <td>${escapeHtml(String(e.emprnom))}</td>
        <td>
          <select class="form-select form-select-sm emp-sel">
            <option value="NO">No</option>
            <option value="SI">Sí</option>
          </select>
        </td>
      </tr>
    `);
    row.find('select.emp-sel').val(selected ? 'SI' : 'NO');

    // Escuchar cambios para habilitar botón guardar si difiere de la selección inicial
    row.find('select.emp-sel').on('change', function () {
      const norm = normalizeSeleccion(getSeleccionActualComoSet());
      $('#btn-guardar-empresas').prop('disabled', norm === seleccionInicialNorm);
    });

    $tablaEmpresasBody.append(row);
  });
}

function getSeleccionActualComoSet() {
  const set = new Set();
  $tablaEmpresasBody.find('tr').each(function () {
    const cod = $(this).data('cod');
    const val = $(this).find('select.emp-sel').val();
    if (val === 'SI') set.add(String(cod));
  });
  return set;
}

function guardarEmpresasSeleccionadas() {
  const seleccionSet = getSeleccionActualComoSet();
  // Convertir a string "cod1;cod2;cod3"
  const valor = Array.from(seleccionSet).sort().join(';');

  $.ajax({
    url: ENDPOINT_SAVE,
    method: 'POST',
    dataType: 'json',
    data: {
      codigo: PARAM_EMPRESAS,
      descripcion: 'Empresas habilitadas (emprcod separados por ;)',
      valor: valor
    }
  })
  .done(function (resp) {
    if (resp.statusCode === 200) {
      ok('Selección de empresas guardada correctamente.');
      // Actualizar baseline
      seleccionInicialNorm = normalizeSeleccion(seleccionSet);
      $('#btn-guardar-empresas').prop('disabled', true); 
       cargarBodegasYSeleccion();
    } else {
      error(resp.mensaje || 'No se pudo guardar la selección de empresas.');
    }
  })
  .fail(function () {
    error('Fallo al comunicarse con el servidor al guardar la selección de empresas.');
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


function cargarBodegasYSeleccion() {
  // 1) Obtener selección vigente de parámetro
  $.ajax({
    url: ENDPOINT_GET,
    method: 'POST',
    dataType: 'json',
    data: { codigo: PARAM_BODEGAS }
  })
  .done(function (respParam) {
    let seleccionSet = new Set();
    if (respParam.statusCode === 200 && respParam.parametro && respParam.parametro.valor) {
      seleccionSet = parseSeleccionToSet(respParam.parametro.valor);
      bodegasSeleccionInicialNorm = normalizeSeleccion(seleccionSet);
    } else if (respParam.statusCode === 404) {
      seleccionSet = new Set();
      bodegasSeleccionInicialNorm = '';
    } else if (respParam.statusCode === 409) {
      error('Existen múltiples parámetros vigentes para BODEGAS_SELECCIONADAS. Corrige la inconsistencia.');
      return;
    } else if (respParam.statusCode && respParam.statusCode !== 200) {
      error(respParam.mensaje || 'No se pudo cargar la selección de bodegas.');
      return;
    }

    // 2) Listar bodegas asignadas a empresa
    $.ajax({
      url: ENDPOINT_LISTAR_BODEGAS,
      method: 'POST',
      dataType: 'json'
    })
    .done(function (respBod) {
      if (respBod.statusCode === 200 && Array.isArray(respBod.bodegas)) {
        bodegasCache = respBod.bodegas; // [{bodcod, bodnom, emprcod, emprnom}]
        renderTablaBodegas(bodegasCache, seleccionSet);
        $('#btn-guardar-bodegas').prop('disabled', true);
      } else {
        error(respBod.mensaje || 'No se pudieron cargar las bodegas.');
      }
    })
    .fail(function () {
      error('Fallo al comunicarse con el servidor al listar bodegas.');
    });

  })
  .fail(function () {
    error('Fallo al obtener el parámetro BODEGAS_SELECCIONADAS.');
  });
}

function renderTablaBodegas(bodegas, seleccionSet) {
  $tablaBodegasBody.empty();

  // Ya vienen ordenadas por empresa desde el backend, pero si quieres:
  // bodegas.sort((a,b)=> a.emprnom.localeCompare(b.emprnom) || a.bodnom.localeCompare(b.bodnom));

  bodegas.forEach(b => {
    const selected = seleccionSet.has(String(b.bodcod));
    const row = $(`
      <tr data-bod="${escapeHtml(String(b.bodcod))}">
        <td>${escapeHtml(String(b.emprnom))}</td>
        <td class="text-monospace">${escapeHtml(String(b.bodcod))}</td>
        <td>${escapeHtml(String(b.bodnom))}</td>
        <td>
          <select class="form-select form-select-sm bod-sel">
            <option value="NO">No</option>
            <option value="SI">Sí</option>
          </select>
        </td>
      </tr>
    `);
    row.find('select.bod-sel').val(selected ? 'SI' : 'NO');

    row.find('select.bod-sel').on('change', function () {
      const norm = normalizeSeleccion(getSeleccionBodegasActualComoSet());
      $('#btn-guardar-bodegas').prop('disabled', norm === bodegasSeleccionInicialNorm);
    });

    $tablaBodegasBody.append(row);
  });
}

function getSeleccionBodegasActualComoSet() {
  const set = new Set();
  $tablaBodegasBody.find('tr').each(function () {
    const cod = $(this).data('bod');
    const val = $(this).find('select.bod-sel').val();
    if (val === 'SI') set.add(String(cod));
  });
  return set;
}

function guardarBodegasSeleccionadas() {
  const seleccionSet = getSeleccionBodegasActualComoSet();
  const valor = Array.from(seleccionSet).sort().join(';');

  $.ajax({
    url: ENDPOINT_SAVE,
    method: 'POST',
    dataType: 'json',
    data: {
      codigo: PARAM_BODEGAS,
      descripcion: 'Bodegas habilitadas (BodCod separados por ;)',
      valor: valor
    }
  })
  .done(function (resp) {
    if (resp.statusCode === 200) {
      ok('Selección de bodegas guardada correctamente.');
      bodegasSeleccionInicialNorm = normalizeSeleccion(seleccionSet);
      $('#btn-guardar-bodegas').prop('disabled', true);
    } else {
      error(resp.mensaje || 'No se pudo guardar la selección de bodegas.');
    }
  })
  .fail(function () {
    error('Fallo al comunicarse con el servidor al guardar la selección de bodegas.');
  });
}

// Notificaciones
function ok(msg)   { if (window.Swal) Swal.fire('Éxito',       msg, 'success'); else alert(msg); }
function info(msg) { if (window.Swal) Swal.fire('Información', msg, 'info');    else alert(msg); }
function warn(msg) { if (window.Swal) Swal.fire('Atención',    msg, 'warning'); else alert(msg); }
function error(msg){ if (window.Swal) Swal.fire('Error',       msg, 'error');   else alert(msg); }
