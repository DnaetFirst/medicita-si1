import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';

const source = readFileSync(new URL('../public/js/reportes.js', import.meta.url), 'utf8');
class Element {
    constructor() { this.value = ''; this.style = {}; this.attributes = {}; this.children = []; this.events = {}; this.disabled = false; this.textContent = ''; }
    setAttribute(key, value) { this.attributes[key] = value; }
    addEventListener(event, action) { this.events[event] = action; }
    appendChild(child) { this.children.push(child); }
    replaceChildren() { this.children = []; }
    focus() {}
    set innerHTML(_) { throw new Error('Untrusted HTML must never be inserted'); }
    async click() { await this.events.click(); }
}
function setup({denied = false, response = {ok: true, texto: 'Cuántas citas atendidas hubo hoy'}} = {}) {
    const elements = new Map();
    const get = id => { if (!elements.has(id)) elements.set(id, new Element()); return elements.get(id); };
    let stopped = 0;
    const pending = [];
    const requests = [];
    const events = {};
    class Recorder {
        static isTypeSupported() { return true; }
        constructor() { this.events = {}; this.state = 'inactive'; this.mimeType = 'audio/webm'; }
        addEventListener(event, action) { this.events[event] = action; }
        start() { this.state = 'recording'; }
        stop() {
            this.state = 'inactive';
            this.events.dataavailable({data: new Blob(['x'.repeat(2048)])});
            pending.push(this.events.stop());
        }
    }
    vm.runInNewContext(source, {
        document: {currentScript: {dataset: {consultaUrl: '/query', transcripcionUrl: '/transcribe', csrf: 'csrf'}}, getElementById: get, createElement: () => new Element()},
        window: {isSecureContext: true, MediaRecorder: Recorder, addEventListener: (event, action) => { events[event] = action; }},
        navigator: {mediaDevices: {getUserMedia: async () => { if (denied) throw Object.assign(new Error(), {name: 'NotAllowedError'}); return {getTracks: () => [{stop: () => { stopped++; }}]}; }}},
        MediaRecorder: Recorder, Blob, FormData, AbortSignal, setTimeout: () => 1, clearTimeout: () => {}, setInterval: () => 1, clearInterval: () => {},
        fetch: async (url, options) => { requests.push({url, options}); return {ok: true, status: 200, json: async () => response}; },
    });
    return {get, requests, pending, events, stopped: () => stopped};
}
test('recording starts and stops with separate clicks, transcribes and requires explicit query', async () => {
    const app = setup();
    await app.get('btn-microfono').click();
    assert.equal(app.get('btn-microfono').attributes['aria-pressed'], 'true');
    await app.get('btn-microfono').click();
    await Promise.all(app.pending);
    assert.equal(app.requests.length, 1);
    assert.equal(app.requests[0].url, '/transcribe');
    assert.equal(app.get('ia-pregunta').value, 'Cuántas citas atendidas hubo hoy');
    assert.equal(app.get('btn-microfono').attributes['aria-pressed'], 'false');
    assert.equal(app.get('btn-consultar-ia').disabled, false);
    assert.equal(app.stopped(), 1);
});
test('microphone denial gives a visible message and allows another attempt', async () => {
    const app = setup({denied: true});
    await app.get('btn-microfono').click();
    assert.match(app.get('voz-estado').textContent, /Permiso.*denegado/);
    assert.equal(app.requests.length, 0);
    assert.equal(app.get('btn-microfono').disabled, false);
});
test('leaving the page releases the microphone without uploading audio', async () => {
    const app = setup();
    await app.get('btn-microfono').click();
    app.events.pagehide();
    await Promise.all(app.pending);
    assert.equal(app.requests.length, 0);
    assert.equal(app.stopped(), 1);
});
test('report values and dynamic column labels are rendered as text', async () => {
    const untrusted = '<img src=x onerror=alert(1)>';
    const app = setup({response: {ok: true, columnas: ['nombre'], filas: [{nombre: untrusted}], total: 1, tiempo_ms: 10}});
    app.get('ia-pregunta').value = 'Lista médicos';
    await app.get('btn-consultar-ia').click();
    assert.equal(app.get('ia-tabla-body').children[0].children[0].textContent, untrusted);
    assert.equal(app.get('btn-consultar-ia').disabled, false);
});
