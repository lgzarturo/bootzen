/**
 * Tests del runtime public/assets/js/bootzen.js del skeleton (node --test + jsdom).
 */
import { after, before, beforeEach, describe, test } from 'node:test';
import assert from 'node:assert/strict';
import { cpSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';
import { JSDOM } from 'jsdom';

const skeletonJs = new URL('../../skeleton/public/assets/js/', import.meta.url);
const sandbox = mkdtempSync(join(tmpdir(), 'bootzen-js-'));
const dom = new JSDOM('<!doctype html><html><head></head><body></body></html>', { url: 'http://localhost/' });
let bz;
let fetchCalls;
let nextResponse;

const tick = () => new Promise((resolve) => setTimeout(resolve, 20));

before(async () => {
  cpSync(skeletonJs, sandbox, { recursive: true });
  writeFileSync(join(sandbox, 'components/cleanup.js'), `
    export default function (element) {
      element.dataset.mounted = 'yes';
      return () => { globalThis.cleanedUp = (globalThis.cleanedUp ?? 0) + 1; };
    }
  `);
  writeFileSync(join(sandbox, 'components/broken.js'), 'export const nada = 1;');

  for (const name of ['window', 'document', 'Element', 'HTMLElement', 'HTMLFormElement', 'MutationObserver', 'CustomEvent', 'FormData', 'Node']) {
    globalThis[name] = dom.window[name];
  }
  dom.window.confirm = () => globalThis.confirmAnswer ?? true;
  globalThis.fetch = async (url, init) => {
    fetchCalls.push({ url, init });
    const { status = 200, body = {}, json = true } = nextResponse;
    return new Response(json ? JSON.stringify(body) : body, {
      status,
      headers: { 'content-type': json ? 'application/json' : 'text/html' },
    });
  };

  bz = await import(pathToFileURL(join(sandbox, 'bootzen.js')).href);
});

after(() => rmSync(sandbox, { recursive: true, force: true }));

beforeEach(() => {
  document.head.innerHTML = '';
  document.body.innerHTML = '';
  fetchCalls = [];
  nextResponse = {};
  globalThis.confirmAnswer = true;
});

describe('componentes', () => {
  test('monta un componente con sus props una sola vez', async () => {
    document.body.innerHTML = `
      <div data-component="counter" data-props='{"start": 5}'>
        <button data-counter-increment>+</button><span data-counter-value></span>
      </div>`;
    await bz.mount(document);
    await bz.mount(document);

    document.querySelector('[data-counter-increment]').click();
    assert.equal(document.querySelector('[data-counter-value]').textContent, '6');
  });

  test('marca con data-component-error los componentes inválidos o inexistentes', async () => {
    const originalError = console.error;
    console.error = () => {};
    document.body.innerHTML = `
      <div id="a" data-component="no-existe"></div>
      <div id="b" data-component="broken"></div>
      <div id="c" data-component="../fuera"></div>`;
    await bz.mount(document);
    console.error = originalError;

    assert.ok(document.getElementById('a').hasAttribute('data-component-error'));
    assert.ok(document.getElementById('b').hasAttribute('data-component-error'));
    assert.ok(!document.getElementById('c').hasAttribute('data-component-error'));
  });

  test('start() monta los componentes agregados después y limpia los que se quitan', async () => {
    bz.start();
    bz.start();

    const element = document.createElement('div');
    element.dataset.component = 'cleanup';
    document.body.append(element);
    await tick();
    assert.equal(element.dataset.mounted, 'yes');

    element.remove();
    await tick();
    assert.equal(globalThis.cleanedUp, 1);
  });

  test('el menú alterna sus enlaces y aria-expanded', async () => {
    document.body.innerHTML = `
      <nav data-component="menu">
        <button data-menu-toggle aria-expanded="false"></button>
        <div class="hidden" data-menu-items></div>
      </nav>`;
    await bz.mount(document);

    document.querySelector('[data-menu-toggle]').click();
    assert.equal(document.querySelector('[data-menu-items]').classList.contains('hidden'), false);
    assert.equal(document.querySelector('[data-menu-toggle]').getAttribute('aria-expanded'), 'true');
  });
});

describe('request()', () => {
  test('envía JSON con CSRF y cabeceras AJAX', async () => {
    document.head.innerHTML = '<meta name="csrf-token" content="tok123">';
    nextResponse = { body: { ok: true } };

    const data = await bz.request('/api/tareas', { method: 'post', data: { titulo: 'Pan' } });

    assert.deepEqual(data, { ok: true });
    assert.equal(fetchCalls[0].init.method, 'POST');
    assert.equal(fetchCalls[0].init.headers['X-CSRF-TOKEN'], 'tok123');
    assert.equal(fetchCalls[0].init.headers['Content-Type'], 'application/json');
    assert.equal(fetchCalls[0].init.headers['X-Requested-With'], 'XMLHttpRequest');
    assert.equal(fetchCalls[0].init.body, '{"titulo":"Pan"}');
  });

  test('en GET manda los datos como query string y sin CSRF', async () => {
    document.head.innerHTML = '<meta name="csrf-token" content="tok123">';
    await bz.request('/buscar', { data: { q: 'café', pagina: 2 } });

    assert.equal(fetchCalls[0].url, 'http://localhost/buscar?q=caf%C3%A9&pagina=2');
    assert.equal(fetchCalls[0].init.headers['X-CSRF-TOKEN'], undefined);
    assert.equal(fetchCalls[0].init.body, undefined);
  });

  test('lanza RequestError con el estado y el mensaje del servidor', async () => {
    nextResponse = { status: 422, body: { message: 'Datos inválidos', errors: { titulo: ['Requerido'] } } };

    await assert.rejects(bz.request('/api/tareas', { method: 'POST', data: {} }), (error) => {
      assert.ok(error instanceof bz.RequestError);
      assert.equal(error.status, 422);
      assert.equal(error.message, 'Datos inválidos');
      assert.deepEqual(error.data.errors, { titulo: ['Requerido'] });
      return true;
    });
  });

  test('devuelve texto cuando la respuesta no es JSON', async () => {
    nextResponse = { body: '<p>hola</p>', json: false };

    assert.equal(await bz.request('/fragmento'), '<p>hola</p>');
  });
});

describe('avisos y atributos declarativos', () => {
  test('toast() crea el contenedor y se quita solo', async () => {
    const element = bz.toast('Guardado', 'success', { timeout: 10 });

    assert.equal(element.getAttribute('role'), 'status');
    assert.match(element.className, /bg-green-600/);
    assert.ok(document.querySelector('[data-toasts]').contains(element));
    await tick();
    assert.equal(element.isConnected, false);
  });

  test('data-confirm cancela el clic si el usuario no confirma', async () => {
    bz.start();
    let clicked = false;
    document.body.innerHTML = '<a href="#" data-confirm="¿Seguro?">Borrar</a>';
    document.querySelector('a').addEventListener('click', () => { clicked = true; });

    globalThis.confirmAnswer = false;
    document.querySelector('a').click();
    assert.equal(clicked, false);

    globalThis.confirmAnswer = true;
    document.querySelector('a').click();
    assert.equal(clicked, true);
  });

  test('data-dismiss quita el bloque [data-dismissible]', () => {
    bz.start();
    document.body.innerHTML = '<div data-dismissible><button data-dismiss>x</button></div>';

    document.querySelector('[data-dismiss]').click();
    assert.equal(document.querySelector('[data-dismissible]'), null);
  });

  test('un formulario data-remote se envía con fetch y muestra el mensaje', async () => {
    bz.start();
    nextResponse = { body: { message: 'Tarea creada' } };
    document.body.innerHTML = `
      <form action="/tareas" method="POST" data-remote>
        <input name="titulo" value="Pan"><button type="submit">Guardar</button>
      </form>`;
    let detail;
    document.querySelector('form').addEventListener('bootzen:success', (event) => { detail = event.detail; });

    document.querySelector('form').requestSubmit();
    await tick();

    assert.equal(fetchCalls[0].url, 'http://localhost/tareas');
    assert.equal(fetchCalls[0].init.body.get('titulo'), 'Pan');
    assert.deepEqual(detail, { message: 'Tarea creada' });
    assert.match(document.querySelector('[data-toasts]').textContent, /Tarea creada/);
    assert.equal(document.querySelector('button').disabled, false);
  });

  test('un formulario data-remote con error dispara bootzen:error y un aviso', async () => {
    bz.start();
    nextResponse = { status: 500, body: { message: 'Falló' } };
    document.body.innerHTML = '<form action="/x" method="POST" data-remote><button>Enviar</button></form>';
    let failed = false;
    document.querySelector('form').addEventListener('bootzen:error', () => { failed = true; });

    document.querySelector('form').requestSubmit();
    await tick();

    assert.equal(failed, true);
    assert.equal(document.querySelector('[data-toasts] [role="alert"]').textContent, 'Falló');
  });
});
