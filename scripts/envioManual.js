let pollTimer = null;
let currentId = null;

function renderLogs(rows){
  const $body = $('#logsBody').empty();
  if(rows.length === 0){
    $body.append('<tr><td colspan="3" class="text-muted">Sin registros…</td></tr>');
    return;
  }
  rows.forEach((r, idx) => {
    const fecha = new Date(r.momento_de_registro.replace(' ', 'T'));
    $body.append(
      `<tr>
         <td>${idx + 1}</td>
         <td>${r.descripcion_paso}</td>
         <td class="mono">${isNaN(fecha) ? r.momento_de_registro : fecha.toLocaleString()}</td>
       </tr>`
    );
  });
}

function startPolling(){
    if(pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(async () => {
        if(!currentId) return;
        try{
            const res = await fetch('controladores/getLogsBitacora.php?id_bitacora=' + encodeURIComponent(currentId), {cache:'no-store'});
            const data = await res.json();
            if(data.ok){
                renderLogs(data.rows);
                const done = data.rows.some(r => (r.descripcion_paso || '').toLowerCase() === 'termina');
                if(done){
                    clearInterval(pollTimer);
                    $('#status').text('Proceso finalizado.');
                    $('#btnActualizar').prop('disabled', false);
                }
            }
        }catch(e){
        console.error(e);
        }
    }, 2000);
}

$('#btnActualizar').on('click', async function(){
    $(this).prop('disabled', true);
    $('#status').text('Creando bitácora…');

    try{
        const res = await fetch('controladores/registroInicialBitacora.php', { method: 'POST' });
        const data = await res.json();
        if(!data.ok) throw new Error(data.error || 'Error desconocido');

        currentId = data.id_bitacora;
        $('#idBitacora').text(currentId);
        $('#status').text('Ejecutando…');

        fetch('controladores/gestorGeneracionInforme.php', {
        method: 'POST',
        body: new URLSearchParams({ id_bitacora: currentId })
        });

        startPolling();

    } catch(e){
        $('#status').text('Error: ' + e.message);
        $('#btnActualizar').prop('disabled', false);
    }
});