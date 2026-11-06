(() => {
    'use strict';
    const script = document.currentScript;
    const byId = id => document.getElementById(id);
    const input = byId('ia-pregunta');
    const microphone = byId('btn-microfono');
    const queryButton = byId('btn-consultar-ia');
    const voiceStatus = byId('voz-estado');
    if (!input || !microphone || !queryButton) return;

    let recorder = null;
    let stream = null;
    let timer = null;
    let starting = false;
    let stopping = false;
    let transcribing = false;
    let leaving = false;
    let audioContext = null;
    let meter = null;

    window.colocarPregunta = text => { input.value = text; input.focus(); };
    const status = (text, error = false) => {
        voiceStatus.textContent = text;
        voiceStatus.style.display = 'inline';
        voiceStatus.style.color = error ? '#b91c1c' : '#334155';
    };
    const releaseMicrophone = () => {
        clearTimeout(timer);
        clearInterval(meter);
        if (audioContext) { audioContext.close().catch(() => {}); audioContext = null; }
        if (stream) stream.getTracks().forEach(track => track.stop());
        stream = null;
        microphone.setAttribute('aria-pressed', 'false');
        microphone.setAttribute('aria-label', 'Grabar pregunta');
        microphone.style.background = '#e2e8f0';
    };
    const responseJson = async response => {
        if (response.status === 419 || response.status === 401 || response.redirected) {
            throw new Error('Tu sesión expiró. Recarga la página e inicia sesión nuevamente.');
        }
        if (response.status === 429) throw new Error('Hay demasiadas solicitudes. Espera un minuto e inténtalo de nuevo.');
        let data;
        try { data = await response.json(); }
        catch { throw new Error('El servidor no pudo completar la solicitud. Intenta de nuevo.'); }
        if (!response.ok) throw new Error(data.mensaje || data.message || 'No se pudo completar la solicitud.');
        return data;
    };

    microphone.addEventListener('click', async () => {
        if (starting || stopping || transcribing) return;
        if (recorder?.state === 'recording') {
            stopping = true;
            recorder.stop();
            return;
        }
        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
            status('La grabación necesita HTTPS (o localhost) y un navegador con acceso al micrófono.', true);
            return;
        }
        starting = true;
        status('Solicitando permiso para usar el micrófono...');
        try {
            stream = await navigator.mediaDevices.getUserMedia({audio: true});
            const mime = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/mp4']
                .find(type => MediaRecorder.isTypeSupported(type));
            recorder = mime ? new MediaRecorder(stream, {mimeType: mime}) : new MediaRecorder(stream);
            const parts = [];
            let measured = false;
            let signalDetected = false;
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (AudioContext) {
                try {
                    audioContext = new AudioContext();
                    const analyser = audioContext.createAnalyser();
                    audioContext.createMediaStreamSource(stream).connect(analyser);
                    const samples = new Uint8Array(analyser.fftSize);
                    audioContext.resume().catch(() => {});
                    meter = setInterval(() => {
                        if (audioContext?.state !== 'running') return;
                        analyser.getByteTimeDomainData(samples);
                        measured = true;
                        signalDetected ||= samples.some(value => Math.abs(value - 128) > 3);
                    }, 100);
                } catch { measured = false; }
            }
            recorder.addEventListener('dataavailable', event => { if (event.data.size) parts.push(event.data); });
            recorder.addEventListener('error', () => {
                releaseMicrophone();
                status('No se pudo grabar. Comprueba que el micrófono esté conectado y vuelve a intentarlo.', true);
            });
            recorder.addEventListener('stop', async () => {
                stopping = false;
                const type = recorder.mimeType.split(';')[0] || 'audio/webm';
                releaseMicrophone();
                if (leaving) return;
                if (measured && !signalDetected) { status('No se detectó sonido. Comprueba el micrófono seleccionado y vuelve a grabar.', true); return; }
                const audio = new Blob(parts, {type});
                if (audio.size < 1000) { status('La grabación fue demasiado breve. Graba tu pregunta de nuevo.', true); return; }
                if (audio.size > 10 * 1024 * 1024) { status('La grabación es demasiado grande. Haz una pregunta más breve.', true); return; }
                transcribing = true;
                microphone.disabled = queryButton.disabled = true;
                status('Transcribiendo tu pregunta...');
                const form = new FormData();
                const extension = type.includes('ogg') ? 'ogg' : type.includes('mp4') ? 'mp4' : 'webm';
                form.append('audio', audio, 'pregunta.' + extension);
                try {
                    const data = await responseJson(await fetch(script.dataset.transcripcionUrl, {
                        method: 'POST', credentials: 'same-origin',
                        headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': script.dataset.csrf}, body: form,
                        signal: AbortSignal.timeout(55000),
                    }));
                    if (!data.ok || !data.texto) throw new Error(data.mensaje || 'No se reconoció voz. Vuelve a grabar.');
                    input.value = [input.value.trim(), data.texto].filter(Boolean).join(' ').slice(0, 1000);
                    input.focus();
                    status('Transcripción lista. Revisa el texto y pulsa Consultar.');
                } catch (error) { status(error.name === 'TimeoutError' ? 'La transcripción tardó demasiado. Intenta de nuevo.' : error.message, true); }
                finally { transcribing = false; microphone.disabled = queryButton.disabled = false; }
            });
            recorder.start();
            microphone.setAttribute('aria-pressed', 'true');
            microphone.setAttribute('aria-label', 'Detener grabación');
            microphone.style.background = '#fecaca';
            status('Grabando... Pulsa el micrófono para terminar (máximo 60 segundos).');
            timer = setTimeout(() => { if (recorder?.state === 'recording') { stopping = true; recorder.stop(); } }, 60000);
        } catch (error) {
            releaseMicrophone();
            status(error.name === 'NotAllowedError' ? 'Permiso de micrófono denegado. Habilítalo para este sitio desde la barra del navegador.'
                : error.name === 'NotFoundError' ? 'No se encontró un micrófono conectado.'
                : 'No se pudo abrir el micrófono. Comprueba que otra aplicación no lo esté utilizando.', true);
        } finally { starting = false; }
    });
    window.addEventListener('pagehide', () => {
        leaving = true;
        if (recorder?.state === 'recording') recorder.stop();
        releaseMicrophone();
    });

    const resultMessage = (text, ok) => {
        byId('ia-resultado-contenedor').style.display = 'block';
        const badge = byId('ia-estado-badge');
        badge.textContent = ok ? 'Consulta completada' : 'Revisa la consulta';
        badge.style.background = ok ? '#dcfce7' : '#fee2e2';
        badge.style.color = ok ? '#15803d' : '#b91c1c';
        const message = byId('ia-mensaje-error');
        message.textContent = text;
        message.style.display = ok ? 'none' : 'block';
    };
    queryButton.addEventListener('click', async () => {
        const pregunta = input.value.trim();
        if (!pregunta) { input.focus(); resultMessage('Escribe o graba una pregunta antes de consultar.', false); return; }
        if (recorder?.state === 'recording' || starting || stopping || transcribing || queryButton.disabled) return;
        queryButton.disabled = microphone.disabled = true;
        byId('ia-btn-texto').style.display = 'none';
        byId('ia-spinner').style.display = 'inline';
        byId('ia-exportar').replaceChildren();
        byId('ia-exportar').style.display = 'none';
        byId('ia-tabla-head').replaceChildren();
        byId('ia-tabla-body').replaceChildren();
        byId('ia-metadatos').textContent = '';
        byId('ia-sql-debug').textContent = '';
        byId('ia-grafico-sugerido').textContent = '';
        try {
            const data = await responseJson(await fetch(script.dataset.consultaUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': script.dataset.csrf},
                body: JSON.stringify({pregunta}), signal: AbortSignal.timeout(95000),
            }));
            resultMessage([data.mensaje, data.sugerencia].filter(Boolean).join(' '), data.ok);
            byId('ia-sql-debug').textContent = data.sql || '';
            if (!data.ok) return;
            byId('ia-metadatos').textContent = `${data.total} filas · ${data.tiempo_ms} ms`;
            for (const [url, label] of [[data.exportar_csv_url, 'Exportar este resultado a CSV'], [data.exportar_pdf_url, 'Imprimir / PDF de este resultado']]) {
                if (!url) continue;
                const destination = new URL(url, window.location.href);
                if (destination.origin !== window.location.origin) continue;
                const link = document.createElement('a');
                link.href = destination.href;
                link.textContent = label;
                link.className = 'btn btn-secondary';
                byId('ia-exportar').appendChild(link);
                byId('ia-exportar').style.display = 'flex';
            }
            const header = document.createElement('tr');
            for (const column of data.columnas || []) {
                const cell = document.createElement('th');
                cell.textContent = column.replaceAll('_', ' ');
                cell.style.padding = '.6rem';
                header.appendChild(cell);
            }
            byId('ia-tabla-head').appendChild(header);
            for (const row of data.filas || []) {
                const tr = document.createElement('tr');
                for (const column of data.columnas || []) {
                    const cell = document.createElement('td');
                    cell.textContent = row[column] == null ? '—' : String(row[column]);
                    cell.style.padding = '.6rem';
                    tr.appendChild(cell);
                }
                byId('ia-tabla-body').appendChild(tr);
            }
            if (!data.filas?.length) {
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = Math.max(1, data.columnas?.length || 0);
                td.textContent = 'No hay registros para los filtros solicitados.';
                tr.appendChild(td);
                byId('ia-tabla-body').appendChild(tr);
            }
        } catch (error) {
            resultMessage(error.name === 'TimeoutError' ? 'La consulta tardó demasiado. Acota el período e inténtalo de nuevo.' : error.message, false);
        } finally {
            byId('ia-btn-texto').style.display = 'inline';
            byId('ia-spinner').style.display = 'none';
            queryButton.disabled = microphone.disabled = false;
        }
    });
})();
