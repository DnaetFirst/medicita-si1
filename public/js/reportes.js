(() => {
    'use strict';
    const script = document.currentScript;
    const byId = id => document.getElementById(id);
    const csrf = script.dataset.csrf;

    // ──────────────────────────────────────────────────────────
    // TIPO 3: Asistente IA + Voz
    // ──────────────────────────────────────────────────────────
    const input = byId('ia-pregunta');
    const microphone = byId('btn-microfono');
    const queryButton = byId('btn-consultar-ia');
    const voiceStatus = byId('voz-estado');

    if (input && microphone && queryButton) {
        let recorder = null, stream = null, timer = null;
        let starting = false, stopping = false, transcribing = false, leaving = false;
        let audioContext = null, meter = null;

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

        // ── Microphone recording ─────────────────────────────
        microphone.addEventListener('click', async () => {
            if (starting || stopping || transcribing) return;
            if (recorder?.state === 'recording') { stopping = true; recorder.stop(); return; }
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
                let measured = false, signalDetected = false;
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
                    status('Transcribiendo tu voz con IA...');
                    const form = new FormData();
                    const extension = type.includes('ogg') ? 'ogg' : type.includes('mp4') ? 'mp4' : 'webm';
                    form.append('audio', audio, 'pregunta.' + extension);
                    try {
                        const data = await responseJson(await fetch(script.dataset.transcripcionUrl, {
                            method: 'POST', credentials: 'same-origin',
                            headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrf}, body: form,
                            signal: AbortSignal.timeout(55000),
                        }));
                        if (!data.ok || !data.texto) throw new Error(data.mensaje || 'No se reconoció voz. Vuelve a grabar.');
                        input.value = [input.value.trim(), data.texto].filter(Boolean).join(' ').slice(0, 1000);
                        input.focus();
                        // Mostrar texto transcrito prominentemente
                        status('✅ Transcripción: "' + data.texto + '" — Revisa y pulsa Consultar.');
                        // Auto-submit si el texto capturado es suficientemente largo
                        if (data.texto.trim().length > 10) {
                            setTimeout(() => { if (!transcribing) queryButton.click(); }, 800);
                        }
                    } catch (error) {
                        status(error.name === 'TimeoutError' ? 'La transcripción tardó demasiado. Intenta de nuevo.' : error.message, true);
                    } finally { transcribing = false; microphone.disabled = queryButton.disabled = false; }
                });
                recorder.start();
                microphone.setAttribute('aria-pressed', 'true');
                microphone.setAttribute('aria-label', 'Detener grabación');
                microphone.style.background = '#fecaca';
                status('🎙️ Grabando... Pulsa el micrófono para terminar (máximo 60 segundos).');
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

        // ── Resultado IA ─────────────────────────────────────
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
            // Limpiar banner del título anterior
            const tituloBox = byId('ia-titulo-reporte');
            if (tituloBox) tituloBox.style.display = 'none';
            try {
                const data = await responseJson(await fetch(script.dataset.consultaUrl, {
                    method: 'POST', credentials: 'same-origin',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
                    body: JSON.stringify({pregunta}), signal: AbortSignal.timeout(95000),
                }));
                resultMessage([data.mensaje, data.sugerencia].filter(Boolean).join(' '), data.ok);
                byId('ia-sql-debug').textContent = data.sql || '';
                if (!data.ok) return;
                byId('ia-metadatos').textContent = `${data.total} filas · ${data.tiempo_ms} ms`;

                // Mostrar título del reporte IA
                if (data.titulo_reporte && tituloBox) {
                    tituloBox.textContent = '📄 ' + data.titulo_reporte;
                    tituloBox.style.display = 'block';
                }

                // Botones de exportación con nombres descriptivos
                const exportes = [
                    [data.exportar_csv_url, '⬇ Descargar resultado CSV / Excel'],
                    [data.exportar_pdf_url, '⬇ Descargar resultado PDF'],
                ];
                for (const [url, label] of exportes) {
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
                    cell.style.cssText = 'padding:.6rem; text-align:left;';
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
    }

    // ──────────────────────────────────────────────────────────
    // TIPO 2: Constructor Visual de Reportes
    // ──────────────────────────────────────────────────────────
    const constructorUrl = script.dataset.constructorUrl;
    const btnPreview = byId('btn-constructor-preview');

    if (btnPreview && constructorUrl) {
        const getColumnas = () => Array.from(document.querySelectorAll('input[name="col_constructor"]:checked')).map(el => el.value);
        const getMetricas = () => Array.from(document.querySelectorAll('input[name="met_constructor"]:checked')).map(el => el.value);
        const getAgrupacion = () => byId('constructor-agrupacion')?.value || 'ninguna';
        const getFechaInicio = () => byId('constructor-fecha-inicio')?.value || '';
        const getFechaFin = () => byId('constructor-fecha-fin')?.value || '';

        // Deshabilitar métricas cuando agrupación es "ninguna"
        const agrupacionSel = byId('constructor-agrupacion');
        const metricasBox = byId('constructor-metricas-box');
        if (agrupacionSel && metricasBox) {
            const toggleMetricas = () => {
                const disabled = agrupacionSel.value === 'ninguna' || agrupacionSel.value === '';
                metricasBox.style.opacity = disabled ? '0.4' : '1';
                metricasBox.querySelectorAll('input').forEach(el => el.disabled = disabled);
            };
            agrupacionSel.addEventListener('change', toggleMetricas);
            toggleMetricas();
        }

        const rellenarFormulario = (formId, colField, agField, metField, fIField, fFField) => {
            const form = byId(formId);
            if (!form) return;
            const cols = getColumnas();
            const ags = getAgrupacion();
            const mets = getMetricas();
            const fi = getFechaInicio();
            const ff = getFechaFin();
            // Limpiar campos previos
            form.querySelectorAll('[name^="columnas"], [name^="agrupacion"], [name^="metricas"], [name^="fecha"]').forEach(el => el.remove());
            // Insertar nuevos campos hidden
            cols.forEach(c => {
                const inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = 'columnas[]'; inp.value = c;
                form.appendChild(inp);
            });
            [['agrupacion', ags], ['fecha_inicio', fi], ['fecha_fin', ff]].forEach(([n, v]) => {
                if (!v) return;
                const inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = n; inp.value = v;
                form.appendChild(inp);
            });
            mets.forEach(m => {
                const inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = 'metricas[]'; inp.value = m;
                form.appendChild(inp);
            });
        };

        // Enviar formularios de descarga
        byId('form-constructor-csv')?.addEventListener('submit', () => rellenarFormulario('form-constructor-csv'));
        byId('form-constructor-pdf')?.addEventListener('submit', () => rellenarFormulario('form-constructor-pdf'));

        btnPreview.addEventListener('click', async () => {
            const cols = getColumnas();
            if (!cols.length) { byId('constructor-estado').textContent = '⚠ Selecciona al menos una columna.'; return; }
            const spinner = byId('constructor-spinner');
            if (spinner) spinner.style.display = 'inline';
            btnPreview.disabled = true;
            byId('constructor-estado').textContent = 'Generando vista previa...';
            byId('constructor-preview-box').style.display = 'none';
            byId('constructor-descargas').style.display = 'none';
            byId('constructor-titulo-box').style.display = 'none';

            try {
                const payload = {
                    columnas: cols,
                    agrupacion: getAgrupacion(),
                    metricas: getMetricas(),
                    fecha_inicio: getFechaInicio() || undefined,
                    fecha_fin: getFechaFin() || undefined,
                };
                const res = await fetch(constructorUrl, {
                    method: 'POST', credentials: 'same-origin',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
                    body: JSON.stringify(payload), signal: AbortSignal.timeout(60000),
                });
                if (!res.ok) {
                    const err = await res.json().catch(() => ({}));
                    throw new Error(err.message || 'Error al generar la vista previa.');
                }
                const data = await res.json();

                // Mostrar título y total
                byId('constructor-titulo-texto').textContent = data.titulo;
                byId('constructor-total-texto').textContent = `${data.total} registros · Agrupado por: ${data.agrupacion_label}`;
                byId('constructor-titulo-box').style.display = 'block';

                // Renderizar tabla de preview (máx 50 filas)
                const thead = byId('constructor-tabla-head');
                const tbody = byId('constructor-tabla-body');
                thead.replaceChildren();
                tbody.replaceChildren();

                const headerRow = document.createElement('tr');
                (data.columnas || []).forEach(col => {
                    const th = document.createElement('th');
                    th.textContent = col;
                    th.style.cssText = 'padding:.5rem .6rem; text-align:left; font-size:.8rem; white-space:nowrap;';
                    headerRow.appendChild(th);
                });
                thead.appendChild(headerRow);

                const previewFilas = (data.filas || []).slice(0, 50);
                if (previewFilas.length === 0) {
                    const tr = document.createElement('tr');
                    const td = document.createElement('td');
                    td.colSpan = (data.columnas || []).length || 1;
                    td.textContent = 'Sin resultados para los filtros seleccionados.';
                    td.style.cssText = 'padding:1rem; text-align:center; color:#6b7280;';
                    tr.appendChild(td); tbody.appendChild(tr);
                } else {
                    previewFilas.forEach((fila, i) => {
                        const tr = document.createElement('tr');
                        tr.style.background = i % 2 === 0 ? '#f0fdf4' : 'white';
                        (data.columnas || []).forEach(col => {
                            const td = document.createElement('td');
                            td.textContent = fila[col] == null ? '—' : String(fila[col]);
                            td.style.cssText = 'padding:.45rem .6rem; font-size:.8rem; border-bottom:1px solid #d1fae5;';
                            tr.appendChild(td);
                        });
                        tbody.appendChild(tr);
                    });
                }
                byId('constructor-preview-box').style.display = 'block';

                // Mostrar botones de descarga
                byId('constructor-descargas').style.display = 'flex';
                byId('constructor-estado').textContent = `✅ ${data.total} registros listos.`;

                // Pre-rellenar formularios de descarga
                rellenarFormulario('form-constructor-csv');
                rellenarFormulario('form-constructor-pdf');

            } catch (error) {
                byId('constructor-estado').textContent = '❌ ' + (error.name === 'TimeoutError' ? 'La operación tardó demasiado.' : error.message);
            } finally {
                if (spinner) spinner.style.display = 'none';
                btnPreview.disabled = false;
            }
        });
    }
})();
