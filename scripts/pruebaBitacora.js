// scripts/parametrizacion.js
// Requiere jQuery y opcionalmente SweetAlert2 (Swal)

const ENDPOINT_UPDATE  = 'controladores/actualizaBitacora.php';
const ENDPOINT_SAVE = 'controladores/registraBitacora.php';


// Definición de parámetros y reglas  SE QUITAN TODOS 
const PARAMS = [];

let empresasCache = [];
let seleccionInicialNorm = '';
let $tablaEmpresasBody = null;

setupBitacora();

$(document).ready(function () {
  PARAMS.forEach(setupBitacora);
    // setupBitacora();
});

function setupBitacora(def) {
  
  let initial = '';
   console.log("entro a la funcion");

   
  // Carga
  $.ajax({
    url: ENDPOINT_SAVE,
    method: 'POST',
    dataType: 'json',
    data: { 
            tipo_de_cargue: 'FULL' ,
            fecha_ejecucion: "2025-01-01",
            hora_ejecucion: "12:00:00",
            origen_del_proceso: 'Manual',
            cantidad_registros_enviados: 15,
            tamaño_del_archivo: '3M',
            resultado_del_envio: 'Exitoso',
            descripcion_error: 'todo bien',
            parametros_usados: 'casi nada',   
            satisfactorio: 1,
            ruta_archivo: 'via/pruebas.txt',
            archivo_borrado: 0 
          }
  })
  .done(function (resp) {
    if (resp.statusCode === 200 && resp.parametro) {
      const val = def.loadTransform(resp.parametro.valor);
      $input.val(val);
      initial = val;
      $btn.prop('disabled', true);
    } else if (resp.statusCode === 404) {
      // no vigente encontrado: queda vacío
      
      console.log(`Existen múltiples vigentes para ${def.tipo_de_cargue}. Corrige la inconsistencia.`);
    } else {
      console.log(resp.mensaje || `No se pudo cargar el parámetro ${def.tipo_de_cargue}.`);
    }
  })
  .fail(function () {
    console.log(`Fallo al registrar la bitacora.`);
  });

}
// Notificaciones
function ok(msg)   { if (window.Swal) Swal.fire('Éxito',       msg, 'success'); else alert(msg); }
function info(msg) { if (window.Swal) Swal.fire('Información', msg, 'info');    else alert(msg); }
function warn(msg) { if (window.Swal) Swal.fire('Atención',    msg, 'warning'); else alert(msg); }
function error(msg){ if (window.Swal) Swal.fire('Error',       msg, 'error');   else alert(msg); }
